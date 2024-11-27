<?php

namespace Toast\ThemeFonts\Models;

use SilverStripe\ORM\DB;
use SilverStripe\Assets\File;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Security;
use Toast\Forms\IconOptionsetField;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\SiteConfig\SiteConfig;
use Toast\ThemeFonts\Models\ThemeFontConfig;
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

    private static $db = [
        'SortOrder' => 'Int',
        'Title' => 'Varchar(255)',
        'CustomID' => 'Varchar(255)',
        'FontFamily' => 'Varchar(255)',
    ];

    private static $many_many = [
        'ThemeFontFiles' => File::class,
    ];

    private static $has_many = [
        'ThemeFontConfigs' => ThemeFontConfig::class,
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
        'CustomID' => 'FontFamily ID',
        'ID' => 'ID',
    ];

    private static $default_sort = 'ID ASC';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['SortOrder','SiteConfig','CustomID', 'ThemeFontFiles', 'ThemeFontConfigs']);

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
            'ThemeFontConfigs',
            'Configuration',
            $configs,
            $fontsConfig
        );

        $fontsField->getConfig()->getComponentByType(GridFieldEditableColumns::class)
            ->setDisplayFields([
                'FontFileID' => [
                    'title' => 'This Font File',
                    'callback' => function($record, $column, $grid) {
                        return DropdownField::create($column)
                            ->setEmptyString('None')
                            ->setSource($record::getFontFilesArray());
                    },
                ],
                'FontWeight' => [
                    'title' => 'Applies to font weight',
                    'callback' => function($record, $column, $grid) {
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
                    'callback' => function($record, $column, $grid) {
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
                ->setReadOnly(!$this->canChangeFontFamily())
                ->setDescription($this->canChangeFontFamily() ? (($this->CustomID) ? 'e.g. "' . $this->CustomID . '" - ' : '') . 'For your reference only' : 'This is the default theme font "' . $this->CustomID . '" and cannot be changed.'),
            TextField::create('FontFamily', 'Font Family')
                ->setReadOnly(!$this->canChangeFontFamily())
                ->setDescription($this->canChangeFontFamily() ? 'Paste the font-family css value. Eg: <code>Roboto, sans-serif</code>' : 'This is the default theme font "' . $this->CustomID . '" and cannot be changed.'),
            LiteralField::create('', '<div class="message warning">Fonts can be uploaded and configured in the Files tab.</div>'),
        ]);

        $fields->addFieldsToTab('Root.Files', [
            LiteralField::create('', '<div class="message warning">Configuration is needed for uploaded fonts. Save after uploading your fonts to configure them below.</div>'),
            UploadField::create('ThemeFontFiles', 'Font Files')
                // Allow multiple files to be uploaded
                ->setAllowedMaxFileNumber(null)
                // Set the folder to upload the files to
                ->setFolderName('fonts')
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

    static function getCurrentSiteConfig()
    {
        if($siteConfig = DataObject::get_one(SiteConfig::class)){
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

    public function canDelete($member = null)
    {
        // Get the restricted fonts
        $restricted = $this->getFontFamilyRestrictions();

        // Check to see if there is a key in the restricted array that matches the CustomID
        if (array_key_exists($this->CustomID, $restricted)) {
            return false;
        }

        return true;
    }

    public function canChangeFontFamily($member = null)
    {
        // Get the restricted fonts
        $restricted = $this->getFontFamilyRestrictions();

        if (array_key_exists($this->CustomID, $restricted)) {
            if ($restricted[$this->CustomID]['FontFamily']) {
                return false;
            }
        }

        return true;
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        if($siteConfig = self::getCurrentSiteConfig()){
            foreach ($this->getDefaultFontFamilys() as $font) {
                $key = key($font);
                $value = $font[$key];

                $existingRecord = $siteConfig->ThemeFontFamilies()->filter([
                    'CustomID' => $key,
                    'SiteConfig.ID' => $siteConfig->ID
                ])->first();

                if ($existingRecord) continue;

                $font = new ThemeFontFamily();
                $font->Title = $key;
                $font->CustomID = $key;
                if ($value) $font->FontFamily = $value;
                $font->write();
                $siteConfig->ThemeFontFamilies()->add($font->ID);
                DB::alteration_message("ThemeFontFamily '$key' created", 'created');
            }
        }
    }

    // Method to return the ID or CustomID
    public function getFontFamilyCustomID()
    {
        return ($this->CustomID) ? $this->CustomID : $this->ID;
    }

    // Method to return the ClassName
    public function getFontFamilyClassName()
    {
        // Prefix the class name with 'c-' in order to avoid numbers at the start of the class name
        $name = 'f-';
        // If we have a CustomID, use that, otherwise use the ID
        $name .= $this->CustomID ?: $this->ID;
        // Return the class name
        return $name;
    }

    // Method to get the restrictions for the fonts
    public function getFontFamilyRestrictions()
    {
        $retrictions = [];

        foreach ($this->getDefaultFontFamilys() as $font) {
            // We need to get the key, which is the name of the font
            $name = key($font);
            // We also need to get the value, which is the hex code
            $value = $font[$name];

            // The font cannot be deleted, if it is in the default fonts
            // The font's FontFamily value cannot be updated, if the $value is not null
            $retrictions[$name] = [
                'FontFamily' => ($value) ? true : false,
            ];

            // True means the field is read only
        }

        return $retrictions;
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
        $configs = $this->ThemeFontConfigs();

        // Loop through the font items
        foreach ($configs as $config) {
            $config->write();
        }

        if($siteConfig = self::getCurrentSiteConfig()){
            $siteConfig->generateThemeFontFiles();
        }
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // If the title is empty, set it to the CustomID
        if (!$this->Title) {
            // If we have a CustomID, set the Title to that
            return $this->Title = $this->getFontFamilyCustomID();
        }

        // Convert the title to all lowercase
        $this->Title = strtolower($this->Title);
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
