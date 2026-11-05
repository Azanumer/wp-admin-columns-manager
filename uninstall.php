<?php
/**
 * Uninstall: remove the plugin's options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'wacm_columns' );
