<?php
/*
Plugin Name: Plugins Garbage Collector
Plugin URI: http://www.shinephp.com/plugins-garbage-collector-wordpress-plugin/
Description: It scans your WordPress database and shows what various things old plugins which were deactivated, uninstalled) left in it. The list of additional database tables used by plugins with quant of records, size, and plugin name is shown.
Version: 0.9.12
Author: Vladimir Garagulya
Author URI: http://www.shinephp.com
Text Domain: pgc
Domain Path: ./lang/
*/

/*
Copyright 2010  Vladimir Garagulya  (email: vladimir@shinephp.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


if (!function_exists("get_option")) {
  die;  // Silence is golden, direct call is prohibited
}

global $wp_version;

require_once('pgc-lib.php');

$pgc_plugin_name = __('Plugins Garbage Collector', 'pgc');

$exit_msg = $pgc_plugin_name.__(' requires WordPress 3.0 or newer.', 'pgc').' <a href="http://codex.wordpress.org/Upgrading_WordPress">'.__('Please update!', 'pgc').'</a>';
if (version_compare($wp_version,"3.0","<")) {
	return ($exit_msg);
}


/**
 * Load translation files - linked to the 'plugins_loaded' hook
 * 
 */
function pgc_load_translation() {

	load_plugin_textdomain( 'pgc', '', dirname( plugin_basename( __FILE__ ) ) . DIRECTORY_SEPARATOR .'lang' );
	
}
// end of pgc_load_translation()


function pgc_actionsPage() {
  
  global $wpdb, $pgc_plugin_name;

  if (!current_user_can('activate_plugins')) {
    die('action is forbidden');
  }
  
?>

<div class="wrap">
  <div class="icon32" id="icon-options-general"><br/></div>
    <h2><?php echo $pgc_plugin_name; ?></h2>
		<?php require ('pgc-options.php'); ?>
  </div>
<?php

}
// end of pgc_optionsPage()


// Install plugin
function pgc_install() {
	
  
}
// end of pgc_install()


function pgc_init() {

  if(function_exists('register_setting')) {
    register_setting('pgc-options', 'pgc_option');
  }
}
// end of pgc_init()


function pgc_plugin_action_links($links, $file) {
    if ($file == plugin_basename(dirname(__FILE__).'/plugins-garbage-collector.php')){
        $settings_link = "<a href='tools.php?page=plugins-garbage-collector.php'>".__('Scan','pgc')."</a>";
        array_unshift( $links, $settings_link );
    }
    return $links;
}
// end of pgc_plugin_action_links


function pgc_plugin_row_meta($links, $file) {
  if ($file == plugin_basename(dirname(__FILE__).'/plugins-garbage-collector.php')){
		$links[] = '<a target="_blank" href="http://www.shinephp.com/plugins-garbage-collector-wordpress-plugin/#changelog">'.__('Changelog', 'pgc').'</a>';
	}
	return $links;
} // end of pgc_plugin_row_meta


function pgc_tools_menu() {
  global $pgc_plugin_name;

	if ( function_exists('add_management_page') ) {
    $pgc_page = add_management_page($pgc_plugin_name, $pgc_plugin_name, 'create_users', basename(__FILE__), 'pgc_actionsPage');
		add_action("admin_print_styles-$pgc_page", 'pgc_adminCssAction');
    add_action("admin_print_scripts-$pgc_page", 'pgc_scriptsAction');
	}
}
// end of pgc_settings_menu()

function pgc_adminCssAction() {

  wp_enqueue_style('pgc_admin_css', PGC_PLUGIN_URL.'/css/pgc-admin.css', array(), false, 'screen');

}
// end of pgc_adminCssAction()

function pgc_scriptsAction() {

  wp_enqueue_script('pgc_js_script', PGC_PLUGIN_URL.'/pgc-ajax.js', array('jquery','jquery-form'));
  wp_localize_script('pgc_js_script', 'pgcSettings', array('plugin_url' => PGC_PLUGIN_URL, 'ajax_nonce' => wp_create_nonce('plugins-garbage-collector')));

}
// end of pgc_scriptsAction()


if (is_admin()) {
  // activation action
  register_activation_hook(__FILE__, "pgc_install");
	// load translation
	add_action('plugins_loaded', 'pgc_load_translation');
	
  add_action('admin_init', 'pgc_init');
  // add a Settings link in the installed plugins page
  add_filter('plugin_action_links', 'pgc_plugin_action_links', 10, 2);
  add_filter('plugin_row_meta', 'pgc_plugin_row_meta', 10, 2);
  add_action('admin_menu', 'pgc_tools_menu');
}




?>