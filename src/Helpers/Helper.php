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
            $fontFormats[] = [
                'title'          => $font->Title,
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

    static function generateCSSFiles()
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
                $themeCSSFilePath = $CSSFilePath . $styleID . '-theme-fonts.css';
                $editorCSSFilePath = $CSSFilePath . $styleID . '-editor-fonts.css';

                // Remove files if they exist
                if (file_exists($themeCSSFilePath)) unlink($themeCSSFilePath);
                if (file_exists($editorCSSFilePath)) unlink($editorCSSFilePath);

                // Create a new file
                $CSSVars = ':root {';

                // Loop through fonts and add CSS vars
                foreach ($fonts as $font) {
                    if ($font->FontFamily) {
                        $CSSVars .= '--' . $font->getFontFamilyClassName() . ': ' . $font->FontFamily || 'inherit' . ';';
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

                // Create a new file for the theme
                $themeStyles = $CSSVars;
                // Create a new file for the editor
                $editorStyles = $CSSVars;


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
