<?php

namespace Toast\ThemeFonts\Extensions;

use SilverStripe\Control\Director;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Security;
use Toast\ThemeFonts\Helpers\Helper;

class DatabaseAdminExtension extends DataExtension
{
    static function getCurrentSiteConfig()
    {
        if($siteConfig = DataObject::get_one(SiteConfig::class)){
            return $siteConfig;
        }
        return;
    }

    public function onAfterBuild()
    {
        $siteConfig = self::getCurrentSiteConfig();

        // Generate all the required css files by theme fonts
         if (Security::database_is_ready()) {
            $siteConfig->generateThemeFontFiles();
        }
    }
}
