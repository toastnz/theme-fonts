<?php

namespace Toast\ThemeFonts\Extensions;

use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Security;
use SilverStripe\Core\Config\Config;
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

class SiteConfigExtension extends DataExtension
{
    private static $db = [
        'ThemeFontPreconnects' => 'Text',
        'ThemeFontLinks' => 'Text',
    ];

    private static $many_many = [
        'ThemeFontFamilies' => ThemeFontFamily::class,
        'ThemeFontConfigs' => ThemeFontConfig::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        // Check if the user is a super admin
        if (Security::database_is_ready() && self::isSuperAdmin()) {

            // if Root.Customization doesn't exist, create it
            if (!$fields->fieldByName('Root.Customization')) {
                $fields->addFieldToTab('Root', TabSet::create('Customization'));
            }

            $fontFamiliesConfig = GridFieldConfig_RecordEditor::create(50);
            $fontFamiliesConfig->addComponent(GridFieldOrderableRows::create('SortOrder'));
            $fontFamiliesConfig->removeComponentsByType(GridFieldDeleteAction::class);

            $fontFamiliesField = GridField::create(
                'ThemeFontFamilies',
                'Font Families',
                $this->owner->ThemeFontFamilies(),
                $fontFamiliesConfig
            );

            $fields->addFieldsToTab('Root.Customization.Fonts', [
                TextareaField::create('ThemeFontPreconnects', 'Preconnects')
                    ->setDescription('Paste any extra link tags that are required, for example <code>&lt;link rel="preconnect" href="https://fonts.googleapis.com"&gt;</code>.'),
                TextareaField::create('ThemeFontLinks', 'Font Links')
                    ->setDescription('Paste the link tag, for example <code>&lt;link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet"&gt;</code>.'),
                $fontFamiliesField,
            ]);

            $configs = $this->ThemeFontConfigs();

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
                    'FontFileID' => [
                        'title' => 'This Font File',
                        'callback' => function ($record, $column, $grid) {
                            return DropdownField::create($column)
                                ->setEmptyString('None')
                                ->setSource($record::getFontFilesArray());
                        },
                    ],
                    'FontWeight' => [
                        'title' => 'Applies to font weight',
                        'callback' => function ($record, $column, $grid) {
                            $fontWeightTitles = [
                                '100' => 'Extra Light',
                                '200' => 'Light',
                                '300' => 'Book',
                                '400' => 'Regular',
                                '500' => 'Medium',
                                '600' => 'Semi Bold',
                                '700' => 'Bold',
                                '800' => 'Extra Bold',
                                '900' => 'Black'
                            ];

                            // Remove any $fontWeightTitles that are not in the available weights
                            $availableWeights = $this->getAvailableWeights();

                            foreach ($fontWeightTitles as $key => $value) {
                                if (!in_array($key, $availableWeights)) {
                                    unset($fontWeightTitles[$key]);
                                }
                            }

                            return DropdownField::create($column)
                                ->setSource($fontWeightTitles);
                        },
                    ],
                    'FontStyle' => [
                        'title' => 'When font style is',
                        'callback' => function ($record, $column, $grid) {
                            $fontStyleTitles = [
                                'normal' => 'Normal',
                                'italic' => 'Italic'
                            ];

                            return DropdownField::create($column)
                                ->setSource($fontStyleTitles);
                        },
                    ],
                ]);

