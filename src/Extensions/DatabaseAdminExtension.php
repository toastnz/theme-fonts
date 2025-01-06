<?php

namespace Toast\ThemeFonts\Extensions;

use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Extension;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;

class DatabaseAdminExtension extends Extension
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
