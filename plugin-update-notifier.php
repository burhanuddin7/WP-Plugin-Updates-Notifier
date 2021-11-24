<?php

$get_current_date = date('d/m/y');
class BStack_Plugin_Update_Notifier {

  /**
   * Autoload method
   * @return void
   */
  function __construct()
  {
    //init hooks
    global $get_current_date;
    add_action( 'admin_menu', array(&$this, 'register_sub_menu') ); //Add submenu in settings page
    if ( get_option('plugin_update_chk_date') !== $get_current_date ) {
      add_action( 'admin_init', array(&$this, 'do_update_check') );
    } 
  }


  /**
   * Register submenu
   * @return void
   */
  public function register_sub_menu()
  {
    add_submenu_page(
      'options-general.php',
      'Plugin Updates Notifier',
      'Plugin Updates Notifier',
      'manage_options',
      'plugin-updates-notifier',
      array(&$this, 'submenu_page_callback')
    );
  }

  /**
   * Render submenu
   * @return void
   */
  public function submenu_page_callback()
  {
    //Update options
    if ( isset($_POST['update-option']) ) {
      update_option('bstack-slack-notify', sanitize_text_field($_POST['slack-notify']));
      update_option('bstack-slack-webhook-url', sanitize_text_field($_POST['slack-webhook-url']));
      update_option('bstack-slack-notify-channel', sanitize_text_field($_POST['slack-notify-channel']));
      update_option('bstack-notify-plugin-update', sanitize_text_field($_POST['notify-plugin-update']));
      //on save check for updates
      $this->do_update_check();
      echo '<div class="notice notice-success is-dismissible"><p>Setting options successfully updated.</p></div>';
    }

    $slack_notify = get_option("bstack-slack-notify") ?: "on";
    $slack_webhook_url = get_option("bstack-slack-webhook-url") ?: "";
    $slack_notify_channel = get_option("bstack-slack-notify-channel") ?: "";
    $bstack_notify_plugin_update = get_option("bstack-notify-plugin-update") ?: "on";
    $last_notification_sent = get_option('plugin_update_notify_time');

    ?>
    <h3>Plugin Updates Notifier</h3>

    <form method="POST" action="" class="setting-page-container">
      <div class="setting-page-row">
        <h3>Settings</h3>
        <hr>
      </div>

      <div class="setting-page-row">
        <div>
          <label class="setting-page-label">Notify about plugin updates?</label>
          <input type="radio" id="notify_plugin_no" name="notify-plugin-update" value="off" <?php echo (($bstack_notify_plugin_update === "off") ? "checked" : ""); ?>><label for="notify_plugin_no">No</label>
          <input type="radio" id="notify_plugin_yes_active" name="notify-plugin-update" value="on" <?php echo (($bstack_notify_plugin_update === "on") ? "checked" : ""); ?>><label for="notify_plugin_yes_active">Yes, but only active plugins</label>
        </div>
        <br/>
        <div><i>Last Notification was sent at: </i><b><?php echo $last_notification_sent; ?></b></div>
      </div>

      <div class="setting-page-row">
        <h3>Email & Slack Notifications</h3>
        <hr>
      </div>

      <div class="setting-page-row">
        <label class="setting-page-label">Send Email & Slack notifications?</label>
        <input type="radio" id="slack-notify-on" name="slack-notify" value="on" <?php echo (($slack_notify === "on") ? "checked" : ""); ?>><label for="slack-notify-on">On</label>
        <input type="radio" id="slack-notify-off" name="slack-notify" value="off" <?php echo (($slack_notify === "off") ? "checked" : ""); ?>><label for="slack-notify-off">Off</label>
      </div>

      <div class="setting-page-row">
        <label for="slack_webhook_url" class="setting-page-label">Slack Webhook url</label>
        <input id="slack_webhook_url" type="text" class="regular-text" name="slack-webhook-url" value="<?php echo $slack_webhook_url; ?>" />
      </div>

      <div class="setting-page-row">
        <label for="slack_notify_channel" class="setting-page-label">Slack Channel to notify</label>
        <input id="slack_notify_channel" type="text" class="regular-text" name="slack-notify-channel" value="<?php echo $slack_notify_channel; ?>" />
      </div>

      <div class="setting-page-row textleft">
        <input type="submit" name="update-option" class="button button-primary" value="Save Changes">
      </div>
    </form>
  <?php }


