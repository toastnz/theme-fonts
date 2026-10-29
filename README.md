### Installation
------------

Requires Silverstripe CMS 6 (earlier releases of the module support Silverstripe 5).

    composer require toastnz/theme-fonts

Run `sake db:build --flush` afterwards. This creates the default font configs and generates the font files for every site.

#### Upgrading from an earlier version

Run `sake db:build --flush` then:

    sake tasks:theme-fonts-migrate --dry-run
    sake tasks:theme-fonts-migrate

The task copies legacy font family IDs into the `ThemeFontFamily` relation, publishes uploaded font files that were never published, creates missing default font configs on every site and rebuilds the generated files. It is safe to run more than once.

### Configuration
-------------

Add the following to your `config.yml` (optional) to generate default fonts on dev/build.
Default fonts are created for every site (and every subsite when `silverstripe/subsites` is installed).

```yaml
Toast\ThemeFonts\Models\ThemeFontFamily:
  default_fonts:
    - body
    - headings
    - comic-sans
  available_weights:
    - 100
    - 200
    - 300
    - 400
    - 500
    - 600
    - 700
    - 800
    - 900

SilverStripe\SiteConfig\SiteConfig:
  # where the generated files are written, relative to the project root
  css_folder_path: '/public/assets/styles/'
  # allow every ADMIN to manage fonts, not just the SS_DEFAULT_ADMIN_USERNAME user
  bypass_super_admin_check: true
```

### Usage
-------------

# Adding the required css and link tags to your template
```html
<head>
    // Your other code
    $ThemeFonts.RAW
    // Your other code
</head>
```

# Editor styles and TinyMCE
A `{mainsite|subsite-ID}-editor-fonts.css` file is generated next to the site fonts file. The font menu for TinyMCE is available from `SiteConfigExtension::getFontFormatsForTinyMCE()` (or `$siteConfig->getThemeFontFormats()`).

### Font config classes
```getFontConfigKey()``` returns the default font name (e.g. `body`) or the record ID. It is used in the `--font-family-*` variables and the `font-family--*` css classes.

### Extending
-------------

Every public method of the module calls `extend()` so it can be customised from an extension. Hooks:

| Class | Hooks |
| --- | --- |
| SiteConfig | `updateIsSuperAdmin`, `updateFontsDisplayFieldsForCMS`, `updateThemeFontsFileKey`, `updateThemeFontsFolderPath`, `updateExtractedHrefUrls`, `updateFontLinks`, `updateFontImports`, `updateFontFaceCSS`, `updateFontCSSVars`, `updateThemeFontSiteStyles`, `updateThemeFontEditorStyles`, `onAfterGenerateThemeFontFiles`, `onAfterRequireDefaultThemeFontConfigs`, `updateThemeFontFormats` |
| PageController | `updateThemeFonts` |
| ThemeFontFamily | `updateFontFilesArray`, `updateFontWeightOptions`, `updateAvailableWeights`, `updateFontFaceDisplayFields`, `onAfterPublishFontFiles`, `onAfterUpdateFontFamily`, `updateDefaultFontKeys`, `updateFontFamilyArray` |
| ThemeFontConfig | `updateFontFamily` |
| ThemeFontFaceConfig | `updateFontFamily`, `updateFontSrc`, `updateFontFaceCSS` |
| MigrateThemeFontsTask | `onAfterMigrateFamilyIDs`, `onAfterMigrateThemeFonts` |
