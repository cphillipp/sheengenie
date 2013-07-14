<?php
/* 
 * Plugins Garbage Collector WordPress plugin AJAX request processing staff
 * Author: Vladimir Garagulya vladimir@shinephp.com
 *
 */

//define('PGC_DEBUG_STEP', 1);

if (defined('PGC_DEBUG_STEP') && PGC_DEBUG_STEP==1) {
	echo '<pgc>debug step 1</pgc>';
	return;
}

require_once('../../../wp-config.php');

// check security
check_ajax_referer( "plugins-garbage-collector" );

if (!isset($_POST['action'])) {
  echo '<pgc>error: wrong action</pgc>';
  return;
}
require_once('pgc-lib.php');

$action = $_POST['action'];
if ($action=='scandbtables') {
  if (!isset($_POST['search_criteria']) || ($_POST['search_criteria']!=1 && $_POST['search_criteria']!=2)) {
    echo '<pgc>error: wrong search criteria value</pgc>';
    return;
  }

  $searchCriteria = $_POST['search_criteria'];

  require_once(ABSPATH.'wp-admin/includes/plugin.php');
  require_once(ABSPATH.'wp-admin/includes/upgrade.php');

  $result = '';
  if ($searchCriteria==1) {  // search none WP tables

		if (defined('PGC_DEBUG_STEP') && PGC_DEBUG_STEP==2) {
			echo '<pgc>debug step 2</pgc>';
			return;
		}

		$tables = pgc_getNotWordPressTables();
		
		if (defined('PGC_DEBUG_STEP') && PGC_DEBUG_STEP==3) {
			echo '<pgc>debug step 3</pgc>';
			return;
		}
		
    pgc_scanPluginsForDbTablesUse($tables);
		
		if (defined('PGC_DEBUG_STEP') && PGC_DEBUG_STEP==4) {
			echo '<pgc>debug step 4</pgc>';
			return;
		}
		
    $result = pgc_showTables($tables);
		
		if (defined('PGC_DEBUG_STEP') && PGC_DEBUG_STEP==5) {
			echo '<pgc>debug step 5</pgc>';
			return;
		}
		
  } else if ($searchCriteria==2) {  // check WP tables structure
    $result .= pgc_checkWpTablesStructure();
  } else {
    $result = 'error: wrong search criteria value';
  }
  echo '<pgc>'.$result.'</pgc>';
} else if ($action=='showprogress') {
  $total = get_option('pgc_scanprogress_total', 0);
  $current = get_option('pgc_scanprogress_current', 0);
  $status = get_option('pgc_scanprogress_status', 'start scanning...');
  if ($total==0 && $current==0) {
    echo '<pgc>100</pgc>';
  } else {
    echo '<pgc>'.$total.'&'.$current.'&'.$status.'</pgc>';
  }
} else if ($action=='hidetable') {
  if (!isset($_POST['table_name']) || !$_POST['table_name']) {
    echo '<pgc>error: Wrong request - required parameter table_name is missed.</pgc>';
  } else {
    $pgc_settings = get_option('pgc_settings');
    if (!$pgc_settings) {
      $pgc_settings = array();
      $pgc_settings['hidden'] = array();
    }
    $pgc_settings['hidden'][$_POST['table_name']] = 1;
    update_option('pgc_settings', $pgc_settings);
    echo '<pgc>OK</pgc>';    
  }
} else if ($action=='showtable') {
  if (!isset($_POST['table_name']) || !$_POST['table_name']) {
    echo '<pgc>error: Wrong request - required parameter table_name is missed.</pgc>';
  } else {
    $pgc_settings = get_option('pgc_settings');
    if (!$pgc_settings) {
      $pgc_settings = array();
      $pgc_settings['hidden'] = array();
    } else if (isset($pgc_settings['hidden'][$_POST['table_name']])) {
      unset($pgc_settings['hidden'][$_POST['table_name']]);
    }
    update_option('pgc_settings', $pgc_settings);
    echo '<pgc>OK</pgc>';
  }
} else {
  echo '<pgc>error: unknown action '.$action,'</pgc>';
}

?>
