<?php
if ( !class_exists( 'IngeniStoreLocatorMaps' ) ) {
	class IngeniStoreLocatorMaps {
		public $debugMode = false;

		public function __construct( $debugOn = false) {
			$this->debugMode = $debugOn;

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

		private function bool2int($value) {
			if ($value)
				return 1;
			else
				return 0;
		}


		private function remoteFileExists( $filename ) {
			$exists = false;

			if ( $filename != '') {
				$this->debugLog('remoteFileExists: filename:'.$filename );
				try {
					$ch = curl_init($filename);
					//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Return data rather than echo to screen
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // Skip SSL Verification - needed for local testing
					curl_exec($ch);
					$curl_err = curl_errno($ch);
					if ($curl_err != 0) {
						throw new Exception('cUrl error '.$curl_err);
					} else {
						$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
						if ($code == 200) {
							$exists = true;
						}
					}
					curl_close($ch);

				} catch (Exception $ex) {
					$this->debugLog('remoteFileExists:'.$ex->getMessage() );
					$exists = false;
				}
			}
			return $exists;
		}


		public function isl_show_open_street_cluster_map( $atts ) {
			$map_atts = shortcode_atts( array(
						'lat' => '-27.7', // Center of Australia
						'lng' => '133.7751',
						'title' => 'Stockists',
						'pin_icon' => '',
						'pin_color' => '#000000',
						'zoom' => 5,
						'store_js_file' => '',
						'minheight' => '250px',
						'minwidth' => '100%',
						'class' => 'islmap',
						'layerprovider' => 'CartoDB.Positron',
						'clustered' => 1,
						'pin_width' => 30,
						'pin_height' => 30,
				), $atts );

			$this->debugLog( 'isl_show_open_street_cluster_map map_atts:'.print_r($map_atts,true) );

			$width = $map_atts['minwidth'];
			$height = $map_atts['minheight'];

			$layer_provider = $map_atts['layerprovider'];

			$lat = $map_atts['lat'];
			$lng = $map_atts['lng'];
			$title = $map_atts['title'];
			$class = $map_atts['class'];

			$zoom = $map_atts['zoom'];
			$pin_icon = $map_atts['pin_icon'];

			$pin_width = $map_atts['pin_width'];
			$pin_height = $map_atts['pin_height'];

			if ( !$this->remoteFileExists( $pin_icon ) ) {
				if ( file_exists( plugin_dir_path( __FILE__ ).$pin_icon ) ) {
					if ( is_file(plugin_dir_path( __FILE__ ).$pin_icon) ) {
						$pin_icon = plugin_dir_url( __FILE__ ).$pin_icon;
					}
				} else {
					$pin_icon = '';
				}
			}

			$pin_data = '';
			if ( $this->endsWith($pin_icon,'.svg') ) {

				$ch_svg = curl_init();
				curl_setopt($ch_svg, CURLOPT_HEADER, 0);
				curl_setopt($ch_svg, CURLOPT_URL, $pin_icon);
				curl_setopt($ch_svg, CURLOPT_RETURNTRANSFER, true);

				$pin_data = curl_exec($ch_svg);
				curl_close($ch_svg);
			}

			$title = $map_atts['title'];
			$pin_color = $map_atts['pin_color'];

			$clustered = $map_atts['clustered'];

			$lat_lng_file = $map_atts['store_js_file'];

			$lat_lng_pairs = array();
			$this->debugLog('to read:'.$lat_lng_file);

			// Read and de-serialize the lat lng pairs
			if ( file_exists( $lat_lng_file ) ) {
				$stores_js = file_get_contents($lat_lng_file);
			}
			
			$this->debugLog('latlng:'.print_r($lat_lng_pairs,true));


			// Enqueue the map clusterer plugin
			if ($clustered != 0) {
				$clustered = 1;
				$clusterpath = get_option('siteurl') . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/leaflet/markercluster/';
				$jspath = $clusterpath . 'leaflet.markercluster.js';
				wp_enqueue_script('markercluster', $jspath, array('jquery'), null, false);
				
				$csspath = $clusterpath . 'MarkerCluster.css';
				wp_enqueue_style('markercluster_css', $csspath);
				$csspath = $clusterpath . 'MarkerCluster.Default.css';
				wp_enqueue_style('markercluster_def_css', $csspath);
			}
	


			if ( !$this->startsWith($pin_color, '#') ) {
				$pin_color = '#'.$pin_color;
			}
			if ( !preg_match('/^#[a-f0-9]{6}$/i', $pin_color) ) {
				$pin_color = '#FF0000';
			}
			if ( (trim( $zoom ) == '') || ( $zoom == 0 ) ) {
				$zoom = 10;
			}

			ob_start();

			$randId = "map-".rand();
			echo('<div id="mapId" style="display:none;">'.$randId.'</div>');
			?>
				<div id="<?php echo($randId); ?>" class="<?php echo($class); ?>" style="min-height:<?php echo($height); ?>;min-width:<?php echo($width); ?>;"></div>
			

				<script type="text/javascript">
					var $jq = jQuery.noConflict();

					$jq( document ).ready( function() {
			
						<?php echo($stores_js); ?>
		
						function hexToRGB(h) {
							let r = 0, g = 0, b = 0;

							// 3 digits
							if (h.length == 4) {
								r = "0x" + h[1] + h[1];
								g = "0x" + h[2] + h[2];
								b = "0x" + h[3] + h[3];
		
							// 6 digits
							} else if (h.length == 7) {
								r = "0x" + h[1] + h[2];
								g = "0x" + h[3] + h[4];
								b = "0x" + h[5] + h[6];
							}
							r = parseInt(r, 16);
							g = parseInt(g, 16);
							b = parseInt(b, 16);

							return "rgb("+ r + "," + g + "," + b + ")";
						}
		
						function mapInit( mapId, lat, lng, place_title, zoom_level, pin_icon, pin_color, layer_provider, clustered, pin_data, pin_width, pin_height, debug_on) {

							isl_map = L.map('<?php echo($randId); ?>').setView([lat,lng], zoom_level);
		
							L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
								attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
								}).addTo(isl_map);

							// add Wikimedia style to map.
							L.tileLayer.provider(layer_provider).addTo(isl_map);

			
							// Let's also add a marker while we're at it
							if (debug_on) {
								console.log('pin icon:'+pin_icon);
								console.log('pin_color:'+pin_color);
							}

							if (pin_icon) {
								var rgb_color = hexToRGB(pin_color);

								if (debug_on) {
									console.log('rgb_color:'+rgb_color);
								}

								console.log('data:'+pin_data);
								var pin_url = pin_icon;
								if (pin_data != '') {
									pin_url = pin_data;
								}

								//var pin_url = 'data:image/svg+xml;base64,' + btoa(pin_icon);
								if ( (pin_width < 5) || (pin_width > 50) ) {
									pin_width = 30;
								}
								if ( (pin_height < 5) || (pin_height > 50) ) {
									pin_height = 30;
								}

								var pin = L.icon({
									iconUrl: pin_url,
									iconSize:[pin_width,pin_height],
									class: 'map_pin'
									});

							} else {
								const size = 15;
								const iconOptions = {
									iconSize:[pin_width,pin_height],
									class : 'map_pin',
									html : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 365 560"><path d="M182.9,551.7c0,0.1,0.2,0.3,0.2,0.3S358.3,283,358.3,194.6c0-130.1-88.8-186.7-175.4-186.9C96.3,7.9,7.5,64.5,7.5,194.6c0,88.4,175.3,357.4,175.3,357.4S182.9,551.7,182.9,551.7z M122.2,187.2c0-33.6,27.2-60.8,60.8-60.8c33.6,0,60.8,27.2,60.8,60.8S216.5,248,182.9,248C149.4,248,122.2,220.8,122.2,187.2z"/></svg>'
								}
								var pin = L.divIcon(iconOptions);
							}
		
							if (clustered) {
								var markers = L.markerClusterGroup({ chunkedLoading: true, showCoverageOnHover: false });
			
								for (var i = 0; i < addressPoints.length; i++) {

									var a = addressPoints[i];
									var title = a[2];

									var marker = L.marker(new L.LatLng( a[0], a[1] ), { icon: pin } );
									marker.bindPopup(title);
									markers.addLayer(marker);
								}

								isl_map.addLayer(markers);

							} else {

								for (var i = 0; i < addressPoints.length; i++) {
									var a = addressPoints[i];
									var title = a[2];

									var marker = L.marker(new L.LatLng( a[0], a[1] ), { icon: pin });
									marker.bindPopup(title);
									isl_map.addLayer(marker);
								}
							}

							// Center map - cannot be positioned at 'Null Island' !!
							if ( (lat != 0) && (lng != 0) ) {
								isl_map.panTo(new L.LatLng(lat, lng));
							}
						}
	
						mapInit("<?php echo($randId); ?>", <?php echo($lat); ?>, <?php echo($lng); ?>, "<?php echo($title); ?>", <?php echo($zoom); ?>, "<?php echo($pin_icon); ?>", "<?php echo($pin_color); ?>", "<?php echo ($layer_provider); ?>", <?php echo($clustered); ?>, "<?php echo($pin_data); ?>", <?php echo($pin_width); ?>, <?php echo($pin_height); ?>, <?php echo($this->bool2int($this->debugMode)); ?>);
					});
				</script>
			<?php
			
			$retHtml = ob_get_contents();
			ob_end_clean();
			return $retHtml;
		}

	} // End of class
}
?>