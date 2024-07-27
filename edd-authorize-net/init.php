<?php

if ( ! class_exists( 'Easy_Digital_Downloads_Authorize_Net' ) ) :

	/**
	 * Easy_Digital_Downloads_Authorize_Net Class. This is where everything is included and initialized for the extension.
	 *
	 * @since 1.0.0
	 */
	final class Easy_Digital_Downloads_Authorize_Net {

		/**
		 * @var Easy_Digital_Downloads_Authorize_Net The one true Easy_Digital_Downloads_Authorize_Net
		 *
		 * @since 1.0.0
		 */
		private static $instance;

		/**
		 * Main Easy_Digital_Downloads_Authorize_Net Instance.
		 *
		 * Ensures that only one instance of Easy_Digital_Downloads_Authorize_Net exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @since 1.0.0
		 *
		 * @static
		 * @staticvar array $instance
		 *
		 * @uses Easy_Digital_Downloads_Authorize_Net::setup_constants() Setup constants.
		 * @uses Easy_Digital_Downloads_Authorize_Net::include_files() Setup required files.
		 * @see edd_authorize_net()
		 *
		 * @return object|Easy_Digital_Downloads_Authorize_Net The one true Easy_Digital_Downloads_Authorize_Net
		 */
		public static function instance( $file = '' ) {

			// Return if already instantiated
			if ( self::is_instantiated() ) {
				return self::$instance;
			}

			// Setup the singleton
			self::setup_instance( $file );

			// Bootstrap
			self::$instance->include_files();

			// Instantiate the EDD_License.
			if ( class_exists( '\\EDD\\Extensions\\ExtensionRegistry' ) ) {
				add_action( 'edd_extension_license_init', function( \EDD\Extensions\ExtensionRegistry $registry ) {
					$registry->addExtension( EDDA_PLUGIN_FILE, 'Authorize.net Payment Gateway', 514, EDDA_VERSION );
				} );
			} elseif ( class_exists( 'EDD_License' ) ) {
				$license = new EDD_License( EDDA_PLUGIN_FILE, 'Authorize.net Payment Gateway', EDDA_VERSION, 'Easy Digital Downloads', null, null, 514 );
			}

			// Admin APIs
			if ( is_admin() ) {
				//self::$instance->notices = new EDDA_Notices();
			}

			// Return the instance
			return self::$instance;
		}

		/**
		 * Throw error on object clone.
		 *
		 * The whole idea of the singleton design pattern is that there is a single
		 * object therefore, we don't want the object to be cloned.
		 *
		 * @since 1.0.0
		 * @access protected
		 * @return void
		 */
		public function __clone() {
			// Cloning instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'edda' ), '1.0.0' );
		}

		/**
		 * Disable un-serializing of the class.
		 *
		 * @since 1.0.0
		 * @access protected
		 * @return void
		 */
		public function __wakeup() {
			// Unserializing instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'edda' ), '1.0.0' );
		}

		/**
		 * Return whether the main loading class has been instantiated or not.
		 *
		 * @since 1.0.0
		 *
		 * @return boolean True if instantiated. False if not.
		 */
		private static function is_instantiated() {

			// Return true if instance is correct class
			if ( ! empty( self::$instance ) && ( self::$instance instanceof Easy_Digital_Downloads_Authorize_Net ) ) {
				return true;
			}

			// Return false if not instantiated correctly
			return false;
		}

		/**
		 * Setup the singleton instance
		 *
		 * @since 1.0.0
		 * @param string $file
		 */
		private static function setup_instance( $file = '' ) {
			self::$instance       = new Easy_Digital_Downloads_Authorize_Net;
			self::$instance->file = $file;
		}

		/**
		 * Include required files.
		 *
		 * @access private
		 * @since 1.0.0
		 * @return void
		 */
		private function include_files() {

			require_once EDDA_PLUGIN_DIR . 'includes/misc-functions.php';
			require_once EDDA_PLUGIN_DIR . 'includes/settings.php';
			require_once EDDA_PLUGIN_DIR . 'includes/webhooks.php';

			require_once EDDA_PLUGIN_DIR . 'includes/Exceptions/PaymentException.php';

			// Include the Authorize.net PHP SDK: https://github.com/AuthorizeNet/sdk-php
			require_once EDDA_PLUGIN_DIR . 'vendor/autoload.php';

			// Include the Recurring Payments integration file
			require_once EDDA_PLUGIN_DIR . 'includes/integrations/edd-recurring-payments/edd-recurring-payments.php';

		}

	}
endif; // End if class_exists check.

/**
 * Returns the instance of Easy_Digital_Downloads_Authorize_Net.
 *
 * The main function responsible for returning the one true Easy_Digital_Downloads_Authorize_Net
 * instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $edd = EDD(); ?>
 *
 * @since 1.0.0
 * @return Easy_Digital_Downloads_Authorize_Net The one true Easy_Digital_Downloads_Authorize_Net instance.
 */
function edd_authorize_net() {
	return Easy_Digital_Downloads_Authorize_Net::instance();
}
