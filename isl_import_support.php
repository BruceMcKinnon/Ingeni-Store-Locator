<?php

class IngeniStoreCsvImport extends IngeniStoreLocator {
	private static $instance = null;

	private $debugMode = false;

	private $default_header_row = "name,address1,address2,town,state,postcode,country,lat,lng,phone1,phone2,email,website,category,tags,published,id";

	private function __construct( $debugOn = false ) {
		$this->debugMode = $debugOn;

		include_once(plugin_dir_path( __FILE__ ).'isl_util.php');
		include_once(plugin_dir_path( __FILE__ ).'isl_export_support.php');

		$this->debugLog('IngeniStoreCsvImport constructed');

		add_action('admin_menu', array( &$this, 'isl_import_register_submenu_page' ) );

		$instance = $this;
	}

	public static function getInstance( $debugOn = false ) {
		if( !self::$instance ) {
			self::$instance = new IngeniStoreCsvImport( $debugOn );
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

	public function defaultHeaderRow() {
		return $this->defaultHeaderRow;
	}


	//
	// Import form
	//
	public function isl_import_register_submenu_page() {
		add_submenu_page( 'edit.php?post_type=ingeni_storelocator', 'Store Locator', 'CSV Import', 'manage_options', 'store-locator-import-page', array( &$this, 'isl_import_form' ) );
	}

	public function ingeni_isl_import_register_settings() {
		//$this->isl_import_form();
	}


	function isl_import_form() {

		$selected_file = "";
	
		// Current user must be a Contributor at least.
		if ( !current_user_can( 'edit_posts' ) )  {
			wp_die( __( 'You don\'t have sufficient permissions to access this page.' ) );
		}
	
	
		if ( (isset($_POST['isl_import_edit_hidden'])) && ($_POST['isl_import_edit_hidden'] == 'Y') ){
			$errMsg = "";
			
			switch ($_REQUEST['submit']) {
				case "Import Now":
	
					// Selected file
					if ( $_FILES['isl_import_options']['name'] != '' ) {
						$selected_file = $_FILES['isl_import_options']['name'];
						$tmp_file = $_FILES['isl_import_options']['tmp_name'];
						$size = $_FILES['isl_import_options']['size'];

						fb_log('files:'.print_r($_FILES['isl_import_options'],true));

						$options = get_option( 'ingeni_isl_plugin_options' );
						$skip_header_row = $options['skip_first_line'];
	
						$import_count = $this->isl_upload_import( $selected_file, $tmp_file, $size, $skip_header_row );

					}

				break;

				case "Export to CSV":
					if (class_exists('IngeniStoreCsvExport')) {
						$islExport = new IngeniStoreCsvExport( $this->debugMode );
					}
					$islExport->isl_download_export();

				break;

			}
		}
	

		echo ('<div id="wpbody" role="main"><div id="wpbody-content">');
			echo( '<h2>Ingeni Store Locator - CSV Importer</h2>' );
			echo('<form action="" method="post" enctype="multipart/form-data" class="isl_settings">'); 
				echo('<input type="hidden" name="isl_import_edit_hidden" value="Y">');

				echo ('<h2>CSV Import</h2>');
				echo ('<table class="form-table" role="presentation"><tbody>');
					echo('<tr><th scope="row">Import File</th><td><input type="file" name="isl_import_options"></td></tr>');
				echo('</tbody></table>');
				
				echo('<div class="submit_wrap"><input type="submit" name="submit" id="submit" class="button button-primary" value="Import Now">');
				echo('<div class="submit_wrap"><input type="submit" name="submit" id="export" class="button button-primary" value="Export to CSV">');
				echo('<div id="loading_anim"><div class="centered"><div class="blob-1"></div><div class="blob-2"></div></div></div></div>');
			echo('</form>');

		echo('</div><div class="clear"></div></div>');
	
	}





	//
	// Support for importing files
	//
	function isl_upload_import( $selected_file, $tmp_file, $size, $skip_header_row ) {
		$this->debugLog('isl_upload_import....');

		try {
			$importCount = 0;
			$errMsg = "";
			$allowedTypes = array("csv");
			$zip_path = "";



			$this->debugLog('isl_upload_import: '. $selected_file.' | ' .$tmp_file.' | ' .$size.' | ' .$skip_header_row);

			if ( $this->isl_upload_to_server( $selected_file, $tmp_file, $fileSize, $allowedTypes, $errMsg, $zip_path )  == 0 ) {
				$this->debugLog( 'upload err: '.$errMsg );
			} else {
				$uploadedFile = $errMsg;
				$this->debugLog('the file was uploaded: '.$uploadedFile);
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

    	echo '<div class="updated"><p>Imported '.$importCount.' stores.</p></div>';

		} catch (Exception $e) {
			$this->debugLog('isl_upload_import: '.$this->e->message);
			echo '<div class="error"><p>ERR: '.$this->e->message.'</p></div>';
		}

		return $importCount;
	}

	function isl_get_post_title_count($post_title) {
		$count = 0;

		$args = array(
			'post_type' => 'ingeni_storelocator',
			'post_status' => array( 'publish' ),
			'posts_per_page' => -1,
			'post_title' => $post_title,
		);
		$stores = new WP_Query( $args );
		if ( $stores->have_posts() ) {
			$count = $stores->post_count();
		}

		wp_reset_postdata();

		return $count;
	}

	function isl_save_item($item) {
		$this->debugLog(print_r($item,true));

		setlocale(LC_ALL, "en_AU");


		$post_id = -1;


		$exists = array_key_exists("name",$item);
/*
ob_start();
var_dump($exists);
$dumpit = ob_get_clean();
$this->debugLog('dumpit:'.print_r($dumpit,true));
*/

		if ($exists !== true) {
			$this->debugLog('Name key does not exist');

			$keys = array_keys($item);
			$this->debugLog(print_r($keys,true));

			foreach( $keys as $key ) {
				$key = trim(strval($key));
				$this->debugLog('testing for name key:>'.$key.'<');
				if ( strcasecmp( $key == "name") == 0 ) {
					$this->debugLog('name key is true');
					$exists = true;
					break;
				}
			}


		}
		
		if ($exists) {
			$content = '';
			if (array_key_exists('content',$item)) {
				$content = $item['content'];
			}
			$post_status = 'publish';
			if (array_key_exists('published',$item)) {
				$post_status = $item['published'];
			}

			$post_id = -1;
			if (array_key_exists('id',$item) ) {
				$post_id = intval($item['id']);
				$this->debugLog('isl_save_item exsiting post_id:'.$post_id);
			}


			// If a post ID is provided, then do a direct lookup, otherwise lookup the port title.
			if ($post_id > 0) {
				$existingPost = get_post($item['id'], OBJECT, 'ingeni_storelocator');
			} else {
				// If doing a post title lookup, make sure the title is unique by adding the town name
				$post_count = $this->isl_get_post_title_count($item['name']);
				if ($post_count > 0) {
					$item['name'] = $item['name'] . ' ' . $item['town'];
				}

				$existingPost = get_page_by_title($item['name'], OBJECT, 'ingeni_storelocator');
			}

			if ( $existingPost ) {
				$post_id = $existingPost->ID;
				
				$this->debugLog('isl_save_item existing:'.$post_id);
			} else {
				$newPost = array(
					'post_title' => $item['name'],
					'post_content' => $content,
					'post_status' => $post_status,
					'post_type' => 'ingeni_storelocator'
				);
				
				// Insert the post into the database
				$err = false;
				$post_id = wp_insert_post( $newPost, $err);
				
				if ($post_id == 0) {
					$this->debugLog('isl_save_item ERR:'.print_r($err,true));
				} else {
					$this->debugLog('isl_save_item new:'.$post_id);
				}

				
			}

			if( !is_wp_error($post_id) ) {

				if (array_key_exists('category',$item)) {
					$catName = sanitize_text_field($item['category']);
					$catID = get_cat_ID( $catName );
					if ($catID < 1) {
						$catID = wp_create_category( $catName, 0 );
					}
					if ($catID > 0) {
						wp_set_post_categories( $post_id, array( $catID ) );
					}
				}

				if (array_key_exists('tags',$item)) {
					$tags = sanitize_text_field($item['tags']);
					wp_set_post_tags( $post_id, $tags );
				}


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
				if (array_key_exists('contact_name',$item)) {
					update_post_meta( $post_id, '_isl_contact_name', sanitize_text_field($item['contact_name']) );
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
				$lat = $lng = '';
				if (array_key_exists('lat',$item)) {
					if (is_numeric($item['lat'])) {
						$lat = $item['lat'];
					}
				}
				if (array_key_exists('lng',$item)) {
					if (is_numeric($item['lng'])) {
						$lng = $item['lng'];
					}
				}

				// Bad lat/lng provided - will we try and geolocate now??
				if ( (!is_numeric($lat)) || (!is_numeric($lng)) ) {
					$options = get_option( 'ingeni_isl_plugin_options' );
					$geoloc_import = $options['geoloc_import'];

					if ($geoloc_import) {
						$find_this = sanitize_text_field($item['address1']).' '.sanitize_text_field($item['address2']).' '.sanitize_text_field($item['town']).' '.sanitize_text_field($item['state']).' '.sanitize_text_field($item['postcode']);
						$find_country = $item['country'];

						// isl_geolocate_now returns a JSON structure
						$retInfo = $this->isl_geolocate_now($find_this, $find_country, 0, '','');
						$jsonInfo = json_decode($retInfo);

						if ($jsonInfo->Count == 1) {
							$lat = $jsonInfo->Stores[0]->lat;
							$lng = $jsonInfo->Stores[0]->lng;
						}
					}
				}

				if ( (is_numeric($lat)) && (is_numeric($lng)) ) {
					update_post_meta( $post_id, '_isl_lat', $lat );
					update_post_meta( $post_id, '_isl_lng', $lng );
				}

			} else {
				$this->debugLog( 'ERR: '.$postId->get_error_message() );
				$post_id = 0;
			}

		} else {
			$this->debugLog('isl_save_item: No name provided!');
		}

		$this->debugLog('isl_save_item post_id='.$post_id);
		return $post_id;
	}


	function isl_upload_to_server( $selectedFile, $tmpFile, $fileSize, $allowed_types = array("csv"), &$err_message, &$zip_path ) {
		try {
				$upl_folder = wp_upload_dir();

				$this->debugLog('isl_upload_to_server selected: '. $selectedFile);

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

				$this->debugLog('isl_upload_to_server wp_upload_bits: '. $target_file.' | '.$tmpFile);

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
				$this->debugLog('A err msg:' .$uploadOk.' = '.$err_message.' = ' . $upload['url']);


				if ( $uploadOk > 0) {
						$path_parts = pathinfo($upload['file']);
						$this->debugLog('path parts='.print_r($path_parts,true));
						if ( strtolower($path_parts['extension']) == "zip" ) {
								$temp_path = $path_parts['dirname'] . '/' . date('is');
								$this->debugLog('temp dir='.$temp_path);

								mkdir($temp_path);
								if ( $this->unzip_upload($upload['file'], $temp_path) > 0 ) {
										// We have unzipped, so find the csv file;
										// Just in case, check to see if there has been a folder created
										// with the same name as the zip file.
										if ( is_dir( $temp_path . '/' . $path_parts['filename'] ) ) {
												$temp_path .= '/' . $path_parts['filename'];
										}

										$tmp_files = scandir($temp_path);
										$this->debugLog('files='.print_r($tmp_files,true));
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
					$this->debugLog('isl_upload_to_server error: '.$err_message);
				}

		} catch (Exception $e) {
			$this->debugLog('isl_upload_to_server: '.$e->message);
		}
		// Remove the tmp file
		unset( $tmpFile );

		$this->debugLog('isl_upload_to_server: OK='. $uploadOk);
		
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
	function isl_csv_to_array($filename='', $skip_header_row=0, $delimiter=',') {

		$this->debugLog('isl_csv_to_array:'.$filename. ' skip_header:'.$skip_header_row.' delimiter:'.$delimiter);


		if( !file_exists($filename) || !is_readable($filename) ) {
			return FALSE;
		}
	
		$header = NULL;
		$data = array();

		if ($skip_header_row == 0) {
			$header = explode(',', $this->defaultHeaderRow);
		}

		setlocale(LC_ALL, 'en_AU');

		if (($handle = fopen($filename, 'r')) !== FALSE) {
			while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
				$this->debugLog('isl_csv_to_array:'.print_r($row,true));
				//$row = array_map("utf8_encode", $row); // extra encoding handling
				if ( !$header ) {
					//$this->debugLog('isl_csv_to_array: setting this row to be the header');
					$header = array_map('trim', $row);
					$this->debugLog('isl_csv_to_array header[]:'.print_r($header,true));
				} else {
					$data[] = array_combine($header, $row);
					$skip_header_row = 1;
				}
			}
			fclose($handle);
			//$this->debugLog('isl_csv_to_array data[]:'.print_r($data,true));
		} else {
			$this->debugLog('isl_csv_to_array - could not open:'.$filename);
		}

		if ( array_key_exists('name',$data[0]) ) {
			$this->debugLog('isl_csv_to_array name exists');
		} else {
			$this->debugLog('isl_csv_to_array name does not exist.');
		}

		//$this->debugLog('isl_csv_to_array:'.print_r($data,true));
		return $data;
	}

} // End of class

?>