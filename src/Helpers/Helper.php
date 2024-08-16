<?php

namespace Toast\ThemeFonts\Helpers;

use SilverStripe\Core\Environment;
use SilverStripe\Security\Security;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Config\Config;
use DirectoryIterator;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;

class Helper
{
    static function isSuperAdmin()
    {
         if ($defaultUser = Environment::getEnv('SS_DEFAULT_ADMIN_USERNAME')) {
            if ($currentUser = Security::getCurrentUser()) {
                $allowed = false;
                // all toast email owner is a superadmin
                if($currentUser->Email == $defaultUser || strstr($currentUser->Email, '@toast.co.nz')){
                    $allowed = true;
                }

               // extend this method
                $currentUser->extend('updateSuperAdmin', $allowed);

                return $allowed;
            }
        }
        return false;
    }

    static function getThemeFontsArray($id = null)
    {
        $array=[];

        $siteConfig = $id ? SiteConfig::get()->byID($id) : SiteConfig::current_site_config();

        if ($fonts = $siteConfig->ThemeFonts()){
            foreach($fonts as $font){
                // Add the font to the array
                $array[$font->getFontFamilyClassName()] = $font;
            }
        }

        return $array;
    }

    static function getFontFormatsForTinyMCE()
    {
        $fonts = self::getThemeFontsArray();

        $formats = [];
        $fontFormats = [];

        // get current fonts
        foreach ($fonts as $font) {
            // Make sure there is a font family before adding it to the array
            if (!$font->FontFamily) continue;
            // Add the font to the array

            // Grab the title and make it title case
            $title = $font->Title;
            $title = ucwords($title);

            $fontFormats[] = [
                'title'          => 'Font Family / ' . $title,
                'selector'       => '*',
                'classes'        => 'font-family--' . $font->FontFamilyClassName,
                'wrapper'        => true,
                'merge_siblings' => true,
            ];
        }

        $formats[] = [
            'title' => 'Font Family',
            'items' => $fontFormats,
        ];

        return $formats;
    }

    static function extractHrefUrls($links) {
        $urls = [];
        $pattern = '/<link[^>]+href="([^"]+)"[^>]*>/i';

        preg_match_all($pattern, $links, $matches);

        if (!empty($matches[1])) {
            $urls = $matches[1];
        }

        return $urls;
    }

