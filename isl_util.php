<?php
if ( !class_exists( 'IngeniStoreUtil' ) ) {
	class IngeniStoreUtil {
		private $debugMode = false;

		public function __construct( $debugOn = false) {
			$this->debugMode = $debugOn;
		}
	
		//
		// Utility functions
		//
		public function startsWith($haystack, $needle) {
			// search backwards starting from haystack length characters from the end
			return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
		}

		public function endsWith($haystack, $needle) {
			// search forward starting from end minus needle length characters
			return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
		}

		public function debugLog( $msg ) {
			if ( $this->debugMode ) {
				$this->fb_log( $msg );
			}
		}
		public function get_local_upload_path() {
			$upload_dir = wp_upload_dir();
			return $upload_dir['baseurl'];
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
	} // End of class
}
?>