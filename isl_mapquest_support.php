<?php

if ( !class_exists( 'IngeniStoreLocatorMapquest' ) ) {
	class IngeniStoreLocatorMapquest {
		private $debugMode = false;

		public $stores_list;

		public function __construct( $debugOn = false) {
			$this->debugMode = $debugOn;
			$this->debugLog('IngeniStoreLocatorMapquest constructed');
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




		private function isl_get_stores() {
			global $wpdb;

			$stores_list = array();

			$sql = "SELECT ID, post_title FROM $wpdb->posts WHERE (post_type='ingeni_storelocator')AND(post_status='publish') ";

			$results = $wpdb->get_results( $wpdb->prepare( $sql ) );
	
			if ($results) {
				foreach($results as $result) {

					$_store_id = $result->ID;
					$_store_name = $result->post_title;
					$_store_lat = get_post_meta( $_store_id, '_isl_lat', true );
					$_store_lng = get_post_meta( $_store_id, '_isl_lng', true );
					$_store_addr = trim(get_post_meta( $_store_id, '_isl_street_address1', true ) . ' ' . get_post_meta( $_store_id, '_isl_street_address2', true ) );
					$_store_town = trim( get_post_meta( $_store_id, '_isl_town', true ) . ' ' . get_post_meta( $_store_id, '_isl_state', true ) . ' ' . get_post_meta( $_store_id, '_isl_postcode', true ) );
					$_store_phone = get_post_meta( $_store_id, '_isl_phone1', true );

					array_push( $stores_list, array('id' => $_store_id, 'lat' => $_store_lat, 'lng' => $_store_lng, 'name' => $_store_name, 'addr' => $_store_addr, 'town' => $_store_town, 'phone' => $_store_phone, 'distance' => 0) );
				}
			}
			$this->debugLog("stores_list:".print_r($stores_list,true));
			return $stores_list;
		}



		public function isl_geolocate_query($find_this, $country, $max_stores = 5) {
			global $wpdb;

			$this->debugLog('isl_ajax_Mapquest_query:'.$find_this. '  country: >'.$country.'<');

			$retInfo = array('Message' => 'Nothing Found', 'Count' => 0);

			$geo_location = $this->isl_geocode_lookup( $find_this, '', '', '', $country, '' );
			$this->debugLog('geoLoc JSON:'.print_r($geo_location,true) );

			$geo_start_location = json_decode($geo_location,true);
			$this->debugLog('geoLoc:'.print_r($geo_start_location,true) );

			$location_name = 'Nothing found!';
			$location_lat = 0;
			$location_lng = 0;

			if (count($geo_start_location) > 0) {
				$this->debugLog('startLocation:'.$geo_start_location['results'][0]['locations'][0]['latLng']['lat'].' / '.$geo_start_location['results'][0]['locations'][0]['latLng']['lng'] );
				
				$location_name = $geo_start_location['results'][0]['providedLocation']['location'];
				$location_lat = floatval($geo_start_location['results'][0]['locations'][0]['latLng']['lat']);
				$location_lng = floatval($geo_start_location['results'][0]['locations'][0]['latLng']['lng']);

				if ($max_stores > 0) {
					// Return a list of nearest stores
					$this->debugLog('startLocation:'.$location_lat.' / '.$location_lng );

					if (class_exists('IngeniStoreLocatorDistance')) {
						$islDistance = new IngeniStoreLocatorDistance($this->debugMode);
					}

					if ( ($location_lat + $location_lng) > 0 ) {
						$my_stores = array();
						$my_stores = $this->isl_get_stores();
						if ($my_stores) {
							$idx = 0;
							$store_count = count($my_stores);
							for ( $idx = 0; $idx < $store_count; ++$idx ) {
								$my_stores[$idx]['distance'] = $islDistance->isl_getDistanceBetweenPoints( floatval($my_stores[$idx]['lat']), floatval($my_stores[$idx]['lng']), $location_lat, $location_lng, 'km');
							}

							// Sort by distance - float value
							usort(
								$my_stores, 
								function($a, $b) {
										$result = 0;
										if ($a['distance'] > $b['distance']) {
												$result = 1;
										} else if ($a['distance'] < $b['distance']) {
												$result = -1;
										}
										return $result; 
								}
							);
						}

						if (count($my_stores) > $max_stores) {
							$ret_stores = array_slice($my_stores,0,$max_stores);
						} else {
							$ret_stores = $my_stores;
						}
					}
				} else {
					// Only returning a single lat/lng for a geolocation lookup
					$store_count = 1;
					$ret_stores = array( array('lat' => $location_lat, 'lng' => $location_lng, 'name' => $location_name ) );
				}

				if ($ret_stores) {
					$store_count = count($ret_stores);
					$retInfo = json_encode( array('Message' => 'OK', 'Count' => $store_count, 'Stores' => $ret_stores) );
				}
			}

			$this->debugLog('retInfo:'.$retInfo);
			return $retInfo;
		}



		public function isl_geocode_lookup( $generic, $street, $city, $state, $country, $postalcode ) {
			$this->debugLog('isl_geocode_lookup: '.$generic.'|'.$street.'|'.$city.'|'.$state.'|'.$country.'|'.$postalcode);
			$search_for = '';

			$options = get_option( 'ingeni_isl_plugin_options' );
			$ingeni_isl_mapquest_api_key = $options['mapquest_api_key'];

			if ($generic) {

				$search_for = 'location='.urlencode($generic);

				if ($country) {
					$search_for .= ','.urlencode($country);
				}

			} else {
				$search_for = 'location=';
				if ($street) {
					$search_for .= urlencode($street).',';
				}
				if ($city) {
					$search_for .= urlencode($city).',';
				}
				if ($state) {
					$search_for .= urlencode($state).',';
				}
				if ($postalcode) {
					$search_for .= urlencode($postalcode).',';
				}
				if ($country) {
					$search_for .= urlencode($country).'&';
				}
				$search_for = str_ireplace(',,',',',$search_for);
			}
			$this->debugLog('search for:'.$search_for);

			$search_for = 'key='.$ingeni_isl_mapquest_api_key.'&inFormat=kvp&outFormat=json&'.$search_for;

			// Hit up Mapquest
			$url = 'https://www.mapquestapi.com/geocoding/v1/address?'.$search_for;
			$curl = curl_init( $url );
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

			$agent = 'isl_' . get_bloginfo('name');
			$agent = str_ireplace(' ','_',$agent);
			curl_setopt($curl, CURLOPT_USERAGENT, 'User-Agent: '.$agent.'\r\n');

			$this->debugLog('url:'.$url);

			$retCurl = curl_exec($curl);
			curl_close($curl);

			$this->debugLog('curl:'.print_r($retCurl,true));
			
			return $retCurl;
		}



	} // End of Class
}
?>