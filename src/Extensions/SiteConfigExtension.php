<?php

namespace Toast\ThemeFonts\Extensions;

use Psr\Log\LoggerInterface;
use SilverStripe\Assets\Filesystem;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Path;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DB;
use SilverStripe\Security\DefaultAdminService;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use Toast\ThemeFonts\Models\ThemeFontConfig;
use Toast\ThemeFonts\Models\ThemeFontFamily;

/**
 * Adds theme font management to SiteConfig. Every SiteConfig (including one per subsite when
 * silverstripe/subsites is installed) has its own font families, configs and generated files.
 *
 * @property string $ThemeFontPreconnects
 * @property string $ThemeFontLinks
 * @method \SilverStripe\ORM\ManyManyList<ThemeFontFamily> ThemeFontFamilies()
 * @method \SilverStripe\ORM\ManyManyList<ThemeFontConfig> ThemeFontConfigs()
 *
 * @extends Extension<SiteConfig&static>
 */
class SiteConfigExtension extends Extension
{
    /**
     * Folder (relative to the base folder) the generated font files are written to
     */
    private static string $css_folder_path = '/public/assets/styles/';

    /**
     * When true any member with the ADMIN permission can manage theme fonts, otherwise only the
     * default admin (SS_DEFAULT_ADMIN_USERNAME) can
     */
    private static bool $bypass_super_admin_check = false;

    private static array $db = [
        'ThemeFontPreconnects' => 'Text',
        'ThemeFontLinks' => 'Text',
    ];

    private static array $many_many = [
        'ThemeFontFamilies' => ThemeFontFamily::class,
        'ThemeFontConfigs' => ThemeFontConfig::class,
    ];

    /**
     * Every SiteConfig, regardless of the current subsite. Use this in tasks / builds where there
     * is no current subsite.
     *
     * @return SiteConfig[]
     */
    public static function getAllSiteConfigs(): array
    {
        $fetch = static fn (): array => SiteConfig::get()->toArray();

        $subsiteClass = 'SilverStripe\\Subsites\\Model\\Subsite';
        if (ClassInfo::exists($subsiteClass)) {
            return $subsiteClass::withDisabledSubsiteFilter($fetch);
        }

        return $fetch();
    }

    /**
     * TinyMCE `style_formats` entry listing this site's font configs. Static so it can be used
     * directly in HTMLEditorConfig setup, see getThemeFontFormats() for the extendable version.
     */
    public static function getFontFormatsForTinyMCE(): array
    {
        return SiteConfig::current_site_config()->getThemeFontFormats();
    }

    public function isSuperAdmin(?Member $member = null): bool
    {
        $member ??= Security::getCurrentUser();
        $isSuperAdmin = false;

        if ($member) {
            // Any administrator is a super admin when the bypass is enabled
            if ($this->getOwner()->config()->get('bypass_super_admin_check')) {
                $isSuperAdmin = Permission::checkMember($member, 'ADMIN');
            }

            if (!$isSuperAdmin) {
                $isSuperAdmin = DefaultAdminService::isDefaultAdmin($member->Email);
            }
        }

        $this->getOwner()->extend('updateIsSuperAdmin', $isSuperAdmin, $member);

        return (bool) $isSuperAdmin;
    }

