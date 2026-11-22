<?php
/**
 * Uninstall: remove plugin options. Kirki designs (kirki_template posts) are
 * left in place because they belong to the site's Kirki content.
 *
 * @package KirkiWoo
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'kirki_woo_settings' );
delete_option( 'kirki_woo_templates' );
