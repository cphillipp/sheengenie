<?php
/* 
 * * User Role Editor plugin Lirary general staff
 * Author: Vladimir Garagulya vladimir@shinephp.com
 * 
 */


if (!defined("WPLANG")) {
  die;  // Silence is golden, direct call is prohibited
}

require_once(ABSPATH.WPINC.'/class-simplepie.php');

$pgc_siteURL = get_option( 'siteurl' );

$pgcPluginDirName = substr(strrchr(dirname(__FILE__), DIRECTORY_SEPARATOR), 1);

if (is_multisite()) {
// [alx359] new definition for Multisite support
  $pgc_plugin_url = (is_ssl() ? 'https://':'http://').$_SERVER['HTTP_HOST'].'/wp-content/plugins/'.$pgcPluginDirName;
} else {
  $pgc_plugin_url = WP_PLUGIN_URL.'/'.$pgcPluginDirName;
}

define('PGC_PLUGIN_URL', $pgc_plugin_url);
define('PGC_PLUGIN_DIR', WP_PLUGIN_DIR.'/'.$pgcPluginDirName);
define('PGC_WP_ADMIN_URL', $pgc_siteURL.'/wp-admin');
define('PGC_ERROR', 'Error is encountered');


global $wpdb;


function pgc_logEvent($message, $showMessage = false) {
  include(ABSPATH .'wp-includes/version.php');

  $fileName = PGC_PLUGIN_DIR.'/plugins-garbage-collector.log';
  $fh = fopen($fileName,'a');
  $cr = "\n";
  $s = $cr.date("d-m-Y H:i:s").$cr.
      'WordPress version: '.$wp_version.', PHP version: '.phpversion().', MySQL version: '.mysql_get_server_info().$cr;
  fwrite($fh, $s);
  fwrite($fh, $message.$cr);
  fclose($fh);

  if ($showMessage) {
    pgc_showMessage('Error is occur. Please check the log file.');
  }
}
// end of pgc_logEvent()


function pgc_showMessage($message) {

  if ($message) {
    echo '<div class="updated" style="margin:0;">'.$message.'</div><br style="clear: both;"/>';
  }

}
// end of pgc_showMessage()


function pgc_getNotWordPressTables() {
  global $wpdb;

  $wpTables = array('users', 'usermeta', 'posts', 'categories', 'post2cat', 'comments', 'links', 'link2cat', 'options',
                    'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'commentmeta', 'blogs', 'blog_versions',
                    'site', 'sitemeta');

  $non_wp_tables = array();
  $query = "show tables";
  $existingTables = $wpdb->get_col($query);
  foreach ($existingTables as $existingTable) {
    $existingTable1 = strtolower($existingTable);
    $legalWPTable = false;
    foreach ($wpTables as $wpTable) {
      if ($wpdb->prefix.$wpTable==$existingTable1) {
        $legalWPTable = true;
        break;
      }
    }
    if (!$legalWPTable && strpos($existingTable, $wpdb->prefix, 0)!==false) {
/*
      $query = "SELECT table_rows, ROUND((data_length + index_length)/1024,2) as kbytes
                  FROM information_schema.TABLES
                  WHERE information_schema.TABLES.table_schema='".DB_NAME."' and
                        information_schema.TABLES.table_name='$existingTable'
                  limit 0, 1";
*/
      $query = "SHOW TABLE STATUS FROM ".DB_NAME." LIKE '$existingTable'";  // MySQL 4+ compatible query
      $result = $wpdb->get_results($query);
      $non_wp_table = new stdClass;
      $non_wp_table->name = $existingTable1;
      $non_wp_table->name_without_prefix = strtolower(str_replace($wpdb->prefix, '', $existingTable));
      $non_wp_table->records = $result[0]->Rows; //$result[0]->table_rows;
      $non_wp_table->kbytes = ROUND(($result[0]->Data_length + $result[0]->Index_length)/1024,2); //$result[0]->kbytes;
      $non_wp_table->plugin_name = '';
      $non_wp_table->plugin_file = '';
      $non_wp_table->plugin_state = '';
      $non_wp_tables[] = $non_wp_table;
    }
  }

  $pgc_settings = get_option('pgc_settings');
  if (isset($pgc_settings['hidden']) &&  count($pgc_settings['hidden'])) {
    $updateSettingsNeeded = false;
    foreach ($pgc_settings['hidden'] as $tableName) {
      $found = false;
      foreach($non_wp_tables as $table) {
        if ($table->name==$tableName) {
          $found = true;
          break;
        }
      }
      if (!$found) {
        unset($pgc_settings['hidden'][$tableName]);
        $updateSettingsNeeded = true;
      }
    }
    if ($updateSettingsNeeded) {
      update_option('pgc_settings', $pgc_settings);
    }
  }

  return $non_wp_tables;
}
// end of pgc_getUnusedTables()


