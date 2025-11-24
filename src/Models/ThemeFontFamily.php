<?php

namespace Toast\ThemeFonts\Models;

use SilverStripe\Assets\File;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\SiteConfig\SiteConfig;
use Toast\ThemeFonts\Models\ThemeFontFaceConfig;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;

class ThemeFontFamily extends DataObject
{
    private static $table_name = 'ThemeFontFamily';

    private static $singular_name = 'Font Family';

    private static $plural_name = 'Font Families';

    private static $db = [
        'SortOrder' => 'Int',
        'Title' => 'Varchar(255)',
        'FontFamily' => 'Varchar(255)',
    ];

    private static $many_many = [
        'ThemeFontFiles' => File::class,
        'ThemeFontConfigs' => ThemeFontConfig::class,
    ];

    private static $has_many = [
        'ThemeFontFaceConfigs' => ThemeFontFaceConfig::class,
    ];

    private static $owns = [
        'ThemeFontFiles',
    ];

    private static $belongs_many_many = [
        'SiteConfig' => SiteConfig::class
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'FontFamily' => 'Font Family',
    ];

    private static $default_sort = 'ID ASC';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['SortOrder', 'SiteConfig', 'ThemeFontFiles', 'ThemeFontFaceConfigs', 'ThemeFontConfigs']);

        $configs = $this->ThemeFontFaceConfigs();

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
                            ->setSource($this->getFontFilesArray());
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

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Title')
                ->setDescription('For your reference only'),
            TextField::create('FontFamily', 'Font Family')
                ->setDescription('Enter the font-family css value, ideally with fallback system fonts. e.g <code>"Roboto", sans-serif;</code>'),
            LiteralField::create('', '<div class="message warning">Fonts can be uploaded and configured in the Files tab.</div>'),
        ]);

        $fields->addFieldsToTab('Root.Files', [
            LiteralField::create('', '<div class="message warning">Configuration is needed for uploaded fonts. Save after uploading your fonts to configure them below.</div>'),
            UploadField::create('ThemeFontFiles', 'Font Files')
                // Allow multiple files to be uploaded
                ->setAllowedMaxFileNumber(null)
                // Set the folder to upload the files to
                ->setFolderName('Uploads/ThemeFonts')
                // Set the allowed file types
                ->setAllowedExtensions(['woff', 'woff2', 'ttf', 'otf', 'eot', 'svg'])
                // Set description
                ->setDescription('Font files are required if the font cannot be loaded through a cdn like Google or Adobe Fonts. Font files will be required for each font weight and style. e.g. If you require Roboto bold, and Roboto bold italic, you will need to upload these as 2 separate font files.'),
        ]);

        // Add the configuration field once the font files have been uploaded
        if ($this->ThemeFontFiles()->count()) {
            $fields->addFieldToTab('Root.Files', $fontsField);
        }

        return $fields;
    }

    // Helper function to format titles
    private static function formatFontFileTitle($title)
    {
        // Replace hyphens and underscores with spaces
        $title = str_replace(['-', '_'], ' ', $title);
        // Insert spaces before capital letters
        $title = preg_replace('/([a-z])([A-Z])/', '$1 $2', $title);
        // Capitalize the words
        $title = ucwords($title);
        return $title;
    }

    public function getFontFilesArray()
    {
        // Create an empty array
        $fontFiles = [];

        // Get the FontFiles
        $files = $this->ThemeFontFiles();

        // Loop through the FontFiles
        foreach ($files as $file) {
            // Add the formatted FontFile title to the array
            $fontFiles[$file->ID] = $this->formatFontFileTitle($file->Title);
        }

        // Return the array
        return $fontFiles;
    }

    static function getCurrentSiteConfig()
    {
        if ($siteConfig = DataObject::get_one(SiteConfig::class)) {
            return $siteConfig;
        }
        return;
    }

    public function getCMSValidator()
    {
        $required = new RequiredFields(['Title', 'FontFamily']);

        $this->extend('updateCMSValidator', $required);

        return $required;
    }

    // Method to get the default fonts
    protected function getDefaultFontFamilys()
    {
        $fonts = $this->config()->get('default_fonts') ?: [];
        return $fonts;
    }

    // Method to get available font weights
    protected function getAvailableWeights()
    {
        $weights = $this->config()->get('available_weights') ?: [];
        return $weights;
    }

    public function updateFontFamily()
    {
        // Get all the font items
        $configs = $this->ThemeFontFaceConfigs();

        // Loop through the font items
        foreach ($configs as $config) {
            $config->write();
        }

        if ($siteConfig = self::getCurrentSiteConfig()) {
            $siteConfig->generateThemeFontFiles();
        }
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        // Update the font items
        $this->updateFontFamily();
    }

    public function onAfterSkippedWrite()
    {
        // Update the font items
        $this->updateFontFamily();
    }
}