            // Add the configuration field once the font files have been uploaded
            if ($this->ThemeFontFamilies()->count()) {
                $fields->addFieldToTab('Root.Files', $fontsField);
            }
        }
    }

    static function getCurrentSiteConfig()
    {
        if($siteConfig = DataObject::get_one(SiteConfig::class)){
            return $siteConfig;
        }
        return;
    }

    static function isSuperAdmin()
    {
         if ($defaultUser = Environment::getEnv('SS_DEFAULT_ADMIN_USERNAME')) {
            if ($currentUser = Security::getCurrentUser()) {
                $allowed = false;
                // all toast email owner is a superadmin
                if($currentUser->Email == $defaultUser || strstr($currentUser->Email, '@toast.co.nz')){
                    $allowed = true;
                }

               // extend this method
                $currentUser->extend('updateSuperAdmin', $allowed);

                return $allowed;
            }
        }
        return false;
    }

    static function extractHrefUrls($links) {
        $urls = [];
        $pattern = '/<link[^>]+href="([^"]+)"[^>]*>/i';

        preg_match_all($pattern, $links, $matches);

        if (!empty($matches[1])) {
            $urls = $matches[1];
        }

        return $urls;
    }

    static function getFontLinks() {
        $html = '';

        // Get the current site's config
        $siteConfig = self::getCurrentSiteConfig();
        if (!$siteConfig) {
            return $html;
        }

        // Preload the FontFiles
        $themeFontFamilies = $siteConfig->ThemeFontFamilies();

        // Preload the ThemeFonts
        $fonts = self::extractHrefUrls($siteConfig->ThemeFontLinks);

        $processedUrls = [];

        // Process theme fonts
        foreach ($fonts as $font) {
            if (empty($font)) {
                continue;
            }

            $html .= '<link rel="preload" href="' . $font . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
        }

        // Process site font families
        foreach ($themeFontFamilies as $family) {
            foreach ($family->ThemeFontConfigs() as $config) {
                if (empty($config->FontSrc) || isset($processedUrls[$config->FontSrc])) {
                    continue;
                }

                $html .= '<link rel="preload" href="' . $config->FontSrc . '" as="font" type="font/' . $config->FontType . '" crossorigin>';
                $processedUrls[$config->FontSrc] = true;
            }
        }

        return $html;
    }

    static function getFontImports($siteConfig) {
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
        $themeFontFamilies = $siteConfig->ThemeFontFamilies();

        foreach ($themeFontFamilies as $themeFontFamily) {
            foreach ($themeFontFamily->ThemeFontConfigs() as $config) {
                // Make sure the FontSrc is not empty
                if (empty($config->FontSrc)) {
                    continue;
                }

                // Add the @import statement for the font file
                $css .= '@import url("' . $config->FontSrc . '");' . PHP_EOL;
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
            $configurations = $fontFamily->ThemeFontConfigs();
            // Loop through the configurations
            foreach ($configurations as $configuration) {
                $fontFaceCSS .= $configuration->getFontFaceCSS();
            }
        }

        return $fontFaceCSS;
    }

    static function generateThemeFontFiles()
    {
        // Get the current site's config
        if ($siteConfig = self::getCurrentSiteConfig()) {
            // Get the site's ID and append to the CSS file name
            $styleID = ($siteConfig->ID == 1) ? 'mainsite' : 'subsite-' . $siteConfig->ID;

            // Get the site's fonts
            $families = $siteConfig->ThemeFontFamilies();

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

                // Loop through fonts and add CSS vars
                foreach ($families as $font) {
                    if ($font->FontFamily) {
                        // Trim any trailing spacing from the font family
                        $family = trim($font->FontFamily);
                        // Remove any ; at the end of the string
                        $family = rtrim($family, ';');
                        // Add the CSS var
                        $CSSVars .= '--' . $font->getFontFamilyClassName() . ': ' . $family . ';';
                    }
                }
                // Close the file
                $CSSVars .= '}';

                // If the ThemeFontLinks field is empty
                if (!$siteConfig->ThemeFontLinks) {
                    // Load the site's fonts imports to the file
                    if ($siteConfig->ThemeFontImports) {
                        $CSSVars .= $siteConfig->ThemeFontImports;
                    }
                }

                // Get the font links and add them to the theme styles
                $siteStyles = self::getFontLinks($siteConfig);
                $editorStyles = self::getFontImports($siteConfig);

                // Create a new file for the theme
                $siteStyles .= '<style>';
                $siteStyles .= $CSSVars;
                // Create a new file for the editor
                $editorStyles .= $CSSVars;

                // Loop through fonts and add styles
                foreach ($families as $font) {
                    if ($font->ThemeFontConfigs()->exists()) {
                        foreach ($font->ThemeFontConfigs() as $config) {
                            $siteStyles .= $config->getFontFaceCSS();
                            $editorStyles .= $config->getFontFaceCSS();
                        }
                    }

                    if ($font->FontFamily) {
                        $className = $font->getFontFamilyClassName();
                        // Theme styles
                        $siteStyles .= '.font-family--' . $className . '{';
                        $siteStyles .= 'font-family: var(--' . $className . ');';
                        $siteStyles .= '}';

                        // Editor styles
                        $editorStyles .= 'body.mce-content-body .font-family--' . $className . '{';
                        $editorStyles .= 'font-family: var(--' . $className . ');';
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
            // Get the site's font families
            $fontFamilies = $siteConfig->ThemeFontFamilies();

            // get current fonts
            foreach ($fontFamilies as $family) {
                // Make sure there is a font family before adding it to the array
                if (!$family->FontFamily) continue;
                // Add the font to the array

                // Grab the title and make it title case
                $title = $family->Title;
                $title = ucwords($title);

                $fontFormats[] = [
                    'title'          => 'Font Family / ' . $title,
                    'selector'       => '*',
                    'classes'        => 'font-family--' . $family->getFontFamilyClassName(),
                    'wrapper'        => true,
                    'merge_siblings' => true,
                ];
            }

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
        $families = $this->owner->ThemeFontFamilies();

        // Loop all the families
        foreach ($families as $family) {
            $family->write();
            // Get all the font item
            $configs = $family->ThemeFontConfigs();
            // Loop all the items
            foreach ($configs as $config) {
                // Write the item
                $config->write();
            }
        }

        parent::onBeforeWrite();
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if($this->owner->ID && !$this->owner->ThemeFontFamilies()->exists()){
            $font = new ThemeFontFamily();
            $font->requireDefaultRecords();
        }

        self::generateThemeFontFiles();
    }
}
