<?php
/**
 * This file holds the ShareaholicAdmin class.
 *
 * @package shareaholic
 */

/**
 * This class takes care of all of the admin interface.
 *
 * @package shareaholic
 */
class ShareaholicAdmin {
  /**
   * Load the terms of service notice that shows up
   * at the top of the admin pages.
   */
  public static function show_terms_of_service() {
    ShareaholicUtilities::load_template('terms_of_service_notice');
  }

  /**
   * Renders footer
   */
  public static function show_footer() {
    ShareaholicUtilities::load_template('footer');
  }

  /**
   * Renders SnapEngage
   */
  public static function include_snapengage() {
    ShareaholicUtilities::load_template('script_snapengage');
  }

  /**
   * Adds meta boxes for post and page options
   */
  public static function add_meta_boxes() {
    $screens = array( 'post', 'page' );
    foreach ($screens as $screen) {
      add_meta_box(
        'shareaholic',
        'Shareaholic',
        array('ShareaholicAdmin', 'meta_box'),
        $screen,
        'side',
        'low'
      );
    }
  }

  /**
   * This is the wp ajax callback for when a user
   * checks a checkbox for a location that doesn't
   * already have a location_id. After it has been
   * successfully created the id needs to be stored,
   * which is what this method does.
   */
  public static function add_location() {
    $location = $_POST['location'];
    $app_name = $location['app_name'];
    ShareaholicUtilities::update_options(array(
      'location_name_ids' => array(
        $app_name => array(
          $location['name'] => $location['id']
        ),
      ),
      $app_name => array(
        $location['name'] => 'on'
      )
    ));

    echo json_encode(array(
      'status' => "successfully created a new {$location['app_name']} location",
      'id' => $location['id']
    ));

    die();
  }

  /**
   * Shows the message about failing to create an api key
   */
  public static function failed_to_create_api_key() {
    ShareaholicUtilities::load_template('failed_to_create_api_key');
    if (isset($_GET['page']) && preg_match('/shareaholic/', $_GET['page'])) {
      ShareaholicUtilities::load_template('failed_to_create_api_key_modal');
    }
  }


  /**
   * The actual function in charge of drawing the meta boxes.
   */
  public static function meta_box() {
    global $post;
    $settings = ShareaholicUtilities::get_settings();
    ShareaholicUtilities::load_template('meta_boxes', array(
      'settings' => $settings,
      'post' => $post
    ));
  }

  /**
   * This function fires when a post is saved
   *
   * @param int $post_id
   */
  public static function save_post($post_id) {
    // wordpress does something silly where save_post is fired twice,
    // once with the id of a revision and once with the actual id. This
    // filters out revision ids (which we don't want)
    if (!wp_is_post_revision($post_id)) {
      self::disable_post_attributes($post_id);
    }
  }

  /**
   * For each of the things that a user can disable per post,
   * we iterate through and turn add the post meta, or make it false
   * if it *used* to be true, but did not come through in $_POST
   * (because unchecked boxes are not submitted).
   *
   * @param int $post_id
   */
  private static function disable_post_attributes($post_id) {
    foreach (array(
      'disable_share_buttons',
      'disable_open_graph_tags',
      'disable_recommendations'
    ) as $attribute) {
      $key = 'shareaholic_' . $attribute;
      if (isset($_POST['shareaholic'][$attribute]) &&
          $_POST['shareaholic'][$attribute] == 'on') {
        update_post_meta($post_id, $key, true);
      } elseif (get_post_meta($post_id, $key, true)) {
        update_post_meta($post_id, $key, false);
      }
    }
  }

