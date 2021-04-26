<?php
/*
Plugin Name: Ingeni Store Locator
Plugin URI: https://github.com/BruceMcKinnon/ingeni-store-locator
Description: Simple store location with support for OSM and Leaflet maps
Author: Bruce McKinnon
Author URI: https://ingeni.net
Version: 2021.01
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html


2021.01 - Initial version


*/



if (!class_exists('islLatLngPair')) {
	class islLatLngPair {
		var $lat = 0;
		var $lng = 0;
		//var $title = '';

		public function __construct($lat, $lng) {
			$this->lat = floatval($lat);
			$this->lng = floatval($lng);
		}
	}
}

if ( !class_exists( 'IngeniStoreLocator' ) ) {
	class IngeniStoreLocator {
		public $name = 'Ingeni Store Locator';
		public $tag = 'ingeni-store-locator';
		public $options = array();
		public $messages = array();
		public $details = array();

		public $islMaps;


		public function __construct() {
			add_action( 'init', array( &$this, 'cpt_init' ) );

			if ( is_admin() ) {
				add_action( 'add_meta_boxes', array( &$this, 'isl_add_meta_boxes' ) );
				add_action( 'save_post', array( &$this, 'isl_content_save' ) );

				add_filter( 'plugin_action_links', array(&$this, 'isl_plugin_action_links'), 10, 2);

				add_action('admin_menu', array(&$this, 'isl_register_submenu_page') );

				add_action( 'wp_ajax_isl_ajax_nominatim_query', array(&$this, 'isl_ajax_nominatim_query') );
				add_action( 'wp_ajax_nopriv_isl_ajax_nominatim_query', array(&$this, 'isl_ajax_nominatim_query') );



			} else {
				add_shortcode( 'ingeni-store-locator', array( &$this, 'ingeni_store_locator_shortcode' ) );

				add_shortcode( 'ingeni-store-locator-nearest', array( &$this, 'ingeni_store_locator_nearest_shortcode' ) );

			}

			
			include_once('isl_map_support.php');
			include_once('isl_nominatim_support.php');

			add_action( 'wp_enqueue_scripts', array(&$this, 'isl_scripts') );
			
		}
		function isl_scripts() {
			wp_enqueue_style( 'ingeni-isl-css', plugins_url('css/ingeni-isl.css', __FILE__) );

			wp_enqueue_script( 'isl-geo-js', plugins_url( '/js/isl_geolocate.js', __FILE__ ), array('jquery'));
			wp_localize_script( 'isl-geo-js', 'ajax_object', array( 'ajax_url' => admin_url('admin-ajax.php'), 'we_value' => 'find_this' ) );
		}

		function activate() {
				$this->cpt_init();
				
				flush_rewrite_rules();
		}
		

		function register() {
				//
		}
		
		function deactivate() {
				flush_rewrite_rules();
		}


		public function cpt_init() {

			$this->isl_custom_post_type();

			// Init auto-update from GitHub repo
			require 'plugin-update-checker/plugin-update-checker.php';
			$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
				'https://github.com/BruceMcKinnon/ingeni-store-locator',
				__FILE__,
				'ingeni-store-locator'
			);

		}


		function isl_custom_post_type() {
			$cpt_obj = register_post_type('ingeni_storelocator',
				array(
				'labels' => array(
					'name' => _x('Ingeni Stores', 'Post Type General Name', 'textdomain'),
					'singular_name' => _x('Ingeni Store', 'Post Type General Name', 'textdomain'),
				),
				'rewrite' => array( 'slug' => 'store' ), // my custom slug
				'menu_icon'   => 'dashicons-store',
				// Features this CPT supports in Post Editor
        'supports' => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'revisions', 'custom-fields', ),
				// A hierarchical CPT is like Pages and can have Parent and child items.
				// A non-hierarchical CPT is like Posts
        'hierarchical' => false,
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_admin_bar' => true,
        'menu_position' => 5,
        'can_export' => true,
        'has_archive' => true,
        'exclude_from_search' => false,
        'publicly_queryable' => true,
        'capability_type' => 'post',
        'show_in_rest' => true,
				'taxonomies' => array('category','post_tag'),
				)
			);

			if ( is_wp_error( $cpt_obj ) ) {
				$this->fb_log('error: '.$cpt_obj->get_error_message());
			}
		}




		//
		// Utility functions
		//
		private function startsWith($haystack, $needle) {
			// search backwards starting from haystack length characters from the end
			return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
		}

		private function endsWith($haystack, $needle) {
			// search forward starting from end minus needle length characters
			return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
		}


		private function get_local_upload_path() {
			$upload_dir = wp_upload_dir();
			return $upload_dir['baseurl'];
		}

		private function local_debug_log($msg) {
			$this->fb_log($msg);
		}

		private function fb_log($msg) {
			$upload_dir = wp_upload_dir();
			$logFile = $upload_dir['basedir'] . '/' . 'fb_log.txt';
			date_default_timezone_set('Australia/Sydney');

			// Now write out to the file
			$log_handle = fopen($logFile, "a");
			if ($log_handle !== false) {
				fwrite($log_handle, date("H:i:s").": ".$msg."\r\n");
				fclose($log_handle);
			}
		}



		private function bool2str($value) {
			if ($value)
				return 'true';
			else
				return 'false';
		}

		private function intToBool($value) {
			if (is_int($value)) {
				if ($value == 0) {
					$value = false;
				} else {
					$value = true;
				}
			}
			return $value;
		}

		//
		// End utility functions
		//


		// https://developer.wordpress.org/reference/functions/add_meta_box/
		// https://www.smashingmagazine.com/2012/11/complete-guide-custom-post-types/

		// Adds the meta box containers

		public function isl_add_meta_boxes( ) {

			add_meta_box(
				'isl_address',
				__( 'Store Address', 'textdomain' ),
				array( &$this, 'render_isl_address' ),
				'ingeni_storelocator',
				'normal',
				'high'
			);
		}

		public function render_isl_address( $post ) {
			$this->render_isl_content( $post, 'street_address1', 'Street Address','cell small-12 large-6',1,0 );
			$this->render_isl_content( $post, 'street_address2', 'Address #2','cell small-12 large-6',0,1 );
			$this->render_isl_content( $post, 'town', 'City Town','cell small-12 large-4',1,0 );
			$this->render_isl_content( $post, 'state', 'State','cell small-12 large-4',0,0 );
			$this->render_isl_content( $post, 'postcode', 'Post/Zip Code','cell small-12 large-4',0,1 );
			$this->render_isl_content( $post, 'country', 'Country', 'cell small-12 large-6',1,1 );
			$this->render_isl_content( $post, 'phone1', 'Phone #1','cell small-12 large-6',1,0 );
			$this->render_isl_content( $post, 'phone2', 'Phone #2','cell small-12 large-6',0,1 );
			$this->render_isl_content( $post, 'email', 'Email','cell small-12 large-6',1,0 );
			$this->render_isl_content( $post, 'web', 'Web','cell small-12 large-6',0,1 );
			$this->render_isl_content( $post, 'lat', 'Lat','cell small-12 large-4',1,0  );
			$this->render_isl_content( $post, 'lng', 'Lng','cell small-12 large-4',0,1 );
		}


		// Render Meta Box content.
		public function render_isl_content( $post, $field, $title, $class = 'small-12 large-6', $row_start = 0, $row_end = 0 ) {

			// Add an nonce field so we can check for it later.
			wp_nonce_field(  plugin_basename( __FILE__ ), 'isl_'.$field.'_nonce' );

			// Use get_post_meta to retrieve an existing value from the database.
			$value = get_post_meta( $post->ID, '_isl_'.$field, true );

			if ( $row_start > 0 ) {
				echo ('<div class="isl_row">');
			}
			// Display the field, using the current value.
			?>
			<div class="<?php echo($class); ?>">
				<label for="isl_<?php echo($field); ?>_title"><?php echo($title); ?> </label>
				<input type="text" autocomplete="off" name="isl_<?php echo($field); ?>_title" id="isl_<?php echo($field); ?>_title" value="<?php echo($value); ?>" />
			</div>
			<?php

			if ( $row_end > 0 ) {
				echo ('</div>');
			}
		}



		// Save the meta when the post is saved
		public function isl_content_save( $post_id ) {

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return;

			if ( !wp_verify_nonce( $_POST['isl_content2_nonce'], plugin_basename( __FILE__ ) ) ) {
				//$this->fb_log('bad nonce');
				return;
			}


			// Check the user's permissions.
			if ( 'page' == $_POST['post_type'] ) {
				if ( ! current_user_can( 'edit_page', $post_id ) ) {
					$this->fb_log('cant edit page');
						return $post_id;
				}
			} else {
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					$this->fb_log('cant edit');
						return $post_id;
				}
			}

			// OK, it's safe for us to save the data now.

			// Sanitize the user input & Update the meta field
			$new = sanitize_title( $_POST['isl_title'], 'new store');
			update_post_meta( $post_id, '_isl_title', $new );

			$new = sanitize_text_field( $_POST['_isl_street_address1'], '');
			update_post_meta( $post_id, '_isl_street_address1', $new );

			$new = sanitize_text_field( $_POST['street_address2'], '');
			update_post_meta( $post_id, '_isl_street_address2', $new );

			$new = sanitize_text_field( $_POST['_isl_town'], '');
			update_post_meta( $post_id, '_isl_town', $new );
		
			$new = sanitize_text_field( $_POST['_isl_state'], '');
			update_post_meta( $post_id, '_isl_state', $new );
		
			$new = sanitize_text_field( $_POST['_isl_postcode'], '');
			update_post_meta( $post_id, '_isl_postcode', $new );
		
			$new = sanitize_text_field( $_POST['_isl_country'], '');
			update_post_meta( $post_id, '_isl_country', $new );

			$new = sanitize_text_field( $_POST['_isl_phone1'], '');
			update_post_meta( $post_id, '_isl_phone1', $new );
				
			$new = sanitize_text_field( $_POST['_isl_phone2'], '');
			update_post_meta( $post_id, '_isl_phone2', $new );
				
			$new = sanitize_email( $_POST['_isl_email'], '');
			update_post_meta( $post_id, '_isl_email', $new );
				
			$new = esc_url_raw( $_POST['_isl_web'], '');
			update_post_meta( $post_id, '_isl_web', $new );
				
			$new = sanitize_text_field( $_POST['l_isl_at'], '');
			if (is_numeric($new)) {
				update_post_meta( $post_id, '_isl_lat', $new );
			}
				
			$new = sanitize_text_field( $_POST['_isl_lng'], '');
			if (is_numeric($new)) {
				update_post_meta( $post_id, '_isl_lng', $new );
			}
	}



	//
	// Primary shortcode
	// [ingeni_store_locator_shortcode ]
	//
	public function ingeni_store_locator_shortcode( $att ) {
		$params = shortcode_atts( array(
				'class' => 'store_map',
				'lat' => '-27.7', // Center of Australia
				'lng' => '133.7751',
				'title' => 'Stockists',
				'pin_icon' => 'map-pin.svg',
				'pin_color' => '#000000',
				'zoom' => 4,
				'store_js_file' => '',
				'minheight' => '250px',
				'minwidth' => '100%',
				'layerprovider' => 'CartoDB.Positron'
		), $att );

		$idx = 0;

		global $wpdb;
		$retHtml = '';

		$store_js = 'var addressPoints = [';

		$sql = "SELECT ID, post_title FROM $wpdb->posts WHERE (post_type='ingeni_storelocator')AND(post_status='publish') ";

		$results = $wpdb->get_results( $wpdb->prepare( $sql ) );

		if ($results) {

				foreach($results as $result) {
					$_store_name = $result->post_title;
					$_store_id = $result->ID;

					$_store_lat = get_post_meta( $_store_id, '_isl_lat', true );
					$_store_lng = get_post_meta( $_store_id, '_isl_lng', true );
					$_store_addr1 = get_post_meta( $_store_id, '_isl_street_address1', true );
					$_store_addr2 = get_post_meta( $_store_id, '_isl_street_address2', true );
					$_store_town = get_post_meta( $_store_id, '_isl_town', true );
					$_store_state = get_post_meta( $_store_id, '_isl_state', true );
					$_store_postcode = get_post_meta( $_store_id, '_isl_postcode', true );

					$_store_phone = get_post_meta( $_store_id, '_isl_phone1', true );
					$tel = preg_replace("/[^0-9]/", "", $_store_phone );
					$_store_web = get_post_meta( $_store_id, '_isl_web', true );

					$info = '"<div class=\'map_info\'><h5>'.$_store_name.'</h5><p>'.trim($_store_addr1.' '.$_store_addr2).'</p>';
					$info .= '<p>'.trim($_store_town.' '.$_store_state.' '.$_store_postcode).'</p>';
					$info .= '<p><a href=\'tel:'.$tel.'\' target=\'_blank\'>'.$_store_phone.'</a></p>';
					if ($_store_web) {
						$host = parse_url($_store_web);
						$info .= '<p><a href=\''.$_store_web.'\' target=\'_blank\'>'.str_ireplace('www.','',$host['host']).'</a></p>';
					}
					$info .= '</div>"';

					//$info = '"'.$result->ID.'"';
					
					$store_js .= '['.$_store_lat.','.$_store_lng.','.$info.'],'.PHP_EOL;
				}
		}

		$store_js .= '];';

		$outFile = get_temp_dir() . 'isl_stores.txt';
		$mode = "w";

		$js_handle = fopen($outFile, $mode);
		if ($js_handle !== false) {
			fwrite($js_handle, $store_js);
			fclose($js_handle);
		}

		$params['store_js_file'] = $outFile;
		$islMaps = null;

		if (class_exists('IngeniStoreLocatorMaps')) {
			$islMaps = new IngeniStoreLocatorMaps();
		}
		if ($islMaps) {
			$retHtml .= $islMaps->isl_show_open_street_cluster_map( $params );
		} else {
			$retHtml .= '<p><strong>Can\'t load IngeniStoreLocatorMaps!</strong</p>';
		}

		return $retHtml;
	}



	function SerialiseLatLng( $latlng, $type ) {
		$out = json_encode($latlng);

		$outFile = get_temp_dir() . $type.'-latlng.txt';
	//fb_log('writing to:'.$outFile);
		// Now write out to the file
		$mode = "w";

		$log_handle = fopen($outFile, $mode);
		if ($log_handle !== false) {
			fwrite($log_handle, $out);
			fclose($log_handle);
		}
	}


	public function isl_register_submenu_page() {
		add_submenu_page( 'tools.php', 'Store Locator ', 'Ingeni Store Locator Import', 'manage_options', 'store-locator-page', array( &$this, 'isl_options_page' ) );
	}


	public function isl_options_page() {

		$selected_file = "";

		// Current user must be a Contributor at least.
		if ( !current_user_can( 'edit_posts' ) )  {
			wp_die( __( 'You don\'t have sufficient permissions to access this page.' ) );
		}



		if ( (isset($_POST['ingeni_isl_importer_edit_hidden'])) && ($_POST['ingeni_isl_importer_edit_hidden'] == 'Y') ){
			$errMsg = "";
			
			switch ($_REQUEST['btn_ingeni_isl_importer_submit']) {
				case "Import Now":
//$this->fb_log('importing.....');
					$import_count = 0;
					$date_start = new DateTime();

					// Set the start time
					date_default_timezone_set("Australia/Sydney"); 

//$this->fb_log(print_r($_FILES,true));
					// Selected file
					if ( $_FILES['btn_isl_select']['name'] != '' ) {
						$selected_file = $_FILES['btn_isl_select']['name'];
						$tmp_file = $_FILES['btn_isl_select']['tmp_name'];
						$size = $_FILES['btn_isl_select']['size'];
//$this->fb_log($selected_file.' - '.$tmp_file.' - '.$size);

						$skip_header_row = false;
						if (isset($_POST['ingeni_isl_skip_first_line'] )) {
							$skip_header_row = true;
						}
						$import_count = $this->isl_upload_import( $selected_file, $tmp_file, $size, $skip_header_row );
//$this->fb_log(' $import_count '.$import_count);
					}
					$date_end = new DateTime();
					$diffInSeconds = $date_end->getTimestamp() - $date_start->getTimestamp();


					if ( $import_count > 0 ) {
						echo('<div class="updated"><p><strong>'.$import_count.' stores imported in '.$diffInSeconds.' secs...</strong></p></div>');
						//$this->fb_log('all done: '.$import_count);
					} else {
						echo('<div class="updated"><p><strong>ERR: '.$errMsg.'</strong></p></div>');		
					}
					
				break;
					
				case "Save Settings":
					//update_option('pfc_packing_slips_max_rows', $_POST['options_max_rows']);
					update_option('ingeni_isl_skip_first_line', isset($_POST['ingeni_isl_skip_first_line'] ));

					echo('<div class="updated"><p>Settings saved...</p></div>');

				break;
				
				case "Clear Multis";
					$clear_count = clear_multi();
					echo('<div class="updated"><p>Cleared '.$clear_count.'</p></div>');
				break;
			}
		}

		$ingeni_isl_skip_first_line = get_option('ingeni_isl_skip_first_line');


		echo('<div class="wrap">');
			echo('<form action="" method="post" enctype="multipart/form-data">'); 
			echo('<input type="hidden" name="ingeni_isl_importer_edit_hidden" value="Y">');

			echo('<table class="form-table isl-importer"><tbody>');

			echo('<tr valign="top">'); 
			echo('<td><input type="file" name="btn_isl_select" value="Select"></td>');
			echo('</tr>');

			echo('<tr valign="top">'); 
			echo('<td>Selected file:'.$selected_file.'</td>');
			echo('</tr>'); 

			$checked_value = '';
			if ($ingeni_isl_skip_first_line) {
				$checked_value = ' checked'; 
			}
			echo('<tr valign="top"><td><input type="checkbox" id="ingeni_isl_skip_first_line" name="ingeni_isl_skip_first_line" '.$checked_value.' />Skip first line</td></tr>');  


			// Progress information
			echo('<tr valign="top">'); 
			echo('<td><div id="ingeni_isl_importer_info" style="width"></div></td>');
			echo('</tr>'); 

			echo('</tbody></table><br/>');

			echo('<input type="submit" name="btn_ingeni_isl_importer_submit" value="Save Settings">');
			echo('<input type="submit" name="btn_ingeni_isl_importer_submit" value="Import Now">');
			
			echo('</form>');

			
			if (is_local()) {
				echo('<p style="color:red;" >Local Install!!!</p>');
			} else {
				echo('<p style="color:green;" >Public Install</p>');			
			}
		echo('</div>');



	}




	public function isl_upload_import( $selected_file, $tmp_file, $size, $skip_header_row ) {
		//$this->fb_log('isl_upload_import....');

		try {
			$importCount = 0;
			$errMsg = "";
			$allowedTypes = array("csv");
			$zip_path = "";


			//$this->fb_log('isl_upload_import: '. $selected_file.' | ' .$tmp_file.' | ' .$size);

			if ( $this->isl_upload_to_server( $selected_file, $tmp_file, $fileSize, $allowedTypes, $errMsg, $zip_path )  == 0 ) {
					$this->fb_log( 'upload err: '.$errMsg );
			} else {
					$uploadedFile = $errMsg;
					//$this->fb_log('the file was uploaded: '.$uploadedFile);
			}

			if ( !file_exists($uploadedFile) ) {
					throw new Exception("Import file does not exist!");
			}


			$fileHandle = fopen($uploadedFile, "r");
			if ($fileHandle === FALSE) {
					throw new Exception("Error opening ".$uploadedFile);
			}
			fclose($fileHandle);

			//
			// The file is uploaded, so now extract the data and save it
			//

			$items = $this->isl_csv_to_array($uploadedFile, $skip_header_row);

			if ($items) {
				foreach ($items as $item) {
					if ( $this->isl_save_item($item) > 0) {
						$importCount += 1;
					}
				}
			}

		} catch (Exception $e) {
			$this->fb_log('isl_upload_import: '.$this->e->message);
		}

		return $importCount;
	}


	private function isl_save_item($item) {
		//$this->fb_log(print_r($item,true));


		$post_id = -1;
		if (array_key_exists('name',$item)) {
			$content = '';
			if (array_key_exists('content',$item)) {
				$content = $item['content'];
			}


			$existingPost = get_page_by_title($item['name'], OBJECT, 'ingeni_storelocator');
			if ( $existingPost ) {
				$post_id = $existingPost->ID;
				
//$this->fb_log('isl_save_item existing:'.$post_id);
			} else {
				$newPost = array(
					'post_title' => $item['name'],
					'post_content' => $content,
					'post_status' => 'publish',
					'post_type' => 'ingeni_storelocator'
				);
				
				// Insert the post into the database
				$err = 0;
				$post_id = wp_insert_post( $newPost, $err);

//$this->fb_log('isl_save_item new:'.$post_id);
			}

			if( !is_wp_error($post_id) ) {

				if (array_key_exists('address1',$item)) {
					update_post_meta( $post_id, '_isl_street_address1', sanitize_text_field($item['address1']) );
				}
				if (array_key_exists('address2',$item)) {
					update_post_meta( $post_id, '_isl_street_address2', sanitize_text_field($item['address2']) );
				}
				if (array_key_exists('town',$item)) {
					update_post_meta( $post_id, '_isl_town', sanitize_text_field($item['town']) );
				}
				if (array_key_exists('state',$item)) {
					update_post_meta( $post_id, '_isl_state', sanitize_text_field($item['state']) );
				}
				if (array_key_exists('postcode',$item)) {
					update_post_meta( $post_id, '_isl_postcode', sanitize_text_field($item['postcode']) );
				}
				if (array_key_exists('country',$item)) {
					update_post_meta( $post_id, '_isl_country', sanitize_text_field($item['country']) );
				}
				if (array_key_exists('phone1',$item)) {
					update_post_meta( $post_id, '_isl_phone1', sanitize_text_field($item['phone1']) );
				}
				if (array_key_exists('phone2',$item)) {
					update_post_meta( $post_id, '_isl_phone2', sanitize_text_field($item['phone2']) );
				}
				if (array_key_exists('email',$item)) {
					update_post_meta( $post_id, '_isl_email', sanitize_email($item['email']) );
				}
				if (array_key_exists('web',$item)) {
					update_post_meta( $post_id, '_isl_web', sanitize_url($item['web']) );
				}
				if (array_key_exists('lat',$item)) {
					if (is_numeric($item['lat'])) {
						update_post_meta( $post_id, '_isl_lat', $item['lat'] );
					}
				}
				if (array_key_exists('lng',$item)) {
					if (is_numeric($item['lng'])) {
						update_post_meta( $post_id, '_isl_lng', $item['lng'] );
					}
				}

			} else {
				$this->fb_log( 'ERR: '.$postId->get_error_message() );
				$post_id = 0;
			}

		}

		//$this->fb_log('isl_save_item='.$post_id);
		return $post_id;
	}


	private function isl_upload_to_server( $selectedFile, $tmpFile, $fileSize, $allowed_types = array("csv"), &$err_message, &$zip_path ) {
		try {
				$upl_folder = wp_upload_dir();

				//$this->fb_log('isl_upload_to_server selected: '. $selectedFile);

				//$target_file = $target_dir . $selectedFile['name'];
				//$target_file = $selectedFile['name'];
				$target_file = $selectedFile;
				$uploadOk = 1;
				$uploadFileType = strtolower( pathinfo($target_file,PATHINFO_EXTENSION) );

				// Check if file already exists
				if ( file_exists( $target_file ) ) {
						$err_message =  "Sorry, file already exists.";
						$uploadOk = 0;
				}

				// Check file size
				if ($uploadOk > 0) {
						if ( $fileSize > 50000000 ) {
								$err_message = "Sorry, your file is too large.";
								$uploadOk = 0;
						}
				}
				
				// Allow certain file formats
				if ($uploadOk > 0) {
						if ( !in_array( $uploadFileType, $allowed_types ) ) {
								$err_message = "Sorry, that file type is not allowed: ".$uploadFileType;
								$uploadOk = 0;
						}
				}

				//$this->fb_log('isl_upload_to_server wp_upload_bits: '. $target_file.' | '.$tmpFile);

				// if everything is ok, try to upload file
				if ( $uploadOk > 0 ) {
						$upload = wp_upload_bits($target_file, null, file_get_contents($tmpFile));

						if ($upload['error'] == false) {
								$err_message =  $upload['file'];
								$uploadOK = 1;
						} else {
								$err_message = $upload['error'];
								$uploadOk = 0;
						}
				}
				//$this->local_debug_log('A err msg:' .$uploadOk.' = '.$err_message.' = ' . $upload['url']);


				if ( $uploadOk > 0) {
						$path_parts = pathinfo($upload['file']);
						//$this->fb_log('path parts='.print_r($path_parts,true));
						if ( strtolower($path_parts['extension']) == "zip" ) {
								$temp_path = $path_parts['dirname'] . '/' . date('is');
								//$this->fb_log('temp dir='.$temp_path);

								mkdir($temp_path);
								if ( $this->unzip_upload($upload['file'], $temp_path) > 0 ) {
										// We have unzipped, so find the csv file;
										// Just in case, check to see if there has been a folder created
										// with the same name as the zip file.
										if ( is_dir( $temp_path . '/' . $path_parts['filename'] ) ) {
												$temp_path .= '/' . $path_parts['filename'];
										}

										$tmp_files = scandir($temp_path);
										//$this->fb_log('files='.print_r($tmp_files,true));
										foreach ($tmp_files as $tmp_file) {
												if ( strpos(strtolower($tmp_file),'.csv') !== false ) {
														$err_message = $upload['url'] = $temp_path . '/' . $tmp_file;
														break;
												}
										}
										$path_parts = pathinfo($err_message);
										$zip_path = $path_parts['dirname'];
								}
						}
				}

				if (! $uploadOk) {
					$this->fb_log('isl_upload_to_server error: '.$err_message);
				}

		} catch (Exception $e) {
				$this->fb_log('isl_upload_to_server: '.$e->message);
		}
		// Remove the tmp file
		unset( $tmpFile );

		//$this->fb_log('isl_upload_to_server: OK='. $uploadOk);
		
		return $uploadOk;
	}


	/**
	 * Convert a comma separated file into an associated array.
	 * The first row should contain the array keys.
	 * 
	 * Example:
	 * 
	 * @param string $filename Path to the CSV file
	 * @param string $delimiter The separator used in the file
	 * @return array
	 * @link http://gist.github.com/385876
	 * @author Jay Williams <http://myd3.com/>
	 * @copyright Copyright (c) 2010, Jay Williams
	 * @license http://www.opensource.org/licenses/mit-license.php MIT License
	 */
	private function isl_csv_to_array($filename='', $skip_header_row=false, $delimiter=',') {
		if(!file_exists($filename) || !is_readable($filename))
			return FALSE;
		
		//$this->fb_log('isl_csv_to_array:'.$filename);
		$header = NULL;
		$data = array();
		if (($handle = fopen($filename, 'r')) !== FALSE) {
			while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
				if ( $skip_header_row ) {
					$header = $row;
					$skip_header_row = false;
				} else {
					$data[] = array_combine($header, $row);
				}
			}
			fclose($handle);
		} else {
			$this->fb_log('isl_csv_to_array - could not open:'.$filename);
		}
		return $data;
	}
	
	public function isl_plugin_action_links($links, $file) {
		if ($file == plugin_basename(__FILE__)) {
			$isl_page_links = '<a href="'.get_admin_url().'options-general.php?page=isl_page_settings">'.__('Settings', 'ingeni_store_locator').'</a>';
			array_unshift($links, $isl_page_links);
		}

		return $links;
	}


	//
	// Geolocating
	//
	public function ingeni_store_locator_nearest_shortcode( $atts ) {
		$address_atts = shortcode_atts( array(
			'street' => '',
			'city' => '',
			'state' => '',
			'country' => 'AU',
			'postalcode' => '',
			'postcode' => '',
			'generic' => '',
		), $atts );

		$retHtml = '';

		$retHtml .= '<div id="isl_nearest_form">';

		$retHtml .= '<p>Type a Postcode or Town:</p>';
		$retHtml .= '<p><input type="text" id="loc_lookup" name="loc_lookup"><button id="isl_geo_btn" onclick="isl_geo()"><div id="isl_icon_search"></div><div id="isl_icon_wait"></div></button></p>';
		//$retHtml .= '</form>';
		$retHtml .= '</div>';
		$retHtml .= '<div id="isl_nearest_list"></div>';
		

		return $retHtml;
	}


	public function isl_ajax_nominatim_query() {
//$this->fb_log( 'Made it into the Ajax function safe and sound!' );

		$find_this = $_POST['find_this'];

//$this->fb_log('find_this:'.$find_this);
		$retInfo = 'Computer says no!';

		$islNom = null;

		if (class_exists('IngeniStoreLocatorNominatim')) {
			$islNom = new IngeniStoreLocatorNominatim();
		}
		if ($islNom) {
			$retInfo = $islNom->isl_ajax_nominatim_query($find_this);
		}

//$this->fb_log('retInfo:'.$retInfo);
		echo $retInfo;

		wp_die(); // this is required to terminate immediately and return a proper response
	}

	} // End of Class
}





function isl_enqueue_admin_scripts() {
	wp_enqueue_style( 'ingeni-isl-admin-css', plugins_url('css/ingeni-isl-admin.css', __FILE__) );
}
add_action('admin_enqueue_scripts', 'isl_enqueue_admin_scripts');


if (class_exists('IngeniStoreLocator')) {
	$storeLocator = new IngeniStoreLocator();
	$storeLocator->register();
}



register_activation_hook(__FILE__, array($storeLocator, 'activate'));
register_deactivation_hook(__FILE__, array($storeLocator, 'deactivate'));
register_uninstall_hook(__FILE__, array($storeLocator, 'uninstall'));

?>