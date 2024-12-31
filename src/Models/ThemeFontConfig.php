<?php

namespace Toast\ThemeFonts\Models;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use Toast\ThemeFonts\Models\ThemeFontFamily;

class ThemeFontConfig extends DataObject
{
    private static $table_name = 'ThemeFontConfig';

    private static $db = [
        'Title' => 'Varchar(255)',
        'FontFamily' => 'Varchar(255)',
        'FontFamilyID' => 'Int',
        'FontConfigID' => 'Varchar(255)',
        'SortOrder' => 'Int',
    ];

    private static $has_one = [
        'ThemeFontFamily' => ThemeFontFamily::class,
    ];

    private static $belongs_many_many = [
        'SiteConfig' => SiteConfig::class
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'ThemeFontFamily.FontFamily' => 'Font Family',
    ];

    private static $default_sort = 'ID ASC';

    // Method to get the default fonts
    protected function getDefaultFontFamilys()
    {
        $fontFamily = new ThemeFontFamily();
        $fonts = $fontFamily->config()->get('default_fonts') ?: [];
        return $fonts;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        return $fields;
    }

    static function getCurrentSiteConfig()
    {
        if ($siteConfig = DataObject::get_one(SiteConfig::class)) {
            return $siteConfig;
        }
        return;
    }

    public function isDefaultFont()
    {
        if ($this->FontConfigID) return true;

        return false;
    }

    public function canDelete($member = null)
    {
        return !$this->isDefaultFont();
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        if ($siteConfig = self::getCurrentSiteConfig()) {
            foreach ($this->getDefaultFontFamilys() as $key) {
                // Check if the record already exists
                $existingRecord = $siteConfig->ThemeFontConfigs()->filter([
                    'FontConfigID' => $key,
                    'SiteConfig.ID' => $siteConfig->ID
                ])->first();

                // Skip if the record already exists
                if ($existingRecord) continue;

                // Create the new record
                $font = new ThemeFontConfig();
                $font->Title = $key;
                $font->FontConfigID = $key;
                $font->write();
                $siteConfig->ThemeFontConfigs()->add($font->ID);
                DB::alteration_message("Font Config '$key' created", 'created');
            }
        }
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

    static function getFontFamilyArray()
    {
        // Get the ThemeFontFamily
        $themeFontFamily = ThemeFontFamily::get();
        // Create an empty array
        $fontFiles = [];

        // Loop through the ThemeFontFamily
        foreach ($themeFontFamily as $family) {
            // Add the formatted FontFile title to the array
            $fontFiles[$family->ID] = self::formatFontFileTitle($family->Title);
        }

        // Return the array
        return $fontFiles;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Get the ThemeFontFamily
        $themeFontFamilies = ThemeFontFamily::get();

        // Get the ThemeFontFamily that matches the FontFamilyID
        $themeFontFamily = $themeFontFamilies->find('ID', $this->FontFamilyID);

        if ($themeFontFamily) {
            // Set this item's FontFamily to the ThemeFontFamily FontFamily
            $this->FontFamily = $themeFontFamily->FontFamily;
        } else {
            // Optionally, handle the case where the ThemeFontFamily is not set
            $this->FontFamily = null;
        }
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
    }
}