  /**
   * Inserts admin css and js
   */
  public static function admin_head() {
    if (isset($_GET['page']) && preg_match('/shareaholic/', $_GET['page'])) {
      $csss = array();
      array_push($csss, ShareaholicUtilities::asset_url('application.css'));
      array_push($csss, plugins_url('assets/css/bootstrap.min.css', __FILE__));
      array_push($csss, plugins_url('assets/css/main.css', __FILE__));
      array_push($csss, '//fonts.googleapis.com/css?family=Open+Sans:400,300,700');

      $javascripts = array();
      array_push($javascripts, ShareaholicUtilities::asset_url('pub/shareaholic.js'));
      array_push($javascripts, plugins_url('assets/js/bootstrap.min.js', __FILE__));
      array_push($javascripts, plugins_url('assets/js/jquery_custom.js', __FILE__));
      array_push($javascripts, plugins_url('assets/js/jquery_ui_custom.js', __FILE__));
      array_push($javascripts, plugins_url('assets/js/jquery.reveal.modified.js', __FILE__));
      array_push($javascripts, plugins_url('assets/js/main.js', __FILE__));

      foreach ($csss as $css) {
        echo '<link rel="stylesheet" type="text/css" href="' . $css . '">';
      }

      foreach ($javascripts as $js) {
        echo '<script type="text/javascript" src="' . $js . '"></script>';
      }
    }
  }

  /**
   * Puts a new menu item under Settings.
   */
  public static function admin_menu() {
    add_menu_page('Shareaholic Settings',
      'Shareaholic',
      'manage_options',
      'shareaholic-settings',
      array('ShareaholicAdmin', 'admin'),
      SHAREAHOLIC_ASSET_DIR . 'img/shareaholic_16x16.png'
    );
    add_submenu_page('shareaholic-settings',
      'Available Apps',
      'Available Apps',
      'manage_options',
      'shareaholic-settings',
      array('ShareaholicAdmin', 'admin')
    );
    add_submenu_page('shareaholic-settings',
      'Advanced Settings',
      'Advanced Settings',
      'manage_options',
      'shareaholic-advanced',
      array('ShareaholicAdmin', 'advanced_admin')
    );
  }

  /**
   * Updates the information if passed in and sets save message.
   */
  public static function admin() {
    $settings = ShareaholicUtilities::get_settings();
    $action = str_replace( '%7E', '~', $_SERVER['REQUEST_URI']);
    if(isset($_POST['already_submitted']) && $_POST['already_submitted'] == 'Y' &&
        check_admin_referer($action, 'nonce_field')) {
      echo "<div class='updated settings_updated'><p><strong>". sprintf(__('Settings successfully saved', 'shareaholic')) . "</strong></p></div>";

      /*
       * only checked check boxes are submitted, so we have to iterate
       * through the existing app locations and if they exist in the settings
       * but not in $_POST, it must have been unchecked, and it
       * should be set to 'off'
       */
      foreach (array('share_buttons', 'recommendations') as $app) {
        if (isset($settings[$app])) {
          foreach ($settings[$app] as $location => $on) {
            if (!isset($_POST[$app][$location]) && $on == 'on') {
              $_POST[$app][$location] = 'off';
            }
          }
        }
        if (!isset($_POST[$app])) {
          $_POST[$app] = array();
        }
      }

      ShareaholicUtilities::update_options(array(
        'share_buttons' => $_POST['share_buttons'],
        'recommendations' => $_POST['recommendations'],
      ));

      ShareaholicUtilities::log_event("UpdatedSettings");

    }

    if (ShareaholicUtilities::has_accepted_terms_of_service()) {
      $api_key = ShareaholicUtilities::get_or_create_api_key();
      ShareaholicUtilities::get_new_location_name_ids($api_key);
    }

    self::draw_deprecation_warnings();
    self::draw_admin_form();
    self::draw_verify_api_key();
  }

