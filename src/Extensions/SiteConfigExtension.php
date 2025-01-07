<?php

namespace Toast\ThemeFonts\Extensions;

use SilverStripe\Forms\TabSet;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Security\Security;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Forms\GridField\GridField;
use Toast\ThemeFonts\Models\ThemeFontConfig;
use Toast\ThemeFonts\Models\ThemeFontFamily;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;

class SiteConfigExtension extends Extension
{
    private static $db = [
        'ThemeFontPreconnects' => 'Text',
        'ThemeFontLinks' => 'Text',
    ];

    private static $many_many = [
        'ThemeFontFamilies' => ThemeFontFamily::class,
        'ThemeFontConfigs' => ThemeFontConfig::class,
    ];

    public function isSuperAdmin()
    {
        if ($defaultUser = Environment::getEnv('SS_DEFAULT_ADMIN_USERNAME')) {
            if ($currentUser = Security::getCurrentUser()) {
                return $currentUser->Email == $defaultUser;
            }
        }
        return false;
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName([
            'ThemeFontPreconnects',
            'ThemeFontLinks',
            'ThemeFontFamilies',
            'ThemeFontConfigs',
        ]);

        if (Security::database_is_ready() && $this->isSuperAdmin()) {
            // if Root.Customization doesn't exist, create it
            if (!$fields->fieldByName('Root.Customization')) {
                $fields->addFieldToTab('Root', TabSet::create('Customization'));
            }

            $fontFamiliesConfig = GridFieldConfig_RecordEditor::create(50);
            $fontFamiliesConfig
                ->addComponent(GridFieldOrderableRows::create('SortOrder'))
                ->removeComponentsByType(GridFieldDeleteAction::class);

            $fontFamiliesField = GridField::create(
                'ThemeFontFamilies',
                'Font Families',
                $this->owner->ThemeFontFamilies(),
                $fontFamiliesConfig
            );

            $fields->addFieldsToTab('Root.Customization.Fonts', [
                TextareaField::create('ThemeFontPreconnects', 'Preconnects')
                    ->setDescription('Paste any extra link tags that are required, for example <code>&lt;link rel="preconnect" href="https://fonts.googleapis.com"&gt;</code>'),
                TextareaField::create('ThemeFontLinks', 'Font Links')
                    ->setDescription('Paste the link tag, for example <code>&lt;link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet"&gt;</code>'),
                $fontFamiliesField,
            ]);

            $configs = $this->owner->ThemeFontConfigs();

            $fontsConfig = GridFieldConfig::create();

            $fontsConfig
                ->addComponent(new GridFieldButtonRow('before'))
                ->addComponent(new GridFieldToolbarHeader())
                ->addComponent(new GridFieldTitleHeader())
                ->addComponent(new GridFieldEditableColumns())
                ->addComponent(new GridFieldDeleteAction())
                ->addComponent(GridFieldOrderableRows::create('SortOrder'))
                ->addComponent($addNewButton = new GridFieldAddNewInlineButton());

            $addNewButton->setTitle('Add Configuration');

            $fontsField = GridField::create(
                'ThemeFontFaceConfigs',
                'Configuration',
                $configs,
                $fontsConfig
            );

            $fontsField->getConfig()->getComponentByType(GridFieldEditableColumns::class)
                ->setDisplayFields([
                    'Title' => [
                        'title' => 'Title',
                        'callback' => function ($record, $column, $grid) {
                            return TextField::create($column)
                                ->setReadonly($record->isDefaultFont());
                        },
                    ],
                    'FontFamilyID' => [
                        'title' => 'Font Family',
                        'callback' => function ($record, $column, $grid) {
                            return DropdownField::create($column)
                                ->setEmptyString('Unset')
                                ->setSource($record::getFontFamilyArray());
                        },
                    ],
                ]);

            // Add the configuration field once the font files have been uploaded
            if ($this->owner->ThemeFontFamilies()->count()) {
                $fields->addFieldToTab('Root.Customization.Fonts', $fontsField);
            }
        }
    }

    static function getCurrentSiteConfig()
    {
        if ($siteConfig = DataObject::get_one(SiteConfig::class)) {
            return $siteConfig;
        }
        return;
    }

    static function extractHrefUrls($links)
    {
        $urls = [];
        $pattern = '/<link[^>]+href="([^"]+)"[^>]*>/i';

        preg_match_all($pattern, $links, $matches);

        if (!empty($matches[1])) {
            $urls = $matches[1];
        }

        return $urls;
    }