    static function getFontLinks($siteConfig) {
        $html = '';

        // Preload the ThemeFonts
        $fonts = $siteConfig->ThemeFontLinks;
        $fonts = self::extractHrefUrls($fonts);
        $lastIndex = count($fonts) - 1;
        $fontsAdded = false;

        foreach ($fonts as $index => $font) {
            // Make sure the value is not empty
            if (!$font || empty($font)) continue;

            // Set the common onload attribute
            $onload = 'this.onload=null;this.rel=\'stylesheet\'';

            // Add the preload link
            if ($index === $lastIndex) {
                // For the last font, add an onload event that adds a 'fonts-loaded' class to the document.body
                $onload .= ';document.documentElement.classList.add(\'fonts-loaded\')';
            }

            $html .= '<link rel="preload" href="' . $font . '" as="style" onload="' . $onload . '">';
            $fontsAdded = true;
        }

        // If no fonts were loaded, add a script tag that adds the 'fonts-loaded' class to the document.body
        if (!$fontsAdded) {
            $html .= '<script>document.documentElement.classList.add(\'fonts-loaded\');</script>';
        }

        // Preload the FontFiles
        $themeFonts = $siteConfig->ThemeFonts();

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
                    $html .= '<link rel="preload" href="' . $uploadedFile->URL . '" as="font" type="font/' . $type . '" crossorigin>';

                    // Mark this URL as processed
                    $processedUrls[$uploadedFile->URL] = true;
                }
            }
        }

        return $html;
    }

    static function getFontImports($siteConfig) {
        $css = '';

        // Import the ThemeFonts
        $fonts = $siteConfig->ThemeFontLinks;
        $fonts = self::extractHrefUrls($fonts);

        foreach ($fonts as $font) {
            // Make sure the value is not empty
            if (!$font || empty($font)) continue;

            // Add the @import statement
            $css .= '@import url("' . $font . '");' . PHP_EOL;
        }

        // Import the FontFiles
        $themeFonts = $siteConfig->ThemeFonts();

        foreach ($themeFonts as $themeFont) {
            $fontFiles = $themeFont->FontFiles();
            foreach ($fontFiles as $fontFile) {
                $uploadedFiles = $fontFile->ThemeFontFiles();
                foreach ($uploadedFiles as $uploadedFile) {
                    // Make sure the URL is not empty
                    if (!$uploadedFile->URL || empty($uploadedFile->URL)) continue;

                    // Add the @import statement for the font file
                    $css .= '@import url("' . $uploadedFile->URL . '");' . PHP_EOL;
                }
            }
        }

        return $css;
    }

    static function generateRequiredFiles()
    {
        // Get the current site's config
        if ($siteConfig = self::getCurrentSiteConfig()){
            // Get the site' ID and append to the css file name
            $styleID = ($siteConfig->ID == 1) ? 'mainsite' : 'subsite-' . $siteConfig->ID;
            // Get the site's fonts
            $fonts = $siteConfig->ThemeFonts();
            // If we have fonts
            if ($fonts) {
                 //get folder path from config
                $folderPath = Config::inst()->get(SiteConfig::class, 'css_folder_path');
                // if folder doesnt exist, create it
                if (!file_exists(Director::baseFolder() . $folderPath)) {
                    mkdir(Director::baseFolder() . $folderPath, 0777, true);
                }
                $CSSFilePath = Director::baseFolder() . $folderPath;
                $themeCSSFilePath = $CSSFilePath . $styleID . '-theme-fonts.html';
                $editorCSSFilePath = $CSSFilePath . $styleID . '-editor-fonts.css';

                // Remove files if they exist
                if (file_exists($themeCSSFilePath)) unlink($themeCSSFilePath);
                if (file_exists($editorCSSFilePath)) unlink($editorCSSFilePath);

                // Create a new file
                $CSSVars = ':root {';

                // Loop through fonts and add CSS vars
                foreach ($fonts as $font) {
                    if ($font->FontFamily) {
                        // Trim any trailing spacing from the font family
                        $family = trim($font->FontFamily);
                        // Remove any ; at the end of the string
                        $family = rtrim($family, ';');
                        // Add the CSS var
                        $CSSVars .= '--' . $font->getFontFamilyClassName() . ': ' . $family . ';';
                    }
                }
                // Close the file
                $CSSVars .= '}';

                // If the ThemeFontsLinks field is empty
                if (!$siteConfig->ThemeFontsLinks) {
                    // Load the theme's fonts imports to the file
                    if ($siteConfig->ThemeFontsImports) {
                        $CSSVars .= $siteConfig->ThemeFontsImports;
                    }
                }

                // Get the font links and add them to the theme styles
                $themeStyles = self::getFontLinks($siteConfig);
                $editorStyles = self::getFontImports($siteConfig);

                // Create a new file for the theme
                $themeStyles .= '<style>';
                $themeStyles .= $CSSVars;
                // Create a new file for the editor
                $editorStyles .= $CSSVars;


                // Loop through fonts and add styles
                foreach ($fonts as $font) {
                    if ($font->FontFiles()) {
                        foreach ($font->FontFiles() as $fontFile) {
                            $themeStyles .= $fontFile->getFontFaceCSS();
                            $editorStyles .= $fontFile->getFontFaceCSS();
                        }
                    }

                    if ($font->FontFamily) {
                        $className = $font->getFontFamilyClassName();
                        // Theme styles
                        $themeStyles .= '.font-family--' . $className . '{';
                        $themeStyles .= 'font-family: var(--' . $className . ');';
                        $themeStyles .= '}';

                        // Editor styles
                        $editorStyles .= 'body.mce-content-body  .font-family--' . $className . '{';
                        $editorStyles .= 'font-family: var(--' . $className . ');';
                        $editorStyles .= '}';
                    }
                }

                // Close the file
                $themeStyles .= '</style>';

                // Write to file
                try {
                    file_put_contents($themeCSSFilePath, $themeStyles);
                    file_put_contents($editorCSSFilePath, $editorStyles);
                } catch (\Exception $e) {
                    // Do nothing
                }
            }
        }
    }

    static function getCurrentSiteConfig()
    {
        if($siteConfig = DataObject::get_one(SiteConfig::class)){
            return $siteConfig;
        }
        return;
    }
}