    protected function updateCMSFields(FieldList $fields): void
    {
        $owner = $this->getOwner();

        $fields->removeByName([
            'ThemeFontPreconnects',
            'ThemeFontLinks',
            'ThemeFontFamilies',
            'ThemeFontConfigs',
        ]);

        if (!$owner->isSuperAdmin()) {
            return;
        }

        if (!$fields->fieldByName('Root.Customization')) {
            $fields->addFieldToTab('Root', TabSet::create('Customization'));
        }

        $fontFamiliesConfig = GridFieldConfig_RecordEditor::create(50);
        $fontFamiliesConfig
            ->addComponent(GridFieldOrderableRows::create('SortOrder'))
            ->removeComponentsByType(GridFieldDeleteAction::class);

        $fields->addFieldsToTab('Root.Customization.FontFamilies', [
            TextareaField::create('ThemeFontPreconnects', 'Preconnects')
                ->setAttribute('data-language', 'html')
                ->setAttribute('spellcheck', 'false')
                ->setDescription('Paste any extra link tags that are required, for example <code>&lt;link rel="preconnect" href="https://fonts.googleapis.com"&gt;</code>'),
            TextareaField::create('ThemeFontLinks', 'Font Links')
                ->setAttribute('data-language', 'html')
                ->setAttribute('spellcheck', 'false')
                ->setDescription('Paste the link tag, for example <code>&lt;link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet"&gt;</code>'),
            GridField::create(
                'ThemeFontFamilies',
                'Font Families',
                $owner->ThemeFontFamilies(),
                $fontFamiliesConfig
            ),
        ]);

        // Add the configuration field once a font family has been created
        if ($owner->ThemeFontFamilies()->exists()) {
            $fontsConfig = GridFieldConfig::create()
                ->addComponent(GridFieldButtonRow::create('before'))
                ->addComponent(GridFieldToolbarHeader::create())
                ->addComponent(GridFieldTitleHeader::create())
                ->addComponent($editableColumns = GridFieldEditableColumns::create())
                ->addComponent(GridFieldDeleteAction::create())
                ->addComponent(GridFieldOrderableRows::create('SortOrder'))
                ->addComponent($addNewButton = GridFieldAddNewInlineButton::create());

            $addNewButton->setTitle('Add Configuration');
            $editableColumns->setDisplayFields($owner->getFontsDisplayFieldsForCMS());

            $fields->addFieldToTab(
                'Root.Customization.FontFamilies',
                GridField::create('ThemeFontConfigs', 'Configuration', $owner->ThemeFontConfigs(), $fontsConfig)
            );
        }
    }

    /**
     * Columns of the font config editor, extend with `updateFontsDisplayFieldsForCMS`
     */
    public function getFontsDisplayFieldsForCMS(): array
    {
        $owner = $this->getOwner();
        $familyOptions = ThemeFontConfig::getFontFamilyArray($owner);

        $fields = [
            'Title' => [
                'title' => 'Title',
                'callback' => static function (ThemeFontConfig $record, string $column) {
                    return TextField::create($column)
                        ->setReadonly($record->isDefaultFont());
                },
            ],
            'ThemeFontFamilyID' => [
                'title' => 'Font Family',
                'callback' => static function (ThemeFontConfig $record, string $column) use ($familyOptions) {
                    return DropdownField::create($column)
                        ->setEmptyString('Unset')
                        ->setSource($familyOptions);
                },
            ],
        ];

        $owner->extend('updateFontsDisplayFieldsForCMS', $fields);

        return $fields;
    }

    /**
     * Key used in the generated file names, e.g. `mainsite` or `subsite-3`
     */
    public function getThemeFontsFileKey(): string
    {
        $owner = $this->getOwner();
        $key = ((int) $owner->ID === 1) ? 'mainsite' : 'subsite-' . $owner->ID;

        $owner->extend('updateThemeFontsFileKey', $key);

        return $key;
    }

    /**
     * Absolute path of the folder the generated files are written to
     */
    public function getThemeFontsFolderPath(): string
    {
        $folder = (string) $this->getOwner()->config()->get('css_folder_path');
        $path = Path::join(Director::baseFolder(), $folder);

        $this->getOwner()->extend('updateThemeFontsFolderPath', $path);

        return $path;
    }

    /**
     * Absolute path of the generated html (preload links + inline styles) rendered by `$ThemeFonts`
     */
    public function getThemeFontsSiteFilePath(): string
    {
        return Path::join($this->getThemeFontsFolderPath(), $this->getThemeFontsFileKey() . '-site-fonts.html');
    }

    /**
     * Absolute path of the generated stylesheet intended for the HTML editor
     */
    public function getThemeFontsEditorFilePath(): string
    {
        return Path::join($this->getThemeFontsFolderPath(), $this->getThemeFontsFileKey() . '-editor-fonts.css');
    }

    /**
     * Extract the href of every `<link>` tag in the given html
     *
     * @return string[]
     */
    public function extractHrefUrls(?string $links): array
    {
        $urls = [];

        if ($links && preg_match_all('/<link[^>]+href="([^"]+)"[^>]*>/i', $links, $matches)) {
            // The hrefs are html attribute values, decode them so they can be re-escaped for html or css
            $urls = array_values(array_filter(array_map(
                static fn (string $url): string => html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                $matches[1]
            )));
        }

        $this->getOwner()->extend('updateExtractedHrefUrls', $urls, $links);

        return $urls;
    }

