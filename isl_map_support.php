<?php
if ( !class_exists( 'IngeniStoreLocatorMaps' ) ) {
	class IngeniStoreLocatorMaps {

		public function __construct() {

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

		public function isl_show_open_street_cluster_map( $atts ) {
			$map_atts = shortcode_atts( array(
						'lat' => '-27.7', // Center of Australia
						'lng' => '133.7751',
						'title' => 'Stockists',
						'pin_icon' => 'map-pin.svg',
						'pin_color' => '#000000',
						'zoom' => 5,
						'store_js_file' => '',
						'minheight' => '250px',
						'minwidth' => '100%',
						'class' => 'islmap',
						'layerprovider' => 'CartoDB.Positron'
				), $atts );


			$width = $map_atts['minwidth'];
			$height = $map_atts['minheight'];

			$layer_provider = $map_atts['layerprovider'];

			$lat = $map_atts['lat'];
			$lng = $map_atts['lng'];
			$title = $map_atts['title'];
			$class = $map_atts['class'];

			$zoom = $map_atts['zoom'];
			$pin_icon = $map_atts['pin_icon'];
			$title = $map_atts['title'];
			$pin_color = $map_atts['pin_color'];

			$lat_lng_file = $map_atts['store_js_file'];

			$lat_lng_pairs = array();
//fb_log('to read:'.$lat_lng_file);
			// Read and de-serialize the lat lng pairs
			if ( file_exists( $lat_lng_file ) ) {
				$stores_js = file_get_contents($lat_lng_file);
			}
//fb_log('latlng:'.print_r($lat_lng_pairs,true));

//fb_log('json encode:'.$json_latlng);
			// Enqueue the map clusterer plugin
			$clusterpath = get_option('siteurl') . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/leaflet/markercluster/';
			$jspath = $clusterpath . 'leaflet.markercluster.js';
			wp_enqueue_script('markercluster', $jspath, array('jquery'), null, false);
			
			$csspath = $clusterpath . 'MarkerCluster.css';
			wp_enqueue_style('markercluster_css', $csspath);
			$csspath = $clusterpath . 'MarkerCluster.Default.css';
			wp_enqueue_style('markercluster_def_css', $csspath);
	


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
		
						function mapInit( mapId, lat, lng, place_title, zoom_level, pin_icon, pin_color, layer_provider) {

							isl_map = L.map('<?php echo($randId); ?>').setView([lat,lng], zoom_level);
		
							L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
								attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
								}).addTo(isl_map);

							// add Wikimedia style to map.
							L.tileLayer.provider(layer_provider).addTo(isl_map);

			
							// Let's also add a marker while we're at it
							var pin = pin_icon;
//console.log('pin icon:'+pin_icon);
//console.log('pin_color:'+pin_color);
							if (pin_icon) {
								var rgb_color = hexToRGB(pin_color);
//console.log('rgb_color:'+rgb_color);
								var svg_pin = '<svg version="1.1" id="mapmarker" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 365 560" enable-background="new 0 0 365 560" xml:space="preserve"><g><path class="fill_color" style="fill:' + rgb_color + ';" d="M182.9,551.7c0,0.1,0.2,0.3,0.2,0.3S358.3,283,358.3,194.6c0-130.1-88.8-186.7-175.4-186.9 C96.3,7.9,7.5,64.5,7.5,194.6c0,88.4,175.3,357.4,175.3,357.4S182.9,551.7,182.9,551.7z M122.2,187.2c0-33.6,27.2-60.8,60.8-60.8 c33.6,0,60.8,27.2,60.8,60.8S216.5,248,182.9,248C149.4,248,122.2,220.8,122.2,187.2z"/></g></svg>';
								var pin_url = encodeURI('data:image/svg+xml,' + svg_pin);

								var pin = L.icon({
									iconUrl: pin_url,
									iconSize:[30,30]
									});

							} else {
								pin = {
									path: "m 768,896 q 0,106 -75,181 -75,75 -181,75 -106,0 -181,-75 -75,-75 -75,-181 0,-106 75,-181 75,-75 181,-75 106,0 181,75 75,75 75,181 z m 256,0 q 0,-109 -33,-179 L 627,-57 q -16,-33 -47.5,-52 -31.5,-19 -67.5,-19 -36,0 -67.5,19 Q 413,-90 398,-57 L 33,717 Q 0,787 0,896 q 0,212 150,362 150,150 362,150 212,0 362,-150 150,-150 150,-362 z",
									fillColor: pin_color,
									fillOpacity: 1,
									strokeWeight: 0,
									scale: 0.03,
									rotation: 180
								}
							}
		

							var markers = L.markerClusterGroup({ chunkedLoading: true, showCoverageOnHover: false });
		
							for (var i = 0; i < addressPoints.length; i++) {

								var a = addressPoints[i];
								var title = a[2];

								var marker = L.marker(new L.LatLng( a[0], a[1] ), { title: title, icon: pin });
								marker.bindPopup(title);
								markers.addLayer(marker);
								
							}

							isl_map.addLayer(markers);

							// Center map - cannot be positioned at 'Null Island' !!
							if ( (lat != 0) && (lng != 0) ) {
								isl_map.panTo(new L.LatLng(lat, lng));
							}
						}
			
						mapInit("<?php echo($randId); ?>", <?php echo($lat); ?>, <?php echo($lng); ?>, "<?php echo($title); ?>", <?php echo($zoom); ?>, "<?php echo($pin_icon); ?>", "<?php echo($pin_color); ?>", "<?php echo ($layer_provider); ?>");
					});
				</script>
			<?php
			
			$retHtml = ob_get_contents();
			ob_end_clean();

			
			return $retHtml;
		}

	}
}
?>