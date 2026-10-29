<?php

namespace Toast\ThemeFonts\Extensions;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Core\Extension;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Exposes `$ThemeFonts` to templates.
 *
 * @extends Extension<ContentController&static>
 */
class PageControllerExtension extends Extension
{
    /**
     * Returns the preconnect tags, font links and font styles for the current (sub)site.
     * Output is raw HTML, use as `$ThemeFonts.RAW` in the template `<head>`.
     */
    public function getThemeFonts(): string
    {
        // current_site_config() is subsite aware when silverstripe/subsites is installed
        /** @var SiteConfig $siteConfig */
        $siteConfig = SiteConfig::current_site_config();
        $filePath = $siteConfig->getThemeFontsSiteFilePath();
        $html = '';

        if (is_file($filePath)) {
            if ($siteConfig->ThemeFontPreconnects) {
                $html .= $siteConfig->ThemeFontPreconnects;
            }

            $html .= (string) file_get_contents($filePath);
        }

        $this->getOwner()->extend('updateThemeFonts', $html, $siteConfig);

        return $html;
    }
}