    static function getFontLinks()
    {
        $html = '';

        // Get the current site's config
        $siteConfig = self::getCurrentSiteConfig();

        if (!$siteConfig) return $html;

        $fonts = $siteConfig->ThemeFontConfigs();
        $fontFamilies = $siteConfig->ThemeFontFamilies();

        // Preload the ThemeFonts
        $fontLinks = self::extractHrefUrls($siteConfig->ThemeFontLinks);

        $processedUrls = [];

        // Process theme fonts
        foreach ($fontLinks as $link) {
            if (empty($link)) continue;

            $html .= '<link rel="preload" href="' . $link . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
        }

        // Process site font families
        foreach ($fonts as $config) {
            // Get the font's font family
            if ($family = $fontFamilies->find('ID', $config->FontFamilyID)) {
                foreach ($family->ThemeFontFaceConfigs() as $config) {
                    if (empty($config->FontSrc) || isset($processedUrls[$config->FontSrc])) {
                        continue;
                    }

                    $html .= '<link rel="preload" href="' . $config->FontSrc . '" as="font" type="font/' . $config->FontType . '" crossorigin>';
                    $processedUrls[$config->FontSrc] = true;
                }
            }
        }

        return $html;
    }

    static function getFontImports($siteConfig)
    {
        $css = '';

        // Import the ThemeFonts
        $fonts = self::extractHrefUrls($siteConfig->ThemeFontLinks);

        foreach ($fonts as $font) {
            // Make sure the value is not empty
            if (empty($font)) {
                continue;
            }

            // Add the @import statement
            $css .= '@import url("' . $font . '");' . PHP_EOL;
        }

        // Import the FontFiles
        $fonts = $siteConfig->ThemeFontConfigs();
        $fontFamilies = $siteConfig->ThemeFontFamilies();

        // Process site font families
        foreach ($fonts as $font) {
            // Get the font's font family
            if ($family = $fontFamilies->find('ID', $font->FontFamilyID)) {
                foreach ($family->ThemeFontFaceConfigs() as $config) {
                    // Make sure the FontSrc is not empty
                    if (empty($config->FontSrc)) {
                        continue;
                    }

                    // Add the @import statement for the font file
                    $css .= '@import url("' . $config->FontSrc . '");' . PHP_EOL;
                }
            }
        }

        return $css;
    }

    static function getFontFaceCSS()
    {
        $fontFaceCSS = '';

        // Get the current site's config
        $siteConfig = self::getCurrentSiteConfig();
        // Get the site's font families
        $fontFamilies = $siteConfig->ThemeFontFamilies();

        // Loop through the font families
        foreach ($fontFamilies as $fontFamily) {
            // Get the font files
            $files = $fontFamily->ThemeFontFiles();
            // Get the configurations
            $configurations = $fontFamily->ThemeFontFaceConfigs();
            // Loop through the configurations
            foreach ($configurations as $configuration) {
                $fontFaceCSS .= $configuration->getFontFaceCSS();
            }
        }

        return $fontFaceCSS;
    }

