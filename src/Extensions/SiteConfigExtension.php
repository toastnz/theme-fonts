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
        'ThemeFontsLinks' => 'HTMLText',
        'ThemeFontsImports' => 'HTMLText',
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

            $fields->addFieldsToTab('Root.Customization.FontFamilies', [
                LiteralField::create('FontsImportWarning', '<div class="message warning"><strong>Please Note:</strong> For better performance it is recommended to use the Font Links rather than Font Imports (only one is required).</div>'),
                TextareaField::create('ThemeFontsLinks', 'Font Links')
                    ->setDescription('Paste the links to the fonts you want to use. Eg: <code>&lt;link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet"&gt;</code>'),
                TextareaField::create('ThemeFontsImports', 'Font Imports')
                    ->setDescription('Paste the links to the fonts you want to use. Eg: <code>@import url(\'https://fonts.googleapis.com/css2?family=\');</code>'),
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
}
