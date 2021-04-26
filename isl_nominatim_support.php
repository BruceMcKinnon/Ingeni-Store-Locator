<?php

if ( !class_exists( 'IngeniStoreLocatorNominatim' ) ) {
	class IngeniStoreLocatorNominatim {

		public $stores_list;

		public function __construct() {
			//fb_log('nom constructed');
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


		private function isl_get_stores() {
			global $wpdb;

			$stores_list = array();

			$sql = "SELECT ID, post_title FROM $wpdb->posts WHERE (post_type='ingeni_storelocator')AND(post_status='publish') ";
//fb_log('sql:'.$sql);
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
//fb_log("stores_list:".print_r($stores_list,true));
			return $stores_list;
		}



		public function isl_ajax_nominatim_query($find_this, $max_stores = 5) {
			global $wpdb;

//fb_log('isl_ajax_nominatim_query:'.$find_this);

			$retInfo = array('Message' => 'Nothing Found', 'Count' => 0);

			$geo_location = $this->isl_geocode_lookup( $find_this, '', '', '', 'AU', '' );
//fb_log('geoLoc JSON:'.print_r($geo_location,true) );

			$geo_start_location = json_decode($geo_location,true);
//fb_log('geoLoc:'.print_r($geo_start_location,true) );

			$location_name = 'Nothing found!';
			$location_lat = 0;
			$location_lng = 0;

			if (count($geo_start_location) > 0) {
//fb_log('startLocation:'.$geo_start_location[0]['lat'].' / '.$geo_start_location[0]['lon'] );
				$location_name = $geo_start_location[0]['display_name'];
				$location_lat = floatval($geo_start_location[0]['lat']);
				$location_lng = floatval($geo_start_location[0]['lon']);
			}
//fb_log('startLocation:'.$location_lat.' / '.$location_lng );

			if ( ($location_lat + $location_lng) > 0 ) {
				$my_stores = array();
				$my_stores = $this->isl_get_stores();
				if ($my_stores) {
					$idx = 0;
					$store_count = count($my_stores);
					for ( $idx = 0; $idx < $store_count; ++$idx ) {
						$my_stores[$idx]['distance'] = $this->isl_getDistanceBetweenPoints( floatval($my_stores[$idx]['lat']), floatval($my_stores[$idx]['lng']), $location_lat, $location_lng, 'km');
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

				if ($ret_stores) {
					$store_count = count($ret_stores);
					$retInfo = json_encode( array('Message' => 'OK', 'Count' => $store_count, 'Stores' => $ret_stores) );
				}
			}

//fb_log('retInfo:'.$retInfo);
			return $retInfo;
		}



		public function isl_geocode_lookup( $generic, $street, $city, $state, $country, $postalcode ) {
//fb_log ('isl_geocode_lookup: '.$generic.'|'.$street.'|'.$city.'|'.$state.'|'.$country.'|'.$postalcode);
			$search_for = '';

			if ($generic) {
				$search_for = 'q='.urlencode($generic);

				if ($country) {
					$search_for .= ','.urlencode($country).'&';
				}

			} else {
				if ($street) {
					$search_for .= 'street='.urlencode($street).'&';
				}
				if ($city) {
					$search_for .= 'city='.urlencode($city).'&';
				}
				if ($state) {
					$search_for .= 'state='.urlencode($state).'&';
				}
				if ($country) {
					$search_for .= 'country='.urlencode($country).'&';
				}
				if ($postalcode) {
					$search_for .= 'postalcode='.urlencode($postalcode).'&';
				}
			}
//fb_log('search for:'.$search_for);

			// Get rid of a tralling space
			if (substr($search_for, strlen($search_for)-1,1) != '&') {
				$search .= '&';
			}

			$search_for .= 'format=json';


			// Hit up Nominatim
			$url = 'https://nominatim.openstreetmap.org/search?'.$search_for;
			$curl = curl_init( $url );
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_USERAGENT, 'User-Agent: IngeniStoreLocator\r\n');

//fb_log('url:'.$url);

			$retCurl = curl_exec($curl);
			curl_close($curl);


//fb_log('curl:'.print_r($retCurl,true));

			
			return $retCurl;
		}


		// Find the distance between 2 locations from its coordinates.
		// 
		// @param latitude1 LAT from point A
		// @param longitude1 LNG from point A
		// @param latitude2 LAT from point A
		// @param longitude2 LNG from point A
		// 
		// @return Float Distance in Kilometers.
		//
		function isl_getDistanceBetweenPoints($lat1, $lng1, $lat2, $lng2, $unit = 'km') {
			$theta = $lng1 - $lng2;
			$distance = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));

			$distance = acos($distance); 
			$distance = rad2deg($distance); 
			$distance = $distance * 60 * 1.1515;
 //fb_log('unit:'.$unit);
			if ( strtolower($unit) == 'km')  { 
				$distance = $distance * 1.609344; 
			}

			return (round($distance,2)); 
		}



		// Find the closest stores
		function isl_GetClosestStores($lat1, $lng1) {
			global $wpdb;


			$sql = $query = "SELECT * FROM ( SELECT *, 
				( ( ( acos( sin(( $lat * pi() / 180)) * sin(( `lat` * pi() / 180)) + cos(( $lat * pi() /180 )) * cos(( `lat` * pi() / 180)) * cos((( $lon - `lng`) * pi()/180))) ) * 180/pi() ) * 60 * 1.1515 * 1.609344 )
					as distance FROM `markers`
			) markers
			WHERE distance <= $distance
			LIMIT 15";
		}


	} // End of Class
}
?>