<?php
/**
 * CloudScale Code Block — Uninstall
 *
 * Removes all plugin data when the plugin is deleted from the WordPress admin.
 * This file is called automatically by WordPress on plugin deletion.
 *
 * @package CloudScale_Code_Block
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'cs_code_default_theme' );
delete_option( 'cs_code_theme_pair' );