  //create your function, that runs on init and will check the updates every 24 hrs
  public function do_update_check() {
    global $get_current_date;
    if ( get_option('plugin_update_chk_date') !== $get_current_date ) {
      $message = ''; // start with a blank message
      $the_list = '';  // message for email body
      $bstack_notify_plugin_update = get_option("bstack-notify-plugin-update");
      if ( 'off' !== $bstack_notify_plugin_update ) { // check for plugin updates option?
        $plugins_updated = $this->plugin_update_notifier( $message, $bstack_notify_plugin_update, $the_list ); // check for plugin updates
      } else {
        $plugins_updated = false; // no plugin updates
      }
      
      if ( $plugins_updated ) { // Did anything come back as need updating?
        $message  = __( 'There are updates available for your WordPress site:', 'plugin-updates-notifier' ) . ' ' . esc_html( get_bloginfo() ) . ' @ ' . esc_url( home_url() ) . "\n" . $message . "\n";
        $message .= sprintf( __( 'Please visit %s to update the plugins.', 'plugin-updates-notifier' ), admin_url( 'update-core.php' ) );
        $slack_notify = get_option("bstack-slack-notify");
        
        // Send slack notification.
        if ( 'on' === $slack_notify ) {
          $save_updated_time = date('d-m-Y H:i a T');
          $this->plugin_slack_notifier( $message );
          $this->plugin_email_message( $message, $the_list );
          update_option('plugin_update_chk_date', $get_current_date);
          update_option('plugin_update_notify_time', $save_updated_time);
        }
      }
    }
  }


