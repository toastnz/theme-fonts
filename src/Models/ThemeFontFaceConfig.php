<?php

namespace Toast\ThemeFonts\Models;

use SilverStripe\Assets\File;
use SilverStripe\ORM\DataObject;

/**
 * One `@font-face` rule: a font file of a ThemeFontFamily with the weight / style it applies to.
 *
 * @property int $SortOrder
 * @property string $FontWeight
 * @property string $FontStyle
 * @property int $FontFileID
 * @property int $ThemeFontFamilyID
 * @method File FontFile()
 * @method ThemeFontFamily ThemeFontFamily()
 */
class ThemeFontFaceConfig extends DataObject
{
    private static string $table_name = 'ThemeFontFaceConfig';

    private static array $db = [
        'SortOrder' => 'Int',
        'FontWeight' => 'Enum("100,200,300,400,500,600,700,800,900", "400")',
        'FontStyle' => 'Enum("normal,italic", "normal")',
    ];

    private static array $has_one = [
        'ThemeFontFamily' => ThemeFontFamily::class,
        'FontFile' => File::class,
    ];

    private static array $summary_fields = [
        'FontWeight' => 'Font Weight',
        'FontStyle' => 'Font Style',
    ];

    private static string $default_sort = 'SortOrder ASC, ID ASC';

    /**
     * Mime type, @font-face format() hint, by lowercase file extension
     */
    private static array $font_types = [
        'woff2' => ['mime' => 'font/woff2', 'format' => 'woff2'],
        'woff' => ['mime' => 'font/woff', 'format' => 'woff'],
        'ttf' => ['mime' => 'font/ttf', 'format' => 'truetype'],
        'otf' => ['mime' => 'font/otf', 'format' => 'opentype'],
        'eot' => ['mime' => 'application/vnd.ms-fontobject', 'format' => 'embedded-opentype'],
        'svg' => ['mime' => 'image/svg+xml', 'format' => 'svg'],
    ];

    /**
     * The font-family CSS value of the parent family. Always in sync with the family record.
     */
    public function getFontFamily(): string
    {
        $family = $this->ThemeFontFamily();
        $value = $family->exists() ? (string) $family->FontFamily : '';

        $this->extend('updateFontFamily', $value);

        return $value;
    }

    /**
     * The font file, only if it is still one of the parent family's files and is published
     */
    public function getPublishedFontFile(): ?File
    {
        if (!$this->FontFileID || !$this->ThemeFontFamilyID) {
            return null;
        }

        /** @var File|null $file */
        $file = $this->ThemeFontFamily()->ThemeFontFiles()->byID($this->FontFileID);

        // Draft files are served from the protected assets area, never link them in public css
        if (!$file || !$file->exists() || !$file->isPublished()) {
            return null;
        }

        return $file;
    }

    public function getFontSrc(): string
    {
        $file = $this->getPublishedFontFile();
        $src = $file ? (string) $file->getURL() : '';

        $this->extend('updateFontSrc', $src);

        return $src;
    }

    public function getFontType(): string
    {
        $file = $this->getPublishedFontFile();

        return $file ? strtolower((string) $file->getExtension()) : '';
    }

    public function getFontMimeType(): string
    {
        $type = $this->getFontType();

        return $this->config()->get('font_types')[$type]['mime'] ?? 'font/' . $type;
    }

    public function getFontFaceCSS(): string
    {
        $src = $this->getFontSrc();
        $css = '';

        if ($src) {
            // Only the first family is used for the face, without any quotes
            $fontFamily = trim(explode(',', $this->getFontFamily())[0], " \t\n\r\0\x0B\"';");
            $format = $this->config()->get('font_types')[$this->getFontType()]['format'] ?? null;

            $css = '@font-face {';
            $css .= 'font-family: "' . $this->escapeCSSString($fontFamily) . '";';
            $css .= 'font-weight: ' . $this->FontWeight . ';';
            $css .= 'font-style: ' . $this->FontStyle . ';';
            $css .= 'font-display: swap;';
            $css .= 'src: url("' . $this->escapeCSSString($src) . '")' . ($format ? ' format("' . $format . '")' : '') . ';';
            $css .= '}';
        }

        $this->extend('updateFontFaceCSS', $css);

        return $css;
    }

    protected function escapeCSSString(string $value): string
    {
        return addcslashes(str_replace(["\r", "\n"], '', $value), '"\\');
    }
}
