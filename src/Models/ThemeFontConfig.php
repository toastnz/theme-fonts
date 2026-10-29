<?php

namespace Toast\ThemeFonts\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * A named font slot (e.g. body, headings) that points to a ThemeFontFamily. Belongs to one SiteConfig.
 *
 * @property string $Title
 * @property string $FontConfigID
 * @property string $BoldFontWeight
 * @property int $SortOrder
 * @property int $ThemeFontFamilyID
 * @method ThemeFontFamily ThemeFontFamily()
 */
class ThemeFontConfig extends DataObject
{
    private static string $table_name = 'ThemeFontConfig';

    private static string $singular_name = 'Font Config';

    private static string $plural_name = 'Font Configs';

    private static array $db = [
        'Title' => 'Varchar(255)',
        'FontConfigID' => 'Varchar(255)',
        'BoldFontWeight' => 'Varchar(64)',
        'SortOrder' => 'Int',
    ];

    private static array $has_one = [
        'ThemeFontFamily' => ThemeFontFamily::class,
    ];

    private static array $belongs_many_many = [
        'SiteConfig' => SiteConfig::class . '.ThemeFontConfigs',
    ];

    private static array $summary_fields = [
        'Title' => 'Title',
        'ThemeFontFamily.FontFamily' => 'Font Family',
    ];

    private static string $default_sort = 'SortOrder ASC, ID ASC';

    /**
     * Names of the font configs created for every site, taken from the `default_fonts` config of
     * ThemeFontFamily. Entries can be plain strings or single key maps (`- body: null`).
     *
     * @return string[]
     */
    public static function getDefaultFontKeys(): array
    {
        $keys = [];

        foreach (ThemeFontFamily::config()->get('default_fonts') ?: [] as $index => $entry) {
            if (is_array($entry)) {
                $entry = array_key_first($entry);
            } elseif (!is_int($index)) {
                $entry = $index;
            }

            if (is_string($entry) && $entry !== '') {
                $keys[] = $entry;
            }
        }

        $keys = array_values(array_unique($keys));

        ThemeFontFamily::singleton()->extend('updateDefaultFontKeys', $keys);

        return $keys;
    }

    /**
     * ID => title options for a font family dropdown. Scoped to the given site when provided,
     * so subsites never see each other's families.
     *
     * @return array<int, string>
     */
    public static function getFontFamilyArray(?SiteConfig $siteConfig = null): array
    {
        $families = $siteConfig ? $siteConfig->ThemeFontFamilies() : ThemeFontFamily::get();
        $options = [];

        foreach ($families as $family) {
            $options[$family->ID] = ThemeFontFamily::formatTitle((string) $family->Title);
        }

        ThemeFontFamily::singleton()->extend('updateFontFamilyArray', $options, $siteConfig);

        return $options;
    }

    /**
     * Default fonts are created by the module and can't be deleted or renamed
     */
    public function isDefaultFont(): bool
    {
        return (bool) $this->FontConfigID;
    }

    /**
     * The identifier used in CSS variable / class names
     */
    public function getFontConfigKey(): string
    {
        return (string) ($this->FontConfigID ?: $this->ID);
    }

    /**
     * The font-family CSS value of the assigned family. Always in sync with the family record.
     */
    public function getFontFamily(): string
    {
        $family = $this->ThemeFontFamily();
        $value = $family->exists() ? (string) $family->FontFamily : '';

        $this->extend('updateFontFamily', $value);

        return $value;
    }

    /**
     * @deprecated Kept for templates / code written before the `FontFamilyID` column was merged into
     * the `ThemeFontFamily` relation
     */
    public function getFontFamilyID(): int
    {
        return (int) $this->ThemeFontFamilyID;
    }

    /**
     * @param Member|null $member
     */
    public function canDelete($member = null)
    {
        if ($this->isDefaultFont()) {
            return false;
        }

        return parent::canDelete($member);
    }
}
