<?php

namespace Toast\ThemeFonts\Models;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use Toast\Forms\IconOptionsetField;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\RequiredFields;
use Toast\ThemeFonts\Helpers\Helper;
use SilverStripe\SiteConfig\SiteConfig;

class ThemeFont extends DataObject
{
    private static $table_name = 'ThemeFont';

    private static $db = [
        'SortOrder' => 'Int',
        'Title' => 'Varchar(255)',
        'CustomID' => 'Varchar(255)',
        'FontFamily' => 'Varchar(255)',
    ];

    private static $has_many = [
        'FontFiles' => FontFile::class
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
        $fields->removeByName(['SortOrder','SiteConfig','CustomID']);

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Title')
                ->setReadOnly(!$this->canChangeFontFamily())
                ->setDescription($this->canChangeFontFamily() ? (($this->CustomID) ? 'e.g. "' . $this->CustomID . '" - ' : '') . 'Please limit to 30 characters' : 'This is the default theme font "' . $this->CustomID . '" and cannot be changed.'),
        ]);

        if ($this->ID) {
            $fields->addFieldsToTab('Root.Main', [
                TextField::create('FontFamily', 'Font Family')
                    ->setReadOnly(!$this->canChangeFontFamily())
                    ->setDescription($this->canChangeFontFamily() ? 'Paste the font family you want to use. Eg: <code>Roboto, sans-serif</code>' : 'This is the default theme font "' . $this->CustomID . '" and cannot be changed.'),
            ]);
        } else {
            // Hide the CustomID field
            $fields->removeByName(['FontFamily']);
            $fields->insertAfter('Title', LiteralField::create('', '<div class="message notice">Font Family field will become available after creating.</div>'));
        }

        return $fields;
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

         // if database and siteconfig is ready, run this
         if (Security::database_is_ready()) {
            if ($this->ID && Helper::getCurrentSiteConfig()) Helper::generateCSSFiles();
        }
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        if($siteConfig = Helper::getCurrentSiteConfig()){
            foreach ($this->getDefaultFontFamilys() as $font) {
                $key = key($font);
                $value = $font[$key];

                $existingRecord = $siteConfig->ThemeFonts()->filter([
                    'CustomID' => $key,
                    'SiteConfig.ID' => $siteConfig->ID
                ])->first();

                if ($existingRecord) continue;

                $font = new ThemeFont();
                $font->Title = $key;
                $font->CustomID = $key;
                if ($value) $font->FontFamily = $value;
                $font->write();
                $siteConfig->ThemeFonts()->add($font->ID);
                DB::alteration_message("ThemeFont '$key' created", 'created');
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
        return $this->config()->get('default_fonts') ?: [];
    }
}