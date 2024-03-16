<?php

namespace Toast\ThemeFonts\Extensions;

use SilverStripe\ORM\DB;
use Toast\ThemeFonts\Helpers\Helper;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Security;
use SilverStripe\Forms\LiteralField;
use Toast\Tasks\GenerateFontCssTask;
use SilverStripe\Forms\TextareaField;
use Toast\ThemeFonts\Models\ThemeFont;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;

class SiteConfigExtension extends DataExtension
{
    private static $db = [
        'ThemeFontLinks' => 'Text',
        'ThemeFontCache' => 'HTMLText',
    ];

    private static $many_many = [
        'ThemeFonts' => ThemeFont::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        /** -----------------------------------------
         * Theme
         * ----------------------------------------*/
        if (Security::database_is_ready() && Helper::isSuperAdmin()) {
            $fontsConfig = GridFieldConfig_RecordEditor::create(50);
            $fontsConfig->addComponent(GridFieldOrderableRows::create('SortOrder'));
            $fontsConfig->removeComponentsByType(GridFieldDeleteAction::class);

            $fontsField = GridField::create(
                'ThemeFonts',
                'Theme Fonts',
                $this->owner->ThemeFonts(),
                $fontsConfig
            );

            // if Root.Customization doesn't exist, create it
            if (!$fields->fieldByName('Root.Customization')) {
                $fields->addFieldToTab('Root', TabSet::create('Customization'));
            }

            // Remove the cache field
            $fields->removeByName('ThemeFontCache');

            $fields->addFieldsToTab('Root.Customization.FontFamilies', [
                TextareaField::create('ThemeFontLinks', 'Font Links')
                    ->setDescription('Paste only the href value, for example <code>https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap</code>.'),
                $fontsField,
            ]);
        }
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if($this->owner->ID && !$this->owner->ThemeFonts()->exists()){
            $font = new ThemeFont();
            $font->requireDefaultRecords();
        }

        Helper::generateCSSFiles();
    }

    // On before write, update the preload cache
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->updatePreloadCache();
    }

    public function updatePreloadCache()
    {
        $html = '';

        // Preload the ThemeFonts
        $fonts = $this->owner->ThemeFontLinks;
        $fonts = preg_split('/\s+/', $fonts);
        foreach ($fonts as $font) {
            // Make sure the value is not empty
            if (!$font || empty($font)) continue;
            // Add the preload link
            $html .= '<link rel="preload" href="' . $font . '" as="font" type="font/woff2" crossorigin>';
        }

        // Preload the FontFiles
        $themeFonts = $this->owner->ThemeFonts();

        $processedUrls = [];

        foreach ($themeFonts as $themeFont) {
            $fontFiles = $themeFont->FontFiles();
            foreach ($fontFiles as $fontFile) {
                $uploadedFiles = $fontFile->ThemeFontFiles();
                foreach ($uploadedFiles as $uploadedFile) {
                    // Make sure the URL is not empty
                    if (!$uploadedFile->URL || empty($uploadedFile->URL)) continue;

                    // If this URL has already been processed, skip it
                    if (isset($processedUrls[$uploadedFile->URL])) continue;

                    // Extract the type from the URL (e.g. woff2)
                    $type = pathinfo($uploadedFile->URL, PATHINFO_EXTENSION);

                    // Add the preload link
                    $html .= '<link rel="preload" href="' . $uploadedFile->URL . '" as="font" type="font/' . $type . '" crossorigin onload="this.rel=\'stylesheet\'">';

                    // Mark this URL as processed
                    $processedUrls[$uploadedFile->URL] = true;
                }
            }
        }

        // Save the HTML to the database
        $this->owner->ThemeFontCache = $html;
    }

    public function getPreloadFonts()
    {
        // Return the cached HTML
        return $this->owner->ThemeFontCache;
    }
}
