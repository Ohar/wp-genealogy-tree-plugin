<?php
/**
 * Loads the Drevo Genealogy Trees plugin as a must-use feature.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$drevo_genealogy_plugins = array(
	WP_PLUGIN_DIR . '/drevo-genealogy/drevo-genealogy.php',
	WP_PLUGIN_DIR . '/wp-genealogy-tree-plugin/drevo-genealogy.php',
);

foreach ( $drevo_genealogy_plugins as $drevo_genealogy_plugin ) {
	if ( file_exists( $drevo_genealogy_plugin ) ) {
		require_once $drevo_genealogy_plugin;
		break;
	}
}
