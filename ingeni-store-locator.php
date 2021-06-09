<?php
/*
Plugin Name: Ingeni Store Locator
Plugin URI: https://github.com/BruceMcKinnon/ingeni-store-locator
Description: Simple store location with support for OSM and Leaflet maps
Author: Bruce McKinnon
Author URI: https://ingeni.net
Version: 2021.03
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html


2021.01 - Initial version

2021.02 - ingeni_store_locator_shortcode() - Don't include locations that have no lat/lng specified
				- Now supports Mapbox and Mapquest (API keys required) in addition to Nominitim
				- Admin screens now include a Get Lat/Lng button
				- Major code-refector.

2021.03 - Added support for categories and tags, both via the import and the shortcode.
				- ingeni_store_locator_shortcode() now uses WP_Query rather than direct SQL call.
				- implemented default inline svg map markers
				- Added optional checkboxes for Categories and Tags to the Nearest Search box.
				- Additional category and tags params for both shortcodes.

*/



if ( !class_exists( 'IngeniStoreLocator' ) ) {
	class IngeniStoreLocator {
		public $name = 'Ingeni Store Locator';
		public $tag = 'ingeni-store-locator';
		public $options = array();
		public $messages = array();
		public $details = array();

		public $islMaps;

		private $debugMode = false;

		public $islSettings = null; // Class for Settings page
		public $islCsv = null; // Class for CVS import page


		public function __construct() {

			$this->debugMode = false;
			$options = get_option( 'ingeni_isl_plugin_options' );

			if ( $options['debug'] ) {
				$this->debugMode = true;
			}


			include_once(plugin_dir_path( __FILE__ ).'isl_util.php');
			$this->debugLog('IngeniStoreLocator constructed');

			add_action( 'init', array( &$this, 'cpt_init' ) );

			if ( is_admin() ) {
				
				add_action( 'add_meta_boxes', array( &$this, 'isl_add_meta_boxes' ) );
				add_action( 'save_post', array( &$this, 'isl_content_save' ) );

				//add_filter( 'plugin_action_links', array(&$this, 'isl_plugin_action_links'), 10, 2);

				include_once(plugin_dir_path( __FILE__ ).'isl_settings_support.php');
				$islSettings = IngeniStoreSettings::getInstance( $this->debugMode );

				include_once(plugin_dir_path( __FILE__ ).'isl_import_support.php');
				$islCsv = IngeniStoreCsvImport::getInstance( $this->debugMode );

				add_action( 'wp_ajax_isl_ajax_geoloc_query', array(&$this, 'isl_ajax_geoloc_query') );
				add_action( 'wp_ajax_nopriv_isl_ajax_geoloc_query', array(&$this, 'isl_ajax_geoloc_query') );

				add_action( 'admin_enqueue_scripts', array(&$this, 'isl_scripts') );

			} else {
				add_shortcode( 'ingeni-store-locator', array( &$this, 'ingeni_store_locator_shortcode' ) );
				add_shortcode( 'ingeni-store-locator-nearest', array( &$this, 'ingeni_store_locator_nearest_shortcode' ) );

				add_action( 'wp_enqueue_scripts', array(&$this, 'isl_scripts') );
			}

			
			include_once(plugin_dir_path( __FILE__ ).'isl_map_support.php');
			include_once(plugin_dir_path( __FILE__ ).'isl_distance_support.php');
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
				$this->debugLog('error: '.$cpt_obj->get_error_message());
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

		public function debugLog( $msg ) {
			if (class_exists('IngeniStoreUtil')) {
				$islUtil = new IngeniStoreUtil( $this->debugMode );
			}
			if ($islUtil) {
				$islUtil->debugLog($msg);
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
			$this->render_isl_content( $post, 'country', 'Country', 'cell small-12 large-6',1,0 );
			?>
			<div class="cell small-12 large-6">
				<button id="isl_geo_btn" type="button" onclick="isl_geo('',0)">Get Lat/Lng</button>
			</div>
			<?php
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
				<label for="isl_<?php echo($field); ?>"><?php echo($title); ?> </label>
				<input type="text" autocomplete="off" name="isl_<?php echo($field); ?>" id="isl_<?php echo($field); ?>" value="<?php echo($value); ?>" />
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

			if ( !wp_verify_nonce( $_POST['isl_street_address1_nonce'], plugin_basename( __FILE__ ) ) ) {
				$this->debugLog('isl_content_save: bad nonce');
				return;
			}


			// Check the user's permissions.
			if ( 'page' == $_POST['post_type'] ) {
				if ( ! current_user_can( 'edit_page', $post_id ) ) {
					$this->debugLog('cant edit page');
					return $post_id;
				}
			} else {
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					$this->debugLog('cant edit');
					return $post_id;
				}
			}

			// OK, it's safe for us to save the data now.

			// Sanitize the user input & Update the meta field
			$new = sanitize_title( $_POST['isl_title'], 'new store');
			update_post_meta( $post_id, '_isl_title', $new );

			$new = sanitize_text_field( $_POST['isl_street_address1'], '');
			update_post_meta( $post_id, '_isl_street_address1', $new );

			$new = sanitize_text_field( $_POST['street_address2'], '');
			update_post_meta( $post_id, '_isl_street_address2', $new );

			$new = sanitize_text_field( $_POST['isl_town'], '');
			update_post_meta( $post_id, '_isl_town', $new );
		
			$new = sanitize_text_field( $_POST['isl_state'], '');
			update_post_meta( $post_id, '_isl_state', $new );
		
			$new = sanitize_text_field( $_POST['isl_postcode'], '');
			update_post_meta( $post_id, '_isl_postcode', $new );
		
			$new = sanitize_text_field( $_POST['isl_country'], '');
			update_post_meta( $post_id, '_isl_country', $new );

			$new = sanitize_text_field( $_POST['isl_phone1'], '');
			update_post_meta( $post_id, '_isl_phone1', $new );
				
			$new = sanitize_text_field( $_POST['isl_phone2'], '');
			update_post_meta( $post_id, '_isl_phone2', $new );
				
			$new = sanitize_email( $_POST['isl_email'], '');
			update_post_meta( $post_id, '_isl_email', $new );
				
			$new = esc_url_raw( $_POST['isl_web'], '');
			update_post_meta( $post_id, '_isl_web', $new );
				
			$new = sanitize_text_field( $_POST['isl_lat'], '');
			if ( is_numeric($new) || ($new == '') ) {
				update_post_meta( $post_id, '_isl_lat', $new );
			}
				
			$new = sanitize_text_field( $_POST['isl_lng'], '');
			if ( is_numeric($new) || ($new == '') ) {
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
					'pin_icon' => '',
					'pin_color' => '#000000',
					'zoom' => 4,
					'store_js_file' => '',
					'minheight' => '250px',
					'minwidth' => '100%',
					'layerprovider' => 'CartoDB.Positron',
					'clustered' => 1,
					'pin_width' => 30,
					'pin_height' => 30,
					'category' => '',
					'tags' => '',
			), $att );

			$idx = 0;

			global $wpdb;
			$retHtml = '';

			$store_js = 'var addressPoints = [';

			$args = array( 'post_type' => 'ingeni_storelocator', 'post_status' => 'publish', 'posts_per_page' => -1  );

			if ( $params['category'] != '') {
				$args += [ 'category_name' => $params['category'] ];
			}
			if ( $params['tags'] != '') {
				$args += [ 'tag' => $params['tags'] ];
			}
			$this->debugLog( 'params:'.print_r($args,true) );

			$store_query = new WP_Query( $args );

			if ( $store_query->have_posts() ) {

					while ( $store_query->have_posts() ) {
						$store_query->the_post();

						$_store_name = get_the_title();
						$_store_id = get_the_ID();

						$_store_cat = '';
						$term_obj_list = get_the_terms( $_store_id, 'category' );
						$_store_category = join(', ', wp_list_pluck($term_obj_list, 'name'));
						
						$_store_tags = '';
						$term_obj_list = get_the_terms( $_store_id, 'post_tag' );
						$_store_tags = join(', ', wp_list_pluck($term_obj_list, 'name'));

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
						if ($_store_category) {
							$info .= '<p class=\'cats\'>'.$_store_category.'</p>';
						}
						if ($_store_tags) {
							$info .= '<p class=\'tags\'>'.$_store_tags.'</p>';
						}
						$info .= '</div>"';

						//$info = '"'.$result->ID.'"';
						
						// Don't include locations with no lat/lng
						if ( ($_store_lat != '') && ($_store_lng != '') ) {
							$store_js .= '['.$_store_lat.','.$_store_lng.','.$info.'],'.PHP_EOL;
						}  // End While
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
				$islMaps = new IngeniStoreLocatorMaps( $this->debugMode );
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
			$this->debugLog('writing to:'.$outFile);
			// Now write out to the file
			$mode = "w";

			$log_handle = fopen($outFile, $mode);
			if ($log_handle !== false) {
				fwrite($log_handle, $out);
				fclose($log_handle);
			}
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
				'category' => '',
				'tags' => '',
				'max_results' => 5,
				'show_cats_chkbox' => 0,
				'show_tags_chkbox' => 0,
				'cats_title' => 'Categories',
				'tags_title' => 'Tags',
			), $atts );

			$retHtml = '';
//$this->debugLog('ingeni_store_locator_nearest_shortcode :'.print_r($address_atts,true));
			$retHtml .= '<div id="isl_nearest_form">';

			$options = get_option( 'ingeni_isl_plugin_options' );
			$defaultCountry = $options['default_country'];
			if ($defaultCountry == '') {
				$defaultCountry = "Australia";
			}

			$cats = $this->getTerms('category', $address_atts['category']);
			$tags = $this->getTerms('post_tag', $address_atts['tags']);

//$this->debugLog('cats :'.$cats);
//$this->debugLog('tags :'.$tags);

			$max_results = $address_atts['max_results'];
			
			$retHtml .= '<p>Type a Postcode or Town:</p>';
			$retHtml .= '<p><input type="text" id="loc_lookup" name="loc_lookup"><button id="isl_geo_store_search_btn" onclick="isl_geo(\''.$defaultCountry.'\','.$max_results.',\''.$cats.'\',\''.$tags.'\')"><div id="isl_icon_search"></div><div id="isl_icon_wait"></div></button></p>';

			if ( ($address_atts['show_cats_chkbox'] > 0) && ( strlen(trim($cats)) > 0 ) ) {
				$retHtml .= $this->makeCheckboxes('category', trim($cats), 'isl_chkCats', $address_atts['cats_title']);
			}
			if ( ($address_atts['show_tags_chkbox'] > 0) && ( strlen(trim($tags)) > 0 ) ) {
				$retHtml .= $this->makeCheckboxes('post_tag', trim($tags), 'isl_chkTags', $address_atts['tags_title']);
			}
			//$retHtml .= '</form>';
			$retHtml .= '</div>';
			$retHtml .= '<div id="isl_nearest_list"></div>';
			

			return $retHtml;
		}

		private function getTerms( $type, $terms ) {
			if ( strtolower($terms) == 'all' ) {
				$list = '';
				$my_terms = get_terms( array( 'taxonomy' => $type, 'hide_empty' => true ) );
				$post_idx = 0;
				foreach( $my_terms as $my_term ) {
					$num_posts = $this->countPostsInTerm($type, $my_term->name, 'ingeni_storelocator');
					
					if ($num_posts > 0) {
						if ($post_idx > 0) {
							$list .= ',';
						}
						$list .= $my_term->name;
						$post_idx += 1;
					}

				}
				$terms = $list;
			}

			return $terms;
		}

		private function countPostsInTerm($taxonomy, $term, $postType = 'post') {
			$query = new WP_Query([
					'posts_per_page' => 1,
					'post_type' => $postType,
					'post_status' => 'publish',
					'tax_query' => [
							[
								'taxonomy' => $taxonomy,
								'terms' => $term,
								'field' => 'name'
							]
					]
			]);
			return $query->found_posts;
		}

		private function makeCheckboxes( $type, $list, $div_id, $title ) {
			$retHtml = '';

			$this->debugLog('makeCheckboxes list:'.$list);

			if ( strlen($list) > 0 ) {

				$items = explode(',',$list);
				$this->debugLog('items:'.print_r($items,true));

				if (count($items) > 1) {  // Only show checkboxes if more than one tag or category is specified
					$retHtml = '<div id="'.$div_id.'"><span>'.$title.'</span>';

					foreach( $items as $item ) {
						$retHtml .= '<label for="'.strtolower($item).'"><input type="checkbox" class="'.$div_id.'" value="'.strtolower($item).'" />'.ucwords($item).'</label>';
					}
					$retHtml .= '</div>';
				}
			}
			return $retHtml;
		}

		public function isl_ajax_geoloc_query() {
			$this->debugLog( 'Made it into the Ajax function safe and sound!' );

			$find_this = $_POST['find_this'];
			$find_country = $_POST['country'];
			$max_stores = $_POST['max_stores'];
			$tags = $_POST['tags'];
			$cats = $_POST['cats'];

			$this->debugLog('find_this:'.$find_this);
			
			$retInfo = $this->isl_geolocate_now($find_this, $find_country, $max_stores, $cats, $tags);

			$this->debugLog('retInfo:'.$retInfo);
			echo $retInfo;

			wp_die(); // this is required to terminate immediately and return a proper response
		}


		public function isl_geolocate_now($find_this, $find_country, $max_stores, $cats, $tags) {
			$retInfo = 'Computer says no!';

			$this->debugLog('isl_geolocate_now:'.$find_this. '  country: >'.$find_country.'<  cats >'.$cats.'<  tags >'.$tags.'<');

			$options = get_option( 'ingeni_isl_plugin_options' );
			$geoLocService = strtolower( $options['geoloc_service'] );

			$this->debugLog('geoloc service:'.$geoLocService);

			if ($geoLocService == 'mapbox') {
				include_once(plugin_dir_path( __FILE__ ).'isl_mapbox_support.php');
				$islMapbox = null;

				if (class_exists('IngeniStoreLocatorMapbox')) {
					$islMapbox = new IngeniStoreLocatorMapbox($this->debugMode);
				}
				if ($islMapbox) {
					$retInfo = $islMapbox->isl_geolocate_query($find_this, $find_country, $max_stores, $cats, $tags);
				}
				
			} elseif ($geoLocService == 'mapquest') {
				include_once(plugin_dir_path( __FILE__ ).'isl_mapquest_support.php');
				$islMapquest = null;
	
				if (class_exists('IngeniStoreLocatorMapquest')) {
					$islMapquest = new IngeniStoreLocatorMapquest($this->debugMode);
				}
				if ($islMapquest) {
					$retInfo = $islMapquest->isl_geolocate_query($find_this, $find_country, $max_stores, $cats, $tags);
				}
				
			} else {

				include_once(plugin_dir_path( __FILE__ ).'isl_nominatim_support.php');
				$islNom = null;

				if (class_exists('IngeniStoreLocatorNominatim')) {
					//$this->debugLog('fund the class');
					$islNom = new IngeniStoreLocatorNominatim($this->debugMode);

					if (! get_class($islNom) ) {
						$this->debugLog('class not instantiated!!!!');
					} else {
						$this->debugLog('class instantiated');
					}
				} else {
					$this->debugLog('class does not exist!!!!');
				}
				if ($islNom) {
					$retInfo = $islNom->isl_geolocate_query($find_this, $find_country, $max_stores, $cats, $tags);

				} else {
					$this->debugLog('Could not instantiate IngeniStoreLocatorNominatim');
				}
			}

			return $retInfo;
		}


		public function isl_plugin_action_links($links, $file) {
			if ($file == plugin_basename(__FILE__)) {
				$isl_page_links = '<a href="'.get_admin_url().'options-general.php?page=isl_page_settings">'.__('Settings', 'ingeni_store_locator').'</a>';
				array_unshift($links, $isl_page_links);
			}
	
			return $links;
		}


	} // End of Class
}




function isl_enqueue_admin_scripts() {
	wp_enqueue_style( 'ingeni-isl-admin-css', plugins_url('css/ingeni-isl-admin.css', __FILE__) );
}
add_action('admin_enqueue_scripts', 'isl_enqueue_admin_scripts');


if (class_exists('IngeniStoreLocator')) {
	if ( !isset($storeLocator) ) {
		$storeLocator = new IngeniStoreLocator();
		$storeLocator->register();
	}
}



register_activation_hook(__FILE__, array($storeLocator, 'activate'));
register_deactivation_hook(__FILE__, array($storeLocator, 'deactivate'));
register_uninstall_hook(__FILE__, array($storeLocator, 'uninstall'));

?>