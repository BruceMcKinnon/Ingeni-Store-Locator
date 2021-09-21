<?php

class IngeniStoreCsvExport extends IngeniStoreCsvImport {
	private static $instance = null;

	private $debugMode = false;

	function __construct( $debugOn = false ) {
		$this->debugMode = $debugOn;

		include_once(plugin_dir_path( __FILE__ ).'isl_util.php');

		$this->debugLog('IngeniStoreCsvExport constructed');

		$instance = $this;
	}

	public static function getInstance( $debugOn = false ) {
		if( !self::$instance ) {
			self::$instance = new IngeniStoreCsvExport( $debugOn );
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
	// Support for exporting files
	//
	function isl_download_export() {
		$this->debugLog('isl_download_export....');

		try {
			/*
			name
			address1
			address2
			town
			state
			postcode
			country
			lat
			lng
			phone1
			phone2
			email
			website
			category
			tags
			published
			id
			*/

			//$header_row = 'name,address1,address2,town,state,postcode,country,lat,lng,phone1,phone2,email,website,category,tags,published';
			$header_row = $this->defaultHeaderRow();

			// Set up the export file
			$upload_dir = wp_upload_dir();
			date_default_timezone_set('Australia/Sydney');
			$csvFile = $upload_dir['basedir'] . '/store_export_' . date("H_i_s") . '.csv';
$this->debugLog('isl_download_export: '.$csvFile);
			// Now write out the header row to the file
			setlocale(LC_ALL, 'en_AU');
			$csv_handle = fopen($csvFile, "w");
			if ($csv_handle !== false) {
				fwrite($csv_handle, $header_row."\r\n");
			}

			$this->debugLog('header written....');

            // Get a list of store posts
            $args = array(
				'post_type' => 'ingeni_storelocator',
				'post_status' => array( 'any' ),
				'posts_per_page' => -1,
			);
			$stores = new WP_Query( $args );
$this->debugLog(print_r($stores->request,true));
			$export_count = 0;
			if ( $stores->have_posts() ) {

				while ( $stores->have_posts() ) {
					$stores->the_post();
//$this->debugLog(print_r($store,true));

					$store_id = get_the_ID();
					$name = "\"".get_the_title()."\"";
					$published = 0;
//$this->debugLog($store_id.': '.$name.' - '.get_post_status() );
					if (get_post_status() == 'publish') {
						$published = 1;
					}

					$catName = '';
					$cats = wp_get_post_categories( $store_id, array( 'fields' => 'names' ));
//$this->debugLog(print_r($cats,true));
					if ( $cats ) {
						$catName = $cats[0];
					}

					$tagName = '';
					$tags = wp_get_post_tags( $store_id );
					if (is_array( $tags )) {
						$idx = 0;
						for ( $idx = 0; $idx < count($tags); ++$idx ) {
							$tagName .= $tags[$idx]->name . ',';
						}
						$tagName = "\"".substr($tagName,0,strlen($tagName)-1)."\"";
					}

					$addr1 = "\"".get_post_meta( $store_id, '_isl_street_address1', true )."\"";
					$addr2 = "\"".get_post_meta( $store_id, '_isl_street_address2', true )."\"";

					$town = get_post_meta( $store_id, '_isl_town', true );
					$state = get_post_meta( $store_id, '_isl_state', true );
					$postcode = get_post_meta( $store_id, '_isl_postcode', true );
					$country = get_post_meta( $store_id, '_isl_country', true );

					$phone1 = get_post_meta( $store_id, '_isl_phone1', true );
					$phone2 = get_post_meta( $store_id, '_isl_phone2', true );
					$email = get_post_meta( $store_id, '_isl_email', true );
					$web = get_post_meta( $store_id, '_isl_web', true );
					$lat = get_post_meta( $store_id, '_isl_lat', true );
					$lng = get_post_meta( $store_id, '_isl_lng', true );

					//$row = $name.','.$addr1.','.$addr2.','.$town.','.$state.','.$postcode.','.$country.','.$lat.','.$lng.','.$phone1.','.$phone2.','.$email.','.$web.','.$catName.','.$tagName.','.$published.','.$store_id;
					$row = array($name,$addr1,$addr2,$town,$state,$postcode,$country,$lat,$lng,$phone1,$phone2,$email,$web,$catName,$tagName,$published,$store_id);

					if ($csv_handle !== false) {
						fputcsv($csv_handle, $row);
						$export_count += 1;

						$this->debugLog('['.$export_count.'] '.$name);
					}
				}
			}

			wp_reset_postdata();
			fclose($csv_handle);

    		echo '<div class="updated"><p>Exported '.$export_count.' stores to '.$csvFile.'</p></div>';

			$this->isl_download_now($csvFile);

		} catch (Exception $e) {
			$this->debugLog('isl_download_export: '.$this->e->message);
			echo '<div class="error"><p>ERR: '.$this->e->message.'</p></div>';
		}

		return $export_count;
	}



	// Download the file using jQuery - avoids 'headers already sent' errors.
	function isl_download_now($dnl_filename) {
		?>
		<script>
		var my_url = '<?php echo($dnl_filename) ?>';
	
		var link = document.createElement("a");
		link.download = '<?php echo ( basename($dnl_filename) ); ?>';
		link.href = my_url;
	
		link.click();
	
		console.log(my_url);
	
		</script>
	
		<?php
		return;
	}
		

} // End of class

?>