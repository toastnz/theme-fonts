<?php

namespace Toast\ThemeFonts\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use Toast\ThemeFonts\Models\ThemeFontFamily;

class ThemeFontConfig extends DataObject
{
    private static $table_name = 'ThemeFontConfig';

    private static $db = [
        'Title' => 'Varchar(255)',
        'FontFamilyID' => 'Int',
        'FontConfigID' => 'Varchar(255)',
        'SortOrder' => 'Int',
    ];

    private static $has_one = [
        'ThemeFontFamily' => ThemeFontFamily::class,
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'ThemeFontFamily.FontFamily' => 'Font Family',
    ];

    private static $default_sort = 'ID ASC';

    // Method to get the default fonts
    protected function getDefaultFontFamilys()
    {
        $fonts = $this->config()->get('default_fonts') ?: [];
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
        // Get the default fonts
        $default = $this->getDefaultFontFamilys();

        // Check to see if there is a key in the default array that matches the CustomID
        if (array_key_exists($this->FontConfigID, $default)) {
            return true;
        }

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
            foreach ($this->getDefaultFontFamilys() as $font) {
                $key = key($font);
                $value = $font[$key];

                $existingRecord = $siteConfig->ThemeFontFamilies()->filter([
                    'FontConfigID' => $key,
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

    // Helper function to format titles
    private static function formatFontFileTitle($title) {
        // Replace hyphens and underscores with spaces
        $title = str_replace(['-', '_'], ' ', $title);
        // Insert spaces before capital letters
        $title = preg_replace('/([a-z])([A-Z])/', '$1 $2', $title);
        // Capitalize the words
        $title = ucwords($title);
        return $title;
    }

    static function getFontFamilyArray() {
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
        $themeFontFamily = $this->ThemeFontFamily();

        if ($themeFontFamily) {
            // Set this item's FontFamily to the ThemeFontFamily FontFamily
            $this->FontFamily = $themeFontFamily->FontFamily;

            // Get the FontFileID
            $fontFileID = $this->FontFileID;

            // Check if the FontFileID is set and the file exists in the ThemeFontFamily's FontFiles
            if ($fontFileID && $fontFile = $themeFontFamily->ThemeFontFiles()->byID($fontFileID)) {
                $this->FontSrc = $fontFile->URL;
            } else {
                // Optionally, handle the case where the FontFileID is not set or the file does not exist
                $this->FontSrc = null;
            }
        } else {
            // Optionally, handle the case where the ThemeFontFamily is not set
            $this->FontFamily = null;
            $this->FontSrc = null;
        }
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
    }
}