function pgc_scanPluginsForDbTablesUse(&$tables) {
  
  update_option('pgc_scanprogress_current', 1);
  update_option('pgc_scanprogress_status', 'Start scanning...');
  $plugins_list = get_plugins();
  update_option('pgc_scanprogress_total', count($plugins_list));
  
  $i = 1;
  foreach ($plugins_list as $key=>$plugin) {
    $i++;
    update_option('pgc_scanprogress_current', $i); 
    update_option('pgc_scanprogress_status', $plugin['Title']);
    $plugin_files = get_plugin_files($key);
    foreach ($plugin_files as $plugin_file) {
      $ext = pathinfo($plugin_file, PATHINFO_EXTENSION);
      if ($ext=='php' || $ext=='PHP') {
        $fh = fopen(WP_PLUGIN_DIR.'/'.$plugin_file, 'r');
        if (!$fh) {
          continue;
        }
        while (!feof($fh)) {
          $s = fgets($fh);
          $s = strtolower($s);
          foreach ($tables as $table) {
            if (!$table->plugin_name && (strpos($s, $table->name_without_prefix)!==false)) {
              $table->plugin_name = $plugin['Title'].' '.$plugin['Version'];
              $table->plugin_file = $key;
            }
          }
        }
        fclose($fh);
      }
    }
  }

  $active_plugins = get_option('active_plugins');
  foreach ($tables as $table) {
    if ($table->plugin_file) {
      $pluginActive = false;
      foreach ($active_plugins as $active_plugin) {
        if ($table->plugin_file==$active_plugin) {
          $pluginActive = true;
          break;
        }
      }
      if ($pluginActive) {
        $table->plugin_state = 'active';
      } else {
        $table->plugin_state = 'deactivated';
      }
    } else {
      $table->plugin_state = 'deleted?';
    }
    $table->plugin_state = $table->plugin_state;
  }
  delete_option('pgc_scanprogress_current');
  delete_option('pgc_scanprogress_total');
  delete_option('pgc_scanprogress_status');
}
// end of pgc_scanPluginsForDbTables()


function pgc_deleteUnusedTablesFromDB() {
  global $wpdb;

  $tablesToDelete = array();
  foreach ($_POST as $key=>$value) {
    if (strpos($key, 'delete_')!==false) {
      $tablesToDelete[] = substr($key, 7);
    }
  }
  if (count($tablesToDelete)==0) {
    return;
  }

  $dbName = DB_NAME;
  $actionResult = '';
  foreach($tablesToDelete as $tableToDelete) {
    if ($actionResult) {
      $actionResult .= ', ';
    }
    $query = "drop table `$dbName`.`$tableToDelete`";
    $wpdb->query($query);
    if ($wpdb->last_error) {
      if ($actionResult) {
        $actionResult = 'Tables are deleted: '.$actionResult;
      }
      return $actionResult.'<br/>'.$wpdb->last_error;
    }
    $actionResult .= ' '.$tableToDelete;
  }

  return 'Tables are deleted successfully: '.$actionResult;
}
// end of deleteUnusedTablesFromDB()


function pgc_displayBoxStart($title, $style) {
  $html = '
			<div class="postbox" style="'.$style.'" >
				<h3 style="cursor:default;"><span>'.$title.'</span></h3>
				<div class="inside">';

  return $html;
}
// 	end of thanks_displayBoxStart()

