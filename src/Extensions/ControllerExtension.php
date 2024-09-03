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

class ControllerExtension extends Extension
{

    public function getThemeFonts() {
        $fonts = null;

        // Grab the SiteConfig
        if($siteConfig =  Helper::getCurrentSiteConfig()){
            $siteID = $siteConfig->ID;

            // Get the theme ID / Name
            $theme = ($siteID == 1) ? 'mainsite' : 'subsite-' . $siteID;
            $folderPath = Config::inst()->get(SiteConfig::class, 'css_folder_path');
            $fonts = $folderPath . $theme . '-theme-fonts.html';
            $baseFolder = Director::baseFolder();

            if ($fonts){
                if (!file_exists($baseFolder . $fonts)){
                    $result = Helper::generateRequiredFiles($fonts);
                }

                if (file_exists($baseFolder . $fonts)) {
                    $html = '';

                    // Check if the siteconfig ThemeFontPreconnects field has been set
                    if ($siteConfig->ThemeFontPreconnects) {
                        $html .= $siteConfig->ThemeFontPreconnects;
                    }

                    // Add the fonts html
                    $html .= file_get_contents($baseFolder . $fonts);

                    // Return the html
                    return $html;
                }
            }
        }
    }
}