    /**
     * Preload tags for the font links and font files used by this site's font configs
     */
    public function getFontLinks(): string
    {
        $owner = $this->getOwner();
        $html = '';

        foreach ($this->extractHrefUrls($owner->ThemeFontLinks) as $link) {
            $html .= '<link rel="preload" href="' . Convert::raw2att($link) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
        }

        $processedUrls = [];
        foreach ($this->getUsedFontFamilies() as $family) {
            foreach ($family->ThemeFontFaceConfigs() as $faceConfig) {
                $src = $faceConfig->getFontSrc();
                if (!$src || isset($processedUrls[$src])) {
                    continue;
                }

                $html .= '<link rel="preload" href="' . Convert::raw2att($src) . '" as="font" type="' . Convert::raw2att($faceConfig->getFontMimeType()) . '" crossorigin>';
                $processedUrls[$src] = true;
            }
        }

        $owner->extend('updateFontLinks', $html);

        return $html;
    }

    /**
     * `@import` statements for the editor stylesheet
     */
    public function getFontImports(): string
    {
        $owner = $this->getOwner();
        $css = '';

        foreach ($this->extractHrefUrls($owner->ThemeFontLinks) as $url) {
            $css .= '@import url("' . $this->escapeCSSString($url) . '");' . PHP_EOL;
        }

        foreach ($this->getUsedFontFamilies() as $family) {
            foreach ($family->ThemeFontFaceConfigs() as $faceConfig) {
                if ($src = $faceConfig->getFontSrc()) {
                    $css .= '@import url("' . $this->escapeCSSString($src) . '");' . PHP_EOL;
                }
            }
        }

        $owner->extend('updateFontImports', $css);

        return $css;
    }

    /**
     * All `@font-face` rules for this site's font families
     */
    public function getFontFaceCSS(): string
    {
        $css = '';

        foreach ($this->getOwner()->ThemeFontFamilies() as $family) {
            foreach ($family->ThemeFontFaceConfigs() as $faceConfig) {
                $css .= $faceConfig->getFontFaceCSS();
            }
        }

        $this->getOwner()->extend('updateFontFaceCSS', $css);

        return $css;
    }

    /**
     * The `:root` block of font family / bold weight css variables
     */
    public function getFontCSSVars(): string
    {
        $owner = $this->getOwner();
        $css = ':root {';

        foreach ($owner->ThemeFontConfigs() as $config) {
            $id = $config->getFontConfigKey();

            // Trim spacing and any trailing ; from the font family
            $value = rtrim(trim($config->getFontFamily()), ';');
            if ($value) {
                $css .= '--font-family-' . $id . ': ' . $value . ';';
            }

            if ($config->BoldFontWeight) {
                $css .= '--font-weight-' . $id . '-bold: ' . $config->BoldFontWeight . ';';
            }
        }

        $css .= '}';

        $owner->extend('updateFontCSSVars', $css);

        return $css;
    }

