<?php

namespace Toast\ThemeFonts\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Control\Director;
use SilverStripe\ORM\DataExtension;
use SilverStripe\View\Requirements;
use SilverStripe\Core\Config\Config;
use Toast\ThemeFonts\Helpers\Helper;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Manifest\ModuleResourceLoader;

class PageControllerExtension extends Extension
{
    // onbeforeinit
    public function onBeforeInit()
    {
        $themeCssFilePath = null;

        // Grab the SiteConfig
        if($siteConfig =  Helper::getCurrentSiteConfig()){
            $siteID = $siteConfig->ID;

            // Get the theme ID / Name
            $theme = ($siteID == 1) ? 'mainsite' : 'subsite-' . $siteID;
            $folderPath = Config::inst()->get(SiteConfig::class, 'css_folder_path');
            $themeCssFilePath = $folderPath . $theme . '-theme-fonts.css';

            if ($themeCssFilePath){
                if (!file_exists(Director::baseFolder() . $themeCssFilePath)){
                    $result = Helper::generateCSSFiles($themeCssFilePath);
                }

                if (file_exists(Director::baseFolder() . $themeCssFilePath)) {
                    Requirements::customCSS(file_get_contents(Director::baseFolder() .$themeCssFilePath));
                    // $cssFile = ModuleResourceLoader::resourceURL($themeCssFilePath);
                    // Requirements::css($cssFile);
                }
            }
        }
    }
}
