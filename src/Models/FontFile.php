<?php

namespace Toast\ThemeFonts\Models;

use SilverStripe\ORM\DB;
use SilverStripe\Assets\File;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use Toast\Forms\IconOptionsetField;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\LiteralField;
use Toast\ThemeFonts\Helpers\Helper;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\RequiredFields;
use Toast\ThemeFonts\Models\ThemeFont;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\AssetAdmin\Forms\UploadField;

class FontFile extends DataObject
{
    private static $table_name = 'FontFile';

    private static $db = [
        'SortOrder' => 'Int',
        'Weight' => 'Enum("100,200,300,400,500,600,700,800,900","400")',
        'Style' => 'Enum("normal,italic","normal")',
    ];

    private static $has_one = [
        'ThemeFont' => ThemeFont::class
    ];

    private static $many_many = [
        'ThemeFontFiles' => File::class
    ];

    private static $owns = [
        'ThemeFont',
        'ThemeFontFiles',
    ];

    private static $summary_fields = [
        'Weight' => 'Weight',
        'Style' => 'Style',
    ];

    private static $default_sort = 'Weight ASC';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['SortOrder', 'ThemeFontID', 'ThemeFontFiles']);

        $fields->addFieldsToTab('Root.Main', [
            DropdownField::create('Weight', 'Weight', [
                '100' => 'Thin 100',
                '200' => 'Extra Light 200',
                '300' => 'Light 300',
                '400' => 'Normal 400',
                '500' => 'Medium 500',
                '600' => 'Semi Bold 600',
                '700' => 'Bold 700',
                '800' => 'Extra Bold 800',
                '900' => 'Black 900',
            ]),
            DropdownField::create('Style', 'Style', [
                'normal' => 'Normal',
                'italic' => 'Italic',
            ]),
        ]);

        if ($this->ID) {
            $fields->addFieldsToTab('Root.Main', [
                UploadField::create('ThemeFontFiles', 'Font Files')
                    ->setFolderName('fonts')
                    ->setAllowedExtensions(['woff', 'woff2', 'ttf', 'eot', 'svg', 'otf'])
                    ->setDescription('Upload the font files you want to use. (EOT, SVG, TTF, WOFF, WOFF2, OTF)'),
            ]);
        } else {
            // Hide the CustomID field
            $fields->removeByName(['ThemeFontFiles']);
            $fields->insertAfter('Title', LiteralField::create('', '<div class="message notice">Upload field will become available after creating.</div>'));
        }

        return $fields;
    }

    public function getCMSValidator()
    {
        $required = new RequiredFields(['ThemeFontFiles']);

        $this->extend('updateCMSValidator', $required);

        return $required;
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

         // if database and siteconfig is ready, run this
         if (Security::database_is_ready()) {
            if ($this->ID && Helper::getCurrentSiteConfig()) Helper::generateCSSFiles();
        }
    }

    public function getFontFaceCSS() {
        $fontFaceCSS = '';

        if ($this->ThemeFontFiles()->exists()) {
            $fontFaceCSS .= '@font-face {';
            $fontFaceCSS .= 'font-family: "' . explode(',', $this->ThemeFont->FontFamily)[0] . '";';
            $fontFaceCSS .= 'font-weight: ' . $this->Weight . ';';
            $fontFaceCSS .= 'font-style: ' . $this->Style . ';';
            $fontFaceCSS .= 'src: ';

            foreach ($this->ThemeFontFiles() as $fontFile) {
                $fontFaceCSS .= 'url("' . $fontFile->URL . '")' . (($fontFile->ID != $this->ThemeFontFiles()->last()->ID) ? ', ' : ';');
            }

            $fontFaceCSS .= '}';
        }

        return $fontFaceCSS;
    }
}