    public function generateThemeFontFiles()
    {
        // Get the current site's config
        if ($siteConfig = self::getCurrentSiteConfig()) {
            // Get the site's ID and append to the CSS file name
            $styleID = ($siteConfig->ID == 1) ? 'mainsite' : 'subsite-' . $siteConfig->ID;

            // Get the site's fonts
            $families = $siteConfig->ThemeFontFamilies();
            $configs = $siteConfig->ThemeFontConfigs();

            // If we have fonts
            if ($families->exists()) {
                // Get folder path from config
                $folderPath = Config::inst()->get(SiteConfig::class, 'css_folder_path');

                // If folder doesn't exist, create it
                if (!file_exists(Director::baseFolder() . $folderPath)) {
                    mkdir(Director::baseFolder() . $folderPath, 0777, true);
                }

                $CSSFilePath = Director::baseFolder() . $folderPath;
                $siteCSSFilePath = $CSSFilePath . $styleID . '-site-fonts.html';
                $editorCSSFilePath = $CSSFilePath . $styleID . '-editor-fonts.css';

                // Remove files if they exist
                if (file_exists($siteCSSFilePath)) unlink($siteCSSFilePath);
                if (file_exists($editorCSSFilePath)) unlink($editorCSSFilePath);

                // Create a new file
                $CSSVars = ':root {';

                // Loop through font configs and add CSS vars
                foreach ($configs as $config) {
                    if ($family = $config->FontFamily) {
                        // Trim any trailing spacing from the font family
                        $value = trim($family);
                        // Remove any ; at the end of the string
                        $value = rtrim($value, ';');

                        if ($value) {
                            $id = ($config->FontConfigID) ?: $config->ID;
                            // Add the CSS var
                            $CSSVars .= '--font-family-' . $id . ': ' . $value . ';';
                        }
                    }
                }
                // Close the file
                $CSSVars .= '}';

                // Get the font links and add them to the theme styles
                $siteStyles = self::getFontLinks($siteConfig);
                $editorStyles = self::getFontImports($siteConfig);

                // Create a new file for the theme
                $siteStyles .= '<style>';
                $siteStyles .= $CSSVars;
                // Create a new file for the editor
                $editorStyles .= $CSSVars;

                // Loop through fonts and add styles
                foreach ($families as $family) {
                    foreach ($family->ThemeFontFaceConfigs() as $fontFace) {
                        $siteStyles .= $fontFace->getFontFaceCSS();
                        $editorStyles .= $fontFace->getFontFaceCSS();
                    }
                }

                // Loop through font configs and add CSS vars
                foreach ($configs as $config) {
                    if ($config->FontFamily) {
                        $id = ($config->FontConfigID) ?: $config->ID;

                        // Theme styles
                        $siteStyles .= '.font-family--' . $id . '{';
                        $siteStyles .= 'font-family: var(--font-family-' . $id . ');';
                        $siteStyles .= '}';

                        // Editor styles
                        $editorStyles .= 'body.mce-content-body .font-family--' . $id . '{';
                        $editorStyles .= 'font-family: var(--font-family-' . $id . ');';
                        $editorStyles .= '}';
                    }
                }

                // Close the file
                $siteStyles .= '</style>';

                // Write to file
                try {
                    file_put_contents($siteCSSFilePath, $siteStyles);
                    file_put_contents($editorCSSFilePath, $editorStyles);
                } catch (\Exception $e) {
                    // Handle the exception
                    error_log('Error writing font files: ' . $e->getMessage());
                }
            }
        }
    }

    static function getFontFormatsForTinyMCE()
    {
        $formats = [];
        $fontFormats = [];

        // Get the current site's config
        if ($siteConfig = self::getCurrentSiteConfig()) {
            // Get the site's fonts
            $families = $siteConfig->ThemeFontFamilies();
            $configs = $siteConfig->ThemeFontConfigs();
            $includedFamilies = [];

            if ($families->exists()) {
                // Loop through font configs and add CSS vars
                foreach ($configs as $config) {
                    // Make sure there is a font family before adding it to the array
                    if (!$family = $config->ThemeFontFamily()) continue;

                    // Check if the font family has already been added
                    if (in_array($family->ID, $includedFamilies)) continue;

                    // Add the font family to the included families array
                    $includedFamilies[] = $family->ID;

                    // Get the font config ID
                    $id = ($config->FontConfigID) ?: $config->ID;

                    // Grab the title and make it title case
                    $title = $family->Title;
                    $title = ucwords($title);

                    $fontFormats[] = [
                        'title'          => 'Font Family / ' . $title,
                        'selector'       => '*',
                        'classes'        => 'font-family--' . $id,
                        'wrapper'        => true,
                        'merge_siblings' => true,
                    ];
                }
            }

            // Make the array unique

            $formats[] = [
                'title' => 'Font Family',
                'items' => $fontFormats,
            ];
        };

        return $formats;
    }

    public function onBeforeWrite()
    {
        // Get all the font families
        $configs = $this->owner->ThemeFontFamilies();

        // Loop all the families
        foreach ($configs as $family) {
            $family->write();
            // Get all the font item
            $configs = $family->ThemeFontFaceConfigs();
            // Loop all the items
            foreach ($configs as $config) {
                // Write the item
                $config->write();
            }
        }
    }

    public function onAfterWrite()
    {
        // If we have theme font families, but dont have any theme font configs, create them
        if ($this->owner->ID) {
            $font = new ThemeFontConfig();
            $font->requireDefaultRecords();
        }

        self::generateThemeFontFiles();
    }
}
