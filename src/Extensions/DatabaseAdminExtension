<?php

namespace Toast\ThemeFonts\Extensions;

use SilverStripe\Control\Director;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Security;
use Toast\ThemeFonts\Helpers\Helper;

class DatabaseAdminExtension extends DataExtension
{
    public function onAfterBuild()
    {
         //generate all the required css files by theme fonts
         if (Security::database_is_ready()) {
            // theme button
            if (Helper::getCurrentSiteConfig()) Helper::generateCSSFiles();
        }
    }
}