function pgc_displayBoxEnd() {
  $html = '
				</div>
			</div>';

  return $html;
}
// end of thanks_displayBoxEnd()


function pgc_displayColumnHeadersNoneWP() {
  $html = '<tr>
              <th>'.__('Hide','pgc').'</th>
              <th>'.__('Table Name','pgc').'</th>
              <th>'.__('Records #','pgc').'</th>
              <th>'.__('KBytes #','pgc').'</th>
              <th>'.__('Plugin Name','pgc').'</th>
              <th>'.__('Plugin State','pgc').'</th>
            </tr>';
   return $html;
}
// displayColumnHeadersNoneWP()


function pgc_showTables($tables) {
  $html =  __('Let\'s see what tables in your database do not belong to the core WordPress installation:', 'pgc');
  if (count($tables)>0) {
    $pgc_settings = get_option('pgc_settings');
    $html .= '
       <table id="pgc_plugin_tables" class="widefat" style="clear:none;" cellpadding="0" cellspacing="0">
          <thead>';
    $html .= pgc_displayColumnHeadersNoneWP();
    $html .= '
          </thead>
          <tbody>';

    $showHiddenTables = isset($_POST['show_hidden_tables']) && $_POST['show_hidden_tables'];
    $showDeleteTablesButton = false; $hiddenTableExists  = false;
    $i = 0;
    foreach ($tables as $table) {
      if ($i & 1) {
        $rowClass = 'class="pgc_odd"';
      } else {
        $rowClass = 'class="pgc_even"';
      }      
      $hiddenTable = isset($pgc_settings['hidden'][$table->name]);
      if ($hiddenTable && !$showHiddenTables) {  // skip this table
        $hiddenTableExists  = true;
        continue;
      }
      $i++;
      $html .= '<tr '.$rowClass.' id="'.$table->name.'" >
                  <td>';
      if ($table->plugin_state=='active') {
        if ($hiddenTable) {
          $checked = 'checked="checked"';          
        } else {
          $checked = '';
        }
        $html .= '<input type="checkbox" name="hidden_'.$table->name.'" id="hidden_'.$table->name.'" onclick="pgc_HideTable(this, \''.$table->name.'\')" '.$checked.' />
                  <img id="ajax_'.$table->name.'" class="ajax_processing" src="'.PGC_WP_ADMIN_URL.'/images/loading.gif" alt="ajax request processing..." title="AJAX request processing..."/>';
      }
      $html .= '</td>
            <td style="vertical-align:top;width:100px;" >';
      $deleteCheckBox = '';
      if (!$table->plugin_name) {
        $color = 'red';
        $deleteCheckBox = '<input type="checkbox" name="delete_'.$table->name.'" />';
        $showDeleteTablesButton = true;
      } else if ($table->plugin_state=='active') {
        $color = 'green';
      } else {
        $color = 'blue';
      }
      $html .= $deleteCheckBox.' <span style="color:'.$color.';">'.$table->name.'</span>';
      $html .= '
            </td>
            <td>
              <span style="color:'.$color.';">'.$table->records.'</span>
            </td>
            <td>
              <span style="color:'.$color.';">'.$table->kbytes.'</span>
            </td>
            <td>';
      if ($table->plugin_name) {
        $html .= '<span style="color:'.$color.';">'.$table->plugin_name.'</span>';
      } else {
        $html .= '<span style="color:red;">Unknown</span>';
      }
      $html .= '
            </td>
            <td>
              <span style="color:'.$color.';">'.$table->plugin_state.'</span>
            </td>
          </tr>';
    }
    $html .= '
          </tbody>
          <tfoot>';

    $html .= pgc_displayColumnHeadersNoneWP();
    $html .= '
          </tfoot>
      </table>';
    if ($hiddenTableExists) {
      $html .= '<span style="color: #bbb; font-size: 0.8em;">'.__('Some tables are hidden by you. Turn on "Show hidden DB tables" option and click "Scan" button again to show them.', 'pgc').'</span>';
    }
    if ($showDeleteTablesButton) {
      $html .= '
      <table>
        <tr>
          <td>
            <div class="submit">
              <input type="submit" name="deleteTableAction" value="'.__('Delete Tables', 'pgc').'"/>
            </div>
          </td>
          <td>
            <div style="padding-left: 10px;"><span style="color: red; font-weight: bold;">'.__('Attention!','pgc').'</span> '.
              __('Operation rollback is not possible. Consider to make database backup first. Please double think before click <code>Delete Tables</code> button.','pgc').'
            </div>
          </td>
        </tr>
      </table>';
    }
  } else {
    $html .= pgc_displayBoxStart().'
    <span style="color: green; text-align: center; font-size: 1.2em;">'.
      __('Congratulations! It seems that your WordPress database is clean.','pgc').'
    </span>'.
    pgc_displayBoxEnd();
  }

  return $html;
}
// end of showTables()


