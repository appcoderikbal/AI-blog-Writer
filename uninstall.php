<?php
/**
 * TechZapp AI Writer — Uninstall Script
 *
 * Fired when the plugin is deleted from the WordPress admin.
 *
 * @package TechZappAIWriter
 */

declare( strict_types=1 );

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
delete_option( 'tzaw_settings' );

// Remove transients.
delete_transient( 'tzaw_trending_topics' );
