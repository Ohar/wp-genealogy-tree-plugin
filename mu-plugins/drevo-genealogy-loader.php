<?php
/**
 * Loads the Drevo Genealogy Trees plugin as a must-use feature.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$drevo_genealogy_plugin = WP_PLUGIN_DIR . '/drevo-genealogy/drevo-genealogy.php';

if ( file_exists( $drevo_genealogy_plugin ) ) {
	require_once $drevo_genealogy_plugin;
}