    /**
     * Write the `-site-fonts.html` and `-editor-fonts.css` files for this site. If the site has no
     * font families any previously generated files are removed.
     */
    public function generateThemeFontFiles(): void
    {
        $owner = $this->getOwner();

        if (!$owner->isInDB()) {
            return;
        }

        $siteFilePath = $this->getThemeFontsSiteFilePath();
        $editorFilePath = $this->getThemeFontsEditorFilePath();

        if (!$owner->ThemeFontFamilies()->exists()) {
            foreach ([$siteFilePath, $editorFilePath] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            return;
        }

        Filesystem::makeFolder($this->getThemeFontsFolderPath());

        $cssVars = $this->getFontCSSVars();
        $fontFaceCSS = $this->getFontFaceCSS();

        $siteStyles = $this->getFontLinks() . '<style>' . $cssVars . $fontFaceCSS;
        $editorStyles = $this->getFontImports() . $cssVars . $fontFaceCSS;

        foreach ($owner->ThemeFontConfigs() as $config) {
            if (!$config->getFontFamily()) {
                continue;
            }

            $id = $config->getFontConfigKey();
            $rules = '--_font-weight-max: ' . $config->BoldFontWeight . ';font-family: var(--font-family-' . $id . ');';

            $siteStyles .= '.font-family--' . $id . '{' . $rules . '}';
            $editorStyles .= 'body.mce-content-body .font-family--' . $id . '{' . $rules . '}';
        }

        $siteStyles .= '</style>';

        $owner->extend('updateThemeFontSiteStyles', $siteStyles);
        $owner->extend('updateThemeFontEditorStyles', $editorStyles);

        foreach ([$siteFilePath => $siteStyles, $editorFilePath => $editorStyles] as $path => $contents) {
            if (file_put_contents($path, $contents) === false) {
                Injector::inst()->get(LoggerInterface::class)->error('Theme fonts: unable to write ' . $path);
            }
        }

        $owner->extend('onAfterGenerateThemeFontFiles');
    }

    /**
     * Create a ThemeFontConfig for each of the `default_fonts` this site doesn't have yet
     */
    public function requireDefaultThemeFontConfigs(): void
    {
        $owner = $this->getOwner();

        if (!$owner->isInDB()) {
            return;
        }

        foreach (ThemeFontConfig::getDefaultFontKeys() as $key) {
            if ($owner->ThemeFontConfigs()->filter('FontConfigID', $key)->exists()) {
                continue;
            }

            $config = ThemeFontConfig::create();
            $config->Title = $key;
            $config->FontConfigID = $key;
            $config->write();
            $owner->ThemeFontConfigs()->add($config);

            DB::alteration_message("Font Config '$key' created for SiteConfig #{$owner->ID}", 'created');
        }

        $owner->extend('onAfterRequireDefaultThemeFontConfigs');
    }

    /**
     * Items for the TinyMCE `style_formats` option, one per font config with a font family
     */
    public function getThemeFontFormats(): array
    {
        $owner = $this->getOwner();
        $fontFormats = [];

        foreach ($owner->ThemeFontConfigs() as $config) {
            $family = $config->ThemeFontFamily();
            if (!$family->exists()) {
                continue;
            }

            $fontFormats[] = [
                'title' => 'Font Family / ' . ucwords((string) $family->Title) . ' (' . ucwords((string) $config->Title) . ')',
                'selector' => '*',
                'classes' => 'font-family--' . $config->getFontConfigKey(),
                'wrapper' => true,
                'merge_siblings' => true,
            ];
        }

        $formats = [[
            'title' => 'Font Family',
            'items' => $fontFormats,
        ]];

        $owner->extend('updateThemeFontFormats', $formats);

        return $formats;
    }

    /**
     * Seed default configs for every site on `sake db:build`. The hook runs on the SiteConfig
     * singleton, after SiteConfig has created its own default record.
     */
    protected function onRequireDefaultRecords(): void
    {
        foreach (static::getAllSiteConfigs() as $siteConfig) {
            $siteConfig->requireDefaultThemeFontConfigs();
        }
    }

    /**
     * Regenerate every site's font files at the end of `sake db:build`. Replaces the
     * SS5 `DatabaseAdmin` extension, that class no longer exists.
     */
    protected function onAfterBuild(): void
    {
        foreach (static::getAllSiteConfigs() as $siteConfig) {
            $siteConfig->generateThemeFontFiles();
        }
    }

    protected function onAfterWrite(): void
    {
        $owner = $this->getOwner();

        $owner->requireDefaultThemeFontConfigs();
        $owner->generateThemeFontFiles();
    }

    /**
     * Font families that are assigned to at least one of this site's font configs
     *
     * @return ThemeFontFamily[]
     */
    protected function getUsedFontFamilies(): array
    {
        $families = [];

        foreach ($this->getOwner()->ThemeFontConfigs() as $config) {
            $familyID = (int) $config->ThemeFontFamilyID;
            if ($familyID && !isset($families[$familyID])) {
                $family = $this->getOwner()->ThemeFontFamilies()->byID($familyID);
                if ($family) {
                    $families[$familyID] = $family;
                }
            }
        }

        return array_values($families);
    }

    protected function escapeCSSString(string $value): string
    {
        return addcslashes(str_replace(["\r", "\n"], '', $value), '"\\');
    }
}
