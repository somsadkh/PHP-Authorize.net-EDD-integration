<?php
/**
 * Integration functions to make Authorize.net compatible with EDD Recurring.
 *
 * @since 2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrates EDD Authorize.net with the EDD Recurring extension
 *
 * @since 2.0
 */
class EDD_AuthorizeNet_Recurring {

	/**
	 * Get things started
	 *
	 * @since  2.0
	 * @return void
	 */
	public function __construct() {

		if ( ! class_exists( 'EDD_Recurring' ) ) {
			return;
		}

		global $edd_recurring_authorize;

		// Include the Recurring Payments integration file.
		require_once EDDA_PLUGIN_DIR . 'includes/integrations/edd-recurring-payments/edd-recurring-gateway-authorize.php';

		// Initialize the EDD_Recurring_Authorize class, which sets up Authorize.net as supported Gateway for EDD Recurring Payments.
		$edd_recurring_authorize = new EDD_Recurring_Authorize();

	}

}
new EDD_AuthorizeNet_Recurring();