function pgc_displayColumnHeadersWP() {
  $html = '<tr>
              <th>'.__('Hide','pgc').'</th>
              <th>'.__('Table Name','pgc').'</th>
              <th>'.__('Extra Field','pgc').'</th>
              <th>'.__('Plugin Name','pgc').'</th>
              <th>'.__('Plugin State','pgc').'</th>
            </tr>';
   return $html;
}
// displayColumnHeadersWP()


// Get all of the field names in the query from between the parens
function pgc_extractFieldNames($query) {

  $columns = array(); 
  $match2 = array();
  preg_match("|\((.*)\)|ms", strtolower($query), $match2);
  $line = trim($match2[1]);
  // Separate field lines into an array
  $fields = explode("\n", $line);  
  // For every field line specified in the query
  foreach($fields as $field) {
    $validfield = true;
    $field = trim($field);
    // Extract the field name
    $fvalue = array();
    preg_match("|^([^ ]*)|", $field, $fvalue);
    $fieldname = strtolower(trim($fvalue[1], '`' ));
    // Verify the found field name
    switch ($fieldname) {
      case '':
      case 'primary':
      case 'index':
      case 'fulltext':
      case 'unique':
      case 'key':
        $validfield = false;
        break;
    }

    // If it's a valid field, add it to the field array
    if ($validfield) {
      $columns[$fieldname] = 1;
    }
  }

  return $columns;
}
// pgc_extractFieldNames()