  /**
   * The function for the advanced admin section
   */
  public static function advanced_admin() {
    $settings = ShareaholicUtilities::get_settings();
    $api_key = ShareaholicUtilities::get_or_create_api_key();

    if (!ShareaholicUtilities::has_accepted_terms_of_service()) {
      ShareaholicUtilities::load_template('terms_of_service_modal', array(
        'image_url' => SHAREAHOLIC_ASSET_DIR . 'img'
      ));
    }

    if(isset($_POST['reset_settings']) && $_POST['reset_settings'] == 'Y') {
      ShareaholicUtilities::destroy_settings();
      echo "<div class='updated settings_updated'><p><strong>"
        . sprintf(__('Settings successfully reset. Refresh this page to complete the reset.', 'shareaholic'))
        . "</strong></p></div>";
    }

    if(isset($_POST['already_submitted']) && $_POST['already_submitted'] == 'Y') {
      echo "<div class='updated settings_updated'><p><strong>". sprintf(__('Settings successfully saved', 'shareaholic')) . "</strong></p></div>";
      foreach (array('disable_tracking', 'disable_og_tags') as $setting) {
        if (isset($settings[$setting]) &&
            !isset($_POST['shareaholic'][$setting]) &&
            $settings[$setting] == 'on') {
          $_POST['shareaholic'][$setting] = 'off';
        } elseif (!isset($_POST['shareaholic'][$setting])) {
          $_POST['shareaholic'][$setting] = array();
        }
      }

      if (isset($_POST['shareaholic']['api_key']) && $_POST['shareaholic']['api_key'] != $api_key) {
        ShareaholicUtilities::get_new_location_name_ids($_POST['shareaholic']['api_key']);
      }

      if (isset($_POST['shareaholic']['api_key'])) {
        ShareaholicUtilities::update_options(array('api_key' => $_POST['shareaholic']['api_key']));
      }

      if (isset($_POST['shareaholic']['disable_tracking'])) {
        ShareaholicUtilities::update_options(array('disable_tracking' => $_POST['shareaholic']['disable_tracking']));
      }

      if (isset($_POST['shareaholic']['disable_og_tags'])) {
        ShareaholicUtilities::update_options(array('disable_og_tags' => $_POST['shareaholic']['disable_og_tags']));
      }
    }

    ShareaholicUtilities::load_template('advanced_settings', array(
      'settings' => ShareaholicUtilities::get_settings(),
      'action' => str_replace( '%7E', '~', $_SERVER['REQUEST_URI'])
    ));
  }

  /**
   * Checks for any deprecations and then shows them
   * to the end user.
   */
  private static function draw_deprecation_warnings() {
    $deprecations = ShareaholicDeprecation::all();
    if (!empty($deprecations)) {
      ShareaholicUtilities::load_template('deprecation_warnings', array(
        'deprecation_warnings' => $deprecations
      ));
    }
  }

  /**
   * Outputs the actual html for the form
   */
  private static function draw_admin_form() {
    $action = str_replace( '%7E', '~', $_SERVER['REQUEST_URI']);
    $settings = ShareaholicUtilities::get_settings();

    if (!ShareaholicUtilities::has_accepted_terms_of_service()) {
      ShareaholicUtilities::load_template('terms_of_service_modal', array(
        'image_url' => SHAREAHOLIC_ASSET_DIR . 'img'
      ));
    }

    ShareaholicUtilities::load_template('settings', array(
      'shareaholic_url' => Shareaholic::URL,
      'settings' => $settings,
      'action' => $action,
      'share_buttons' => (isset($settings['share_buttons'])) ? $settings['share_buttons'] : array(),
      'recommendations' => (isset($settings['recommendations'])) ? $settings['recommendations'] : array(),
      'directory' => dirname(plugin_basename(__FILE__)),
    ));
  }

  /**
   * This function is in charge the logic for
   * showing whatever it is we want to show a user
   * about whether they have verified their api
   * key or not.
   */
  private static function draw_verify_api_key() {
    if (!ShareaholicUtilities::api_key_verified()) {
      $settings = ShareaholicUtilities::get_settings();
      $api_key = $settings['api_key'];
      $verification_key = $settings['verification_key'];
      ShareaholicUtilities::load_template('verify_api_key_js', array(
        'verification_key' => $verification_key
      ));
    }
  }
}
?>