  /**
   * Check to see if any plugin updates.
   *
   * @param string $message Holds message to be sent via notification.
   * @param int  $all_or_active Should we look for all active plugins or none.
   *
   * @return bool
   */
  private function plugin_update_notifier( &$message, $all_or_active, &$the_list ) {
    global $wp_version;
    $cur_wp_version = preg_replace( '/-.*$/', '', $wp_version );
    if (!function_exists('get_plugins')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (!function_exists('get_site_transient')) {
      require_once ABSPATH . 'wp-admin/includes/option.php';
    }
    $updates = get_site_transient('update_plugins');
    $plugins = get_plugins();
    $plugin_group = get_option('plugin_status');
    $active_plugins = get_option('active_plugins'); // simple array of active plugins
    $the_list = array();  // for email message format
    $i = 1;

    if ( ! empty( $updates->response ) ) {
      $plugins_need_update = $updates->response; // plugins that need updating
      $act_plugins = array_flip( $active_plugins ); // find which plugins are active
      $plugins_need_update = array_intersect_key( $plugins_need_update, $act_plugins ); // only keep plugins that are active

      foreach ( $plugins_need_update as $name => $data ) { // loop through plugins that need update
        if ( isset( $plugin_group['plugin_to_update'][$name] ) ) { // has this plugin been notified before?
          if ( $data->new_version === $plugin_group['plugin_to_update'][$name] ) { // does this plugin version match that of the one that's been notified?
            unset( $plugins_need_update[$name] ); // don't notify this plugin as has already been notified
          }
        }
      }

      if ( count( $plugins_need_update ) >= 1 ) {
        if ( $all_or_active === 'on' ) {
          $message = sprintf( __( '# Last plugin update check was done at: %s IST', 'plugin-updates-notifier' ), date("Y-m-d g:i A", intval($updates->last_checked)) ) . "\n";
          foreach ( $plugins as $name => $plugin ) {
            if ( in_array( $name, $active_plugins ) ) { // display active plugins only
              if ( isset( $updates->response[$name] ) ) {
                $the_list[$i]["id"] = $name;
                $the_list[$i]["name"] = $plugin["Name"];
                $the_list[$i]["current_version"] = $plugin["Version"];
                $the_list[$i]["update"] = "yes";
                $the_list[$i]["version"] = $updates->response[$name]->new_version;
                $message .= "\n" . sprintf( __( 'Plugin: %1$s is out of date. Please update from version %2$s to %3$s', 'plugin-updates-notifier' ), $plugin['Name'], $plugin['Version'], $updates->response[$name]->new_version ) . "\n";
                if ( ! empty( $updates->response[$name]->url ) ) {
                  $updates->response[$name]->url = (substr( $updates->response[$name]->url, -1 ) === '/') ? $updates->response[$name]->url : $updates->response[$name]->url . '/';
                } else {
                  $updates->response[$name]->url = 'Not found ';
                }
                $message .= "\t" . sprintf( __( 'Details: %s', 'plugin-updates-notifier' ), $updates->response[$name]->url ) . "\n";
                $message .= "\t" . sprintf( __( 'Changelog: %1$s%2$s', 'plugin-updates-notifier' ), $updates->response[$name]->url, 'changelog' ) . "\n";
                $the_list[$i]["url"] = $updates->response[$name]->url;
  
                if ( isset( $updates->response[$name]->tested ) && version_compare( $updates->response[$name]->tested, $wp_version, '>=' ) ) {
                  $compat = sprintf( __( 'Compatibility with WordPress %1$s: 100%% (according to its author)', 'plugin-updates-notifier' ), $cur_wp_version );
                } else {
                  $compat = sprintf( __( 'Compatibility with WordPress %1$s: Unknown', 'plugin-updates-notifier' ), $wp_version );
                }
                $message .= "\t" . sprintf( __( 'Compatibility: %s', 'plugin-updates-notifier' ), $compat ) . "\n";
                $the_list[$i]["compatibility"] = $compat;

                // add special message on new update available
                if ( $plugins_need_update[$name] === $updates->response[$name] ) {
                  $the_list[$i]["new_update"] = "Latest update available";
                }
                $plugin_group['plugin_to_update'][$name] = $updates->response[$name]->new_version;
                $i++;
              }
            }
          }
          update_option( 'plugin_status', $plugin_group );
          return true; // we have plugin updates return true
        }
      }
    }
    return false; // No plugin updates so return false
  }


  /**
   * Sending Message on Slack when plugin update is available.
   */
  private function plugin_slack_notifier( $message )
  {
    $room =  get_option("bstack-slack-notify-channel");
    $icon = ':nginx:';
    $data = 'payload=' . wp_json_encode(array(
      'channel'       =>  "#{$room}",
      'text'          =>  $message,
      'icon_emoji'    =>  $icon
    ));

    // EndPoint from Slack App Setting
    $ch = curl_init(get_option("bstack-slack-webhook-url"));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    if ($result === false) {
      $result =  'Curl error: ' . curl_error($ch);
    }
    curl_close($ch);
    return $result;
  }


  /**
   * Sending Email when plugin update is available.
   */
  private function plugin_email_message( $message, $the_list ) {
    $siteurl = get_home_url();  // get site URL
    $to = get_field("wp_admin_email_id", "option");  //add admin emai where mail needs to be triggered
    $subject = '[Alert] BS Plugin Updates Notifier: Updates Available on ' . $siteurl . '';
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $message = explode('Plugin:', $message);
    $msg_heading = explode("#", array_shift($message));
    $new_msg = '<div>'. $msg_heading[0] .'</div><br>';
    $new_msg .= '<table cellpadding="5" style="border-collapse: collapse;border: 1px solid #ddd;"><thead><tr><th style="border: 1px solid #ddd;">Name</th><th style="border: 1px solid #ddd;">Status</th><th style="border: 1px solid #ddd;">Details</th><th style="border: 1px solid #ddd;">Compatibility</th><th style="border: 1px solid #ddd;">Update</th></tr></thead><tbody>';

    foreach ($the_list as $value) {
      $latest_update_chk = $value['new_update'] ? '<i style="color:#fb0404;font-size: 12px;"> '. $value['new_update'] . '</i>' : '-';
      $new_msg .= '<tr><td style="border: 1px solid #ddd;">'. $value['name'] .'</td>
        <td style="border: 1px solid #ddd;">Please update from version <strong>' . $value['current_version'] . '</strong> to <strong>' . $value['version'] . '</strong></td>
        <td style="border: 1px solid #ddd;"><div><a href="' . $value['url'] . '">More Info</a></div><div><a href="' . $value['url'] . 'changelog">Changelog</a></div></td>
        <td style="border: 1px solid #ddd;">' . $value['compatibility'] . '</td>
        <td style="border: 1px solid #ddd;">'. $latest_update_chk .'</td></tr>';
    }
    $new_msg .= '</tbody></table>';
    $new_msg .= '<br><br><div>'. sprintf( __( 'Please visit %s to update the plugins.', 'plugin-updates-notifier' ), admin_url( 'update-core.php' ) ) .'</div><br><div><i>'. trim($msg_heading[1]) .'</i></div>';
    $body = $new_msg;
    wp_mail($to, $subject, $body, $headers);
  }

}

new BStack_Plugin_Update_Notifier();

?>