<?php
 if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://revlifter.com
 * @since      1.0.0
 *
 * @package    RevLifter
 * @subpackage Revlifter/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 *
 * @package    RevLifter
 * @subpackage Revlifter/admin
 * @author     RevLifter
 */
class Revlifter_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       RevLifter
	 * @param      string    $version    1.0.0
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		 // Add actions for admin menu, settings, styles, and scripts
		add_action('admin_menu', array( $this, 'addPluginAdminMenu' ), 9);   
		add_action('admin_init', array( $this, 'registerAndBuildFields' )); 
		add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		// Enqueue admin styles
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/revlifter-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		// Enqueue admin scripts
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/revlifter-admin.js', array( 'jquery' ), $this->version, false );

	}
	public function addPluginAdminMenu() {
		// Enqueue admin scripts
		add_submenu_page( 'options-general.php', 'RevLifter', 'RevLifter', 'administrator', $this->plugin_name.'-settings', array( $this, 'displayPluginAdminSettings' ) );
	}
	
	public function displayPluginAdminDashboard() {
		$requested_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

		switch ($requested_page) {
			case 'revlifter-admin-display':
				$file_to_include = 'partials/'.$this->plugin_name.'-admin-display.php';
				break;
			// Add more cases for other allowed pages
	
			default:
				echo 'Invalid page request.';
				return;
		}
		$absolute_path = plugin_dir_path(__FILE__) . $file_to_include;

		if (file_exists($absolute_path)) {
			require_once $absolute_path;
		} else {
			echo 'Requested file not found.';
		}
  	}

	public function displayPluginAdminSettings() {
		// set this var to be used in the settings-display view
		$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET[ 'tab' ]): 'general';
		if(isset($_GET['error_message'])){
			add_action('admin_notices', array($this,'settingsPageSettingsMessages'));
			do_action( 'admin_notices', sanitize_text_field($_GET['error_message']));
		}

		$requested_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

		switch ($requested_page) {
			case 'revlifter-settings':
				$file_to_include = 'partials/'.$this->plugin_name.'-admin-settings-display.php';
				break;
			default:
				echo 'Invalid page request..';
				return;
		}

		$absolute_path = plugin_dir_path(__FILE__) . $file_to_include;
	
		if (file_exists($absolute_path)) {
			require_once $absolute_path;
		} else {
			echo 'Requested file not found.';
		}

	}

	public function settingsPageSettingsMessages($error_message) {
		switch ($error_message) {
			case '1':
				$message = __('There was an error adding this setting. Please try again. If this persists, shoot us an email.', 'my-text-domain');
				$err_code = esc_attr('revlifter_uuid');
				$setting_field = 'revlifter_uuid';
				break;
		}
		$type = 'error';
		// Check if the error message has already been added
		if (!get_settings_errors($setting_field)) {
			add_settings_error(
				$setting_field,
				$err_code,
				$message,
				$type
			);
		}
	}

	private function is_valid_revlifter_uuid($uuid) {
        $result=false;
		$pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
		if (preg_match($pattern, $uuid)) {
			$result= true; // Valid UUIDv4
		} else {
			$result= false; // Invalid UUIDv4
		}
		if($result){
			 // Check if the UUID is valid by making a cURL request to the specified URL
			 $url = 'https://d2236hi0n02pja.cloudfront.net/' . $uuid . '.js';

			// Set up the arguments for the request
			$args = array(
				'timeout' => 60, // Set your desired timeout
			);

			// Make the HTTP request
			$response = wp_safe_remote_get($url, $args);

			 // Check for errors in the response
			if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
				$response_body = wp_remote_retrieve_body($response);
		
				// Check if the response contains an error (or use a more specific check)
				if (strpos($response_body, '<Error>') !== 0) {
					$result = true; // Valid UUID
				} else {
					$result = false; // Invalid UUID or Access Denied
				}
			} else {
				// Handle errors
				// Log the error or take appropriate action
				$result = false; // Set $result to false or handle the error accordingly
			}
		}
		 return $result;
		
    }
	
	public function validate_revlifter_uuid($input) {
        $validated_uuid = sanitize_text_field($input);

        // Perform the validation using the previously defined is_valid_revlifter_uuid function.
        if (!$this->is_valid_revlifter_uuid($validated_uuid)) {
            // If the UUID is not valid, add an error message to be displayed in the admin panel.
            add_settings_error(
                'revlifter_uuid', // Setting name (same as field ID).
                'invalid_revlifter_uuid', // Error code (can be any unique string).
                'Invalid RevLifter UUID. Please provide a valid RevLifter UUID.', // Error message.
                'error' // Error type (error, warning, success).
            );
        }

        return $validated_uuid; // Return the validated UUID or the original input if it's valid.
    }
	public function registerAndBuildFields() {
		// Add settings section
		add_settings_section(
			'revlifter_general_section', 
			'',  
			array( $this, 'revlifter_display_general_account' ),    
			'revlifter_general_settings'                   
		);
		
		// Add RevLifter UUID field
		$args = array(
			'type'        => 'input',
			'subtype'     => 'text',
			'id'          => 'revlifter_uuid',
			'name'        => 'revlifter_uuid',
			'required'    => 'true',
			'get_options_list' => '',
			'value_type'  => 'normal',
			'wp_data'     => 'option'
		);
		add_settings_field(
			'revlifter_uuid', // Use 'revlifter_uuid' as the field ID
			'RevLifter UUID',
			array( $this, 'revlifter_render_settings_field' ),
			'revlifter_general_settings',
			'revlifter_general_section',
			$args
		);
	
		// Add Hide Stock checkbox field
		$args = array(
			'type'        => 'input',
			'subtype'     => 'checkbox',
			'id'          => 'revlifter_hide_stock',
			'name'        => 'revlifter_hide_stock',
			'required'    => 'false',
			'get_options_list' => '',
			'value_type'  => 'normal',
			'wp_data'     => 'option'
		);
		add_settings_field(
			'revlifter_hide_stock',
			'Hide Actual Stock Amounts from RevLifter Events',
			array( $this, 'revlifter_render_settings_field' ),
			'revlifter_general_settings',
			'revlifter_general_section',
			$args
		);
	
		// Register settings
		register_setting(
			'revlifter_general_settings',
			'revlifter_uuid',
			array($this, 'validate_revlifter_uuid') ,
		);
		register_setting(
			'revlifter_general_settings',
			'revlifter_hide_stock',
			'sanitize_checkbox_field' // Sanitize as checkbox field
			
		);
	}	

	public function revlifter_display_general_account() {
		echo '<p>Please enter the RevLifter UUID you will have been provided with during onboarding.</p>';
	}
	 
	public function revlifter_render_settings_field($args) {    
		if($args['wp_data'] == 'option'){
			$wp_data_value = get_option($args['name']);
		} elseif($args['wp_data'] == 'post_meta'){
			$wp_data_value = get_post_meta($args['post_id'], $args['name'], true );
		}

		switch ($args['type']) {

			case 'input':
					$value = ($args['value_type'] == 'serialized') ? serialize($wp_data_value) : $wp_data_value;
					if($args['subtype'] != 'checkbox'){
							$prependStart = (isset($args['prepend_value'])) ? '<div class="input-prepend"> <span class="add-on">'.$args['prepend_value'].'</span>' : '';
							$prependEnd = (isset($args['prepend_value'])) ? '</div>' : '';
							$step = (isset($args['step'])) ? 'step="'.$args['step'].'"' : '';
							$min = (isset($args['min'])) ? 'min="'.$args['min'].'"' : '';
							$max = (isset($args['max'])) ? 'max="'.$args['max'].'"' : '';
							$prependStart=esc_html($prependStart);
							$prependEnd=esc_html($prependEnd);
							if(isset($args['disabled'])){
									// hide the actual input bc if it was just a disabled input the info saved in the database would be wrong - bc it would pass empty values and wipe the actual information

									echo esc_attr($prependStart).'<input type="'.esc_attr($args['subtype']).'" id="'.esc_attr($args['id']).'_disabled" '.esc_attr($step).' '.esc_attr($max).' '.esc_attr($min).' name="'.esc_attr($args['name']).'_disabled" size="40" disabled value="' . esc_attr($value) . '" /><input type="hidden" id="'.esc_attr($args['id']).'" '.esc_attr($step).' '.esc_attr($max).' '.esc_attr($min).' name="'.esc_attr($args['name']).'" size="40" value="' . esc_attr($value) . '" />'.esc_attr($prependEnd);
							} else {
									echo esc_attr($prependStart).'<input type="'.esc_attr($args['subtype']).'" id="'.esc_attr($args['id']).'" "'.esc_attr($args['required']).'" '.esc_attr($step).' '.esc_attr($max).' '.esc_attr($min).' name="'.esc_attr($args['name']).'" size="40" value="' . esc_attr($value) . '" />'.esc_attr($prependEnd);
							}

					} else {
							$checked = ($value) ? 'checked' : '';
							echo '<input type="'.esc_attr($args['subtype']).'" id="'.esc_attr($args['id']).'" "'.esc_attr($args['required']).'" name="'.esc_attr($args['name']).'" size="40" value="1" '.esc_attr($checked).' />';
					}
					break;
			default:
				# code...
				break;
		}
	}
}
