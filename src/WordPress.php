<?php

namespace ResursBank;

/**
 * Class WordPress WordPress related actions.
 * @package ResursBank
 * @since 0.0.1.0
 */
class WordPress
{
    public static function initializePage()
    {
        self::setupFilters();
        self::setupScripts();
    }

    private static function setupFilters()
    {
    }

    private static function setupScripts()
    {
        add_action('wp_enqueue_scripts', 'ResursBank\WordPress::setResursBankScripts');
        add_action('admin_enqueue_scripts', 'ResursBank\WordPress::setResursBankScriptsAdmin');
    }

    /**
     * @param bool $isAdmin
     */
    public static function setResursBankScripts($isAdmin = false)
    {
        foreach (Data::getPluginStyles($isAdmin) as $styleName => $styleFile) {
            wp_enqueue_style(
                sprintf('%s-%s', Data::getPrefix(), $styleName),
                sprintf(
                    '%s/css/%s?%s',
                    Data::getGatewayPath(),
                    $styleFile,
                    isResursTest() ? time() : 'static'
                ),
                [],
                Data::getCurrentVersion()
            );
        }

        foreach (Data::getPluginScripts($isAdmin) as $scriptName => $scriptFile) {
            wp_enqueue_script(
                sprintf('%s-%s', Data::getPrefix(), $scriptName),
                sprintf(
                    '%s/js/%s?%s',
                    Data::getGatewayPath(),
                    $scriptFile,
                    isResursTest() ? Data::getPrefix() . '-' . time() : 'static'
                ),
                Data::getJsDependencies($scriptName, $isAdmin)
            );
        }
    }

    public static function setResursBankScriptsAdmin()
    {

    }
}
