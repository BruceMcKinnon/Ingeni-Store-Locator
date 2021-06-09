<?php

class IngeniStoreSettings {
	private static $instance = null;

	private $debugMode = false;

	private function __construct( $debugOn = false ) {
		$this->debugMode = $debugOn;

		include_once(plugin_dir_path( __FILE__ ).'isl_util.php');

		$this->debugLog('IngeniStoreSettings constructed');

		add_action('admin_menu', array(&$this, 'isl_register_submenu_page') );
		add_action( 'admin_init', array(&$this, 'ingeni_isl_register_settings') );

		$instance = $this;
	}

	public static function getInstance( $debugOn = false ) {
		if( !self::$instance ) {
			self::$instance = new IngeniStoreSettings( $debugOn );
		}
		
		return self::$instance;
	}
	
	public function debugLog( $msg ) {
		if (class_exists('IngeniStoreUtil')) {
			$islUtil = new IngeniStoreUtil( $this->debugMode );
		}
		if ($islUtil) {
			$islUtil->debugLog($msg);
		}
	}


	//
	// Settings form
	//
	public function isl_register_submenu_page() {
		add_submenu_page( 'edit.php?post_type=ingeni_storelocator', 'Store Locator ', 'Settings', 'manage_options', 'store-locator-page', array( &$this, 'isl_render_options_page' ) );
	}

	function isl_render_options_page() {
		?>
		<h2>Ingeni Store Locator</h2>
		<form action="options.php" method="post" class="isl_settings">
				<?php 
				settings_fields( 'ingeni_isl_plugin_options' );
				do_settings_sections( 'ingeni_isl_plugin' );
				?>
				<?php /*
				<input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
				*/ ?>
				<?php
				submit_button('Save Settings');
				?>
		</form>
		<?php
	}


	function ingeni_isl_register_settings() {
		register_setting( 'ingeni_isl_plugin_options', 'ingeni_isl_plugin_options', array( &$this, 'ingeni_isl_plugin_options_validate') );

		add_settings_section( 'isl_general_settings', 'General Settings', array( &$this, 'ingeni_isl_plugin_section_general_text' ), 'ingeni_isl_plugin' );
		
		add_settings_field( 'ingeni_isl_default_country', 'Default Country', array( &$this, 'ingeni_isl_default_country' ), 'ingeni_isl_plugin', 'isl_general_settings' );
		add_settings_field( 'ingeni_isl_debug', 'Debug Mode', array( &$this, 'ingeni_isl_debug' ), 'ingeni_isl_plugin', 'isl_general_settings' );
		add_settings_field( 'ingeni_isl_skip_first_line', 'Skip First Line of Import File', array( &$this, 'ingeni_isl_skip_first_line' ), 'ingeni_isl_plugin', 'isl_general_settings' );
		
		add_settings_field( 'ingeni_isl_geoloc_import', 'Geolocate during Import', array( &$this, 'ingeni_isl_geoloc_import' ), 'ingeni_isl_plugin', 'isl_general_settings' );

		add_settings_section( 'isl_geoloc_settings', 'Geolocation Settings', array( &$this, 'ingeni_isl_plugin_section_geoloc_text' ), 'ingeni_isl_plugin' );
		
		add_settings_field( 'ingeni_isl_geoloc_service', 'Geolocation Service', array( &$this, 'ingeni_isl_geoloc_service' ), 'ingeni_isl_plugin', 'isl_geoloc_settings' );
		add_settings_field( 'ingeni_isl_mapbox_api_key', 'Mapbox API Key', array( &$this, 'ingeni_isl_mapbox_api_key' ), 'ingeni_isl_plugin', 'isl_geoloc_settings' );
		add_settings_field( 'ingeni_isl_mapquest_api_key', 'Mapquest API Key', array( &$this, 'ingeni_isl_mapquest_api_key' ), 'ingeni_isl_plugin', 'isl_geoloc_settings' );

	}

	function ingeni_isl_plugin_options_validate( $input ) {
		//$output['some_text_field']      = sanitize_text_field( $input['some_text_field'] );
		//$output['another_number_field'] = absint( $input['another_number_field'] );

		add_settings_error( 'IngeniStoreSettings', esc_attr( 'settings_updated' ), 'Saved', 'success' );

		return $input;
	}


	function ingeni_isl_plugin_section_general_text() {
		echo '<p>General Settings</p>';
	}
	function ingeni_isl_plugin_section_geoloc_text() {
		echo '<p>Set up the Geolocation service the plugin will use.</p>';
	}
	function ingeni_isl_debug() {
		$options = get_option( 'ingeni_isl_plugin_options' );
		echo "<input id='ingeni_isl_debug' name='ingeni_isl_plugin_options[debug]' type='checkbox' value='1' " . checked(1, $options['debug'], false) . " />";
	}

	function ingeni_isl_skip_first_line() {
		$options = get_option( 'ingeni_isl_plugin_options' );
		echo "<input id='ingeni_isl_skip_first_line' name='ingeni_isl_plugin_options[skip_first_line]' type='checkbox' value='1' " . checked(1, $options['skip_first_line'], false) . " />";
	}

	function ingeni_isl_geoloc_import() {
		$options = get_option( 'ingeni_isl_plugin_options' );
		echo "<input id='ingeni_isl_geoloc_import' name='ingeni_isl_plugin_options[geoloc_import]' type='checkbox' value='1' " . checked(1, $options['geoloc_import'], false) . " />";
	}

	function ingeni_isl_default_country() {
		$options = get_option( 'ingeni_isl_plugin_options' );
		echo "<input id='ingeni_isl_default_country' name='ingeni_isl_plugin_options[default_country]' type='text' value='" . esc_attr( $options['default_country'] ) . "' />";
	}

	function ingeni_isl_mapbox_api_key() {
		$options = get_option( 'ingeni_isl_plugin_options' );
		echo "<input id='ingeni_isl_mapbox_api_key' name='ingeni_isl_plugin_options[mapbox_api_key]' type='text' value='" . esc_attr( $options['mapbox_api_key'] ) . "' />";
	}

	function ingeni_isl_mapquest_api_key() {
		$options = get_option( 'ingeni_isl_plugin_options' );
		echo "<input id='ingeni_isl_mapquest_api_key' name='ingeni_isl_plugin_options[mapquest_api_key]' type='text' value='" . esc_attr( $options['mapquest_api_key'] ) . "' />";
	}


	function ingeni_isl_geoloc_service() {
		$options = get_option( 'ingeni_isl_plugin_options' );

		echo ('<select name="ingeni_isl_plugin_options[geoloc_service]" id="ingeni_isl_geoloc_service">');
			echo ('<option value="nominatim" '.selected( $options['geoloc_service'], 'nominatim').'>Nominatim</option>');
			echo ('<option value="mapbox" '.selected( $options['geoloc_service'], 'mapbox').'>Mapbox</option>');
			echo ('<option value="mapquest" '.selected( $options['geoloc_service'], 'mapquest').'>Mapquest</option>');
		echo('</select>');
	}
	
	function isl_plugin_action_links($links, $file) {
		if ($file == plugin_basename(__FILE__)) {
			$isl_page_links = '<a href="'.get_admin_url().'options-general.php?page=isl_page_settings">'.__('Settings', 'ingeni_store_locator').'</a>';
			array_unshift($links, $isl_page_links);
		}

		return $links;
	}

} // End of class

?>