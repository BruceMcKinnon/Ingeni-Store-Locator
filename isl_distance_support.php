<?php

if ( !class_exists( 'IngeniStoreLocatorDistance' ) ) {
	class IngeniStoreLocatorDistance {
		private $debugMode = false;

		public function __construct( $debugOn = false) {
			$this->debugMode = $debugOn;
			$this->debugLog('IngeniStoreLocatorDistance constructed');
		}

		
		//
		// Utility functions
		//
		public function debugLog( $msg ) {
			if (class_exists('IngeniStoreUtil')) {
				$islUtil = new IngeniStoreUtil( $this->debugMode );
			}
			if ($islUtil) {
				$islUtil->debugLog($msg);
			}
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

			$this->debugLog('unit:'.$unit);

			if ( strtolower($unit) == 'km')  { 
				$distance = $distance * 1.609344; 
			}

			return (round($distance,2)); 
		}


	} // End of Class
}