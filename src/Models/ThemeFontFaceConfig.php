<?php

namespace Toast\ThemeFonts\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use Toast\ThemeFonts\Models\ThemeFontFamily;

class ThemeFontFaceConfig extends DataObject
{
    private static $table_name = 'ThemeFontFaceConfig';

    private static $db = [
        'SortOrder' => 'Int',
        'FontFamily' => 'Varchar(255)',
        'FontWeight' => 'Enum("100,200,300,400,500,600,700,800,900", "400")',
        'FontStyle' => 'Enum("normal,italic", "normal")',
        'FontFileID' => 'Int',
        'FontSrc' => 'Text',
        'FontType' => 'Varchar(50)',
    ];

    private static $has_one = [
        'ThemeFontFamily' => ThemeFontFamily::class,
    ];

    private static $summary_fields = [
        'FontWeight' => 'Font Weight',
        'FontStyle' => 'Font Style',
    ];

    private static $default_sort = 'ID ASC';

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

    static function getFontFilesArray()
    {
        // Get the ThemeFontFamily
        $themeFontFamily = ThemeFontFamily::get();
        // Create an empty array
        $fontFiles = [];
        // Loop through the ThemeFontFamily
        foreach ($themeFontFamily as $family) {
            // Get the FontFiles
            $files = $family->ThemeFontFiles();
            // Loop through the FontFiles
            foreach ($files as $file) {
                // Add the formatted FontFile title to the array
                $fontFiles[$file->ID] = self::formatFontFileTitle($file->Title);
            }
        }
        // Return the array
        return $fontFiles;
    }

    public function getFontFaceCSS()
    {
        $fontFaceCSS = '';

        // Check if there is a src
        if ($this->FontSrc) {
            $fontFamily = explode(',', $this->FontFamily)[0];

            // Strip any quotes from the start and end of the font family name
            $fontFamily = trim($fontFamily, '"\'');

            $fontFaceCSS = '@font-face {';
            $fontFaceCSS .= 'font-family: "' . $fontFamily . '";';
            $fontFaceCSS .= 'font-weight: ' . $this->FontWeight . ';';
            $fontFaceCSS .= 'font-style: ' . $this->FontStyle . ';';
            $fontFaceCSS .= 'font-display: swap;';
            $fontFaceCSS .= 'src: url("' . $this->FontSrc . '");';
            $fontFaceCSS .= '}';
        };

        return $fontFaceCSS;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Get the ThemeFontFamily
        $themeFontFamily = $this->ThemeFontFamily();

        $this->FontSrc = null;
        $this->FontType = null;
        $this->FontFamily = null;

        if ($themeFontFamily) {
            // Set this item's FontFamily to the ThemeFontFamily FontFamily
            $this->FontFamily = $themeFontFamily->FontFamily;

            // Get the FontFileID
            $fontFileID = $this->FontFileID;

            // Check if the FontFileID is set and the file exists in the ThemeFontFamily's FontFiles
            if ($fontFileID && $fontFile = $themeFontFamily->ThemeFontFiles()->byID($fontFileID)) {
                $this->FontSrc = $fontFile->URL;
                $this->FontType = $fontFile->Extension;
            }
        }
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
    }
}
