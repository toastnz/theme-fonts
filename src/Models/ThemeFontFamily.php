<?php

namespace Toast\ThemeFonts\Models;

use SilverStripe\Assets\File;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\CompositeValidator;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;

/**
 * A font family (e.g. `"Roboto", sans-serif`) and the uploaded font files / @font-face configuration
 * for it. Belongs to one or more SiteConfigs.
 *
 * @property int $SortOrder
 * @property string $Title
 * @property string $FontFamily
 * @method \SilverStripe\ORM\ManyManyList<File> ThemeFontFiles()
 * @method \SilverStripe\ORM\HasManyList<ThemeFontFaceConfig> ThemeFontFaceConfigs()
 * @method \SilverStripe\ORM\HasManyList<ThemeFontConfig> ThemeFontConfigs()
 * @method \SilverStripe\ORM\ManyManyList<SiteConfig> SiteConfig()
 */
class ThemeFontFamily extends DataObject
{
    private static string $table_name = 'ThemeFontFamily';

    private static string $singular_name = 'Font Family';

    private static string $plural_name = 'Font Families';

    /**
     * Font configs created for every site on dev/build. Entries are strings or single key maps.
     */
    private static array $default_fonts = [
        'body',
        'headings',
    ];

    /**
     * Weights that can be selected for an uploaded font file
     */
    private static array $available_weights = [
        400,
        700,
    ];

    /**
     * Names for the weights, only the weights in `available_weights` are shown
     */
    private static array $weight_titles = [
        '100' => 'Extra Light',
        '200' => 'Light',
        '300' => 'Book',
        '400' => 'Regular',
        '500' => 'Medium',
        '600' => 'Semi Bold',
        '700' => 'Bold',
        '800' => 'Extra Bold',
        '900' => 'Black',
    ];

    private static array $db = [
        'SortOrder' => 'Int',
        'Title' => 'Varchar(255)',
        'FontFamily' => 'Varchar(255)',
    ];

    private static array $many_many = [
        'ThemeFontFiles' => File::class,
    ];

    private static array $has_many = [
        'ThemeFontFaceConfigs' => ThemeFontFaceConfig::class . '.ThemeFontFamily',
        'ThemeFontConfigs' => ThemeFontConfig::class . '.ThemeFontFamily',
    ];

    private static array $belongs_many_many = [
        'SiteConfig' => SiteConfig::class . '.ThemeFontFamilies',
    ];

    private static array $summary_fields = [
        'Title' => 'Title',
        'FontFamily' => 'Font Family',
    ];

    private static string $default_sort = 'SortOrder ASC, ID ASC';