function pgc_checkWpTablesStructure() {

global $wpdb, $wp_queries;

// Separate individual queries into an array
  $queries = explode( ';', $wp_queries );
  if (''==$queries[count($queries)-1]) {
    array_pop($queries);
  }

  update_option('pgc_scanprogress_current', 1);
  update_option('pgc_scanprogress_status', 'Start scanning...');
  update_option('pgc_scanprogress_total', count($queries));

  $wpTablesList = array();

  foreach ($queries as $query) {
    if (preg_match("|CREATE TABLE ([^ ]*)|", $query, $matches)) {
      $wpTablesList[trim( strtolower($matches[1]), '`' )] = $query;
    }
  }

  $changedTables = array();
  $i = 1;
  foreach ($wpTablesList as $table=>$createQuery) {
    update_option('pgc_scanprogress_current', $i);
    update_option('pgc_scanprogress_status', $table);

    // orginal structure columns list
    $origColumns = pgc_extractFieldNames($createQuery);

    // fact structrue columns list
    $query = "describe $table";
    $factColumns = $wpdb->get_results($query);
    foreach ($factColumns as $factColumn) {
      if (!isset($origColumns[strtolower($factColumn->Field)])) {
        if (!isset($changedTables[$table])) {
          $changedTables[$table] = array();
        }        
        $changedTables[$table][$factColumn->Field] = new stdClass();
        $changedTables[$table][$factColumn->Field]->plugin_name = '';
        $changedTables[$table][$factColumn->Field]->plugin_state = '';
      }
    }
  }

  delete_option('pgc_scanprogress_current');
  delete_option('pgc_scanprogress_total');
  delete_option('pgc_scanprogress_status');

  if (count($changedTables)>0) {
    
    $html .= '
       <table id="pgc_plugin_tables" class="widefat" style="clear:none;" cellpadding="0" cellspacing="0">
          <thead>'
      .pgc_displayColumnHeadersWP().
          '</thead>
          <tbody>';
    $pgc_settings = get_option('pgc_settings');
    $showHiddenTables = isset($_POST['show_hidden_tables']) && $_POST['show_hidden_tables'];
    $showDeleteButton = false; $hiddenTableExists  = false;
    $i = 0;
    foreach ($changedTables as $tableName=>$columnData) {
      foreach ($columnData as $column=>$plugin) {
      if ($i & 1) {
        $rowClass = 'class="pgc_odd"';
      } else {
        $rowClass = 'class="pgc_even"';
      }
      $hiddenTable = isset($pgc_settings['hidden'][$tableName]);
      if ($hiddenTable && !$showHiddenTables) {  // skip this table
        $hiddenTableExists  = true;
        continue;
      }
      $i++;
      $html .= '<tr '.$rowClass.' id="'.$tableName.'" >
                  <td>';
      if ($plugin->plugin_state=='active') {
        if ($hiddenTable) {
          $checked = 'checked="checked"';
        } else {
          $checked = '';
        }
        $html .= '<input type="checkbox" name="hidden_'.$tableName.'" id="hidden_'.$tableName.'" onclick="pgc_HideTable(this, \''.$tableName.'\')" '.$checked.' />
                  <img id="ajax_'.$tableName.'" class="ajax_processing" src="'.PGC_WP_ADMIN_URL.'/images/loading.gif" alt="ajax request processing..." title="AJAX request processing..."/>';
      }
      $html .= '</td>
            <td style="vertical-align:top;width:100px;" >';
      $deleteCheckBox = '';
      if (!$plugin->plugin_name) {
        $color = 'red';
        $deleteCheckBox = '<input type="checkbox" name="delete_'.$tableName.'" />';
        $showDeleteButton = true;
      } else if ($plugin->plugin_state=='active') {
        $color = 'green';
      } else {
        $color = 'blue';
      }
      $html .= $deleteCheckBox.' <span style="color:'.$color.';">'.$tableName.'</span>';
      $html .= '
            </td>
            <td><span style="color:'.$color.';">'.$column.'</span></td><td>';
      if ($plugin->plugin_name) {
        $html .= '<span style="color:'.$color.';">'.$plugin->plugin_name.'</span>';
      } else {
        $html .= '<span style="color:red;">unknown</span>';
      }
      $html .= '</td>
            <td><span style="color:'.$color.';">'.$plugin->plugin_state.'</span></td>
          </tr>';
      }
    }
    $html .= '</tbody>
          <tfoot>'
    .pgc_displayColumnHeadersWP().
          '</tfoot>
      </table>';
    if ($hiddenTableExists) {
      $html .= '<span style="color: #bbb; font-size: 0.8em;">'.__('Some tables are hidden by you. Turn on "Show hidden DB tables" option and click "Scan" button again to show them.', 'pgc').'</span>';
    }
    if ($showDeleteButton) {
      $html .= '
      <table>
        <tr>
          <td>
            <div class="submit">
              <input type="submit" name="deleteExtraColumnsAction" value="'.__('Delete Extra Columns', 'pgc').'"/>
            </div>
          </td>
          <td>
            <div style="padding-left: 10px;"><span style="color: red; font-weight: bold;">'.__('Attention!','pgc').'</span> '.
              __('Operation rollback is not possible. Consider to make database backup first. Please double think before click <code>Delete Extra Columns</code> button.','pgc').'
            </div>
          </td>
        </tr>
      </table>';
    }
  } else {
    $html .= pgc_displayBoxStart().'
    <span style="color: green; text-align: center; font-size: 1.2em;">'.
      __('Congratulations! It seems that your WordPress database tables structure is not changed','pgc').'
    </span>'.
    pgc_displayBoxEnd();
  }

  return $html;
}
// end of pgc_checkWpTablesStructure()



function pgc_deleteExtraColumnsFromWPTables() {

  $message = __('This feature is still in production and will be realized in the next version', 'pgc');
  
  return $message;
}
// end of pgc_deleteExtraColumnsFromWPTables()


?>