    /**
     * Turn a file / family title like `roboto_BoldItalic` into `Roboto Bold Italic`
     */
    public static function formatTitle(string $title): string
    {
        $title = str_replace(['-', '_'], ' ', $title);
        $title = preg_replace('/([a-z])([A-Z])/', '$1 $2', $title);

        return ucwords((string) $title);
    }

    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields): void {
            $fields->removeByName([
                'SortOrder',
                'SiteConfig',
                'ThemeFontFiles',
                'ThemeFontFaceConfigs',
                'ThemeFontConfigs',
            ]);

            $fields->addFieldsToTab('Root.Main', [
                TextField::create('Title', 'Title')
                    ->setDescription('For your reference only'),
                TextField::create('FontFamily', 'Font Family')
                    ->setDescription('Enter the font-family css value, ideally with fallback system fonts. e.g <code>"Roboto", sans-serif;</code>'),
                LiteralField::create('FontFilesInfo', '<div class="alert alert-warning">Fonts can be uploaded and configured in the Files tab.</div>'),
            ]);

            $fields->addFieldsToTab('Root.Files', [
                LiteralField::create('FontConfigurationInfo', '<div class="alert alert-warning">Configuration is needed for uploaded fonts. Save after uploading your fonts to configure them below.</div>'),
                UploadField::create('ThemeFontFiles', 'Font Files')
                    ->setAllowedMaxFileNumber(null)
                    ->setFolderName('Uploads/ThemeFonts')
                    ->setAllowedExtensions(['woff', 'woff2', 'ttf', 'otf', 'eot', 'svg'])
                    ->setDescription('Font files are required if the font cannot be loaded through a cdn like Google or Adobe Fonts. Font files will be required for each font weight and style. e.g. If you require Roboto bold, and Roboto bold italic, you will need to upload these as 2 separate font files.'),
            ]);

            // Add the configuration field once the font files have been uploaded
            if ($this->isInDB() && $this->ThemeFontFiles()->exists()) {
                $fields->addFieldToTab('Root.Files', $this->getFontFaceConfigField());
            }
        });

        return parent::getCMSFields();
    }

    public function getCMSCompositeValidator(): CompositeValidator
    {
        $validator = parent::getCMSCompositeValidator();
        $validator->addValidator(RequiredFieldsValidator::create(['Title', 'FontFamily']));

        return $validator;
    }

    /**
     * The inline editor for this family's @font-face configurations
     */
    public function getFontFaceConfigField(): GridField
    {
        $config = GridFieldConfig::create()
            ->addComponent(GridFieldButtonRow::create('before'))
            ->addComponent(GridFieldToolbarHeader::create())
            ->addComponent(GridFieldTitleHeader::create())
            ->addComponent($editableColumns = GridFieldEditableColumns::create())
            ->addComponent(GridFieldDeleteAction::create())
            ->addComponent(GridFieldOrderableRows::create('SortOrder'))
            ->addComponent($addNewButton = GridFieldAddNewInlineButton::create());

        $addNewButton->setTitle('Add Configuration');

        $fileOptions = $this->getFontFilesArray();
        $weightOptions = $this->getFontWeightOptions();

        $displayFields = [
            'FontFileID' => [
                'title' => 'This Font File',
                'callback' => static function ($record, string $column) use ($fileOptions) {
                    return DropdownField::create($column)
                        ->setEmptyString('None')
                        ->setSource($fileOptions);
                },
            ],
            'FontWeight' => [
                'title' => 'Applies to font weight',
                'callback' => static function ($record, string $column) use ($weightOptions) {
                    return DropdownField::create($column)->setSource($weightOptions);
                },
            ],
            'FontStyle' => [
                'title' => 'When font style is',
                'callback' => static function ($record, string $column) {
                    return DropdownField::create($column)->setSource([
                        'normal' => 'Normal',
                        'italic' => 'Italic',
                    ]);
                },
            ],
        ];

        $this->extend('updateFontFaceDisplayFields', $displayFields);
        $editableColumns->setDisplayFields($displayFields);

        return GridField::create('ThemeFontFaceConfigs', 'Configuration', $this->ThemeFontFaceConfigs(), $config);
    }

    /**
     * ID => title options of the uploaded font files
     *
     * @return array<int, string>
     */
    public function getFontFilesArray(): array
    {
        $fontFiles = [];

        foreach ($this->ThemeFontFiles() as $file) {
            $fontFiles[$file->ID] = static::formatTitle((string) $file->Title);
        }

        $this->extend('updateFontFilesArray', $fontFiles);

        return $fontFiles;
    }

    /**
     * Weight => title options limited to the `available_weights` config
     *
     * @return array<string, string>
     */
    public function getFontWeightOptions(): array
    {
        $available = array_map('strval', $this->getAvailableWeights());
        $options = array_intersect_key($this->config()->get('weight_titles') ?: [], array_flip($available));

        $this->extend('updateFontWeightOptions', $options);

        return $options;
    }

    /**
     * @return array<int|string>
     */
    public function getAvailableWeights(): array
    {
        $weights = $this->config()->get('available_weights') ?: [];

        $this->extend('updateAvailableWeights', $weights);

        return $weights;
    }

    /**
     * Publish the uploaded font files. Files are draft after upload and draft files are only
     * served to logged in CMS users, so fonts would never load for visitors.
     */
    public function publishFontFiles(): void
    {
        foreach ($this->ThemeFontFiles() as $file) {
            if (!$file->isPublished()) {
                $file->publishSingle();
            }
        }

        $this->extend('onAfterPublishFontFiles');
    }

    /**
     * Rebuild the generated font files of each site using this family
     */
    public function updateFontFamily(): void
    {
        $this->publishFontFiles();

        foreach ($this->SiteConfig() as $siteConfig) {
            $siteConfig->generateThemeFontFiles();
        }

        $this->extend('onAfterUpdateFontFamily');
    }

    protected function onAfterWrite(): void
    {
        parent::onAfterWrite();

        $this->updateFontFamily();
    }

    /**
     * Writes where nothing on the family changed (e.g. only the inline face configurations were
     * saved) don't call onAfterWrite
     */
    protected function onAfterSkippedWrite(): void
    {
        $this->updateFontFamily();
    }
}
