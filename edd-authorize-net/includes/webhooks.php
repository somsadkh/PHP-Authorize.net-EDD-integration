<?php

/**
 * Get the transHashSHA2 value for a transaction so it can be verified at Authorize.net.
 * This ensures that the webhook actually came from Authorize.net
 * See: https://developer.authorize.net/support/hash_upgrade/
 *
 * @since    1.0.0
 * @param    int $transaction_id The id of the transaction.
 * @param    int $transaction_amount The amount of the transaction.
 * @return   string
 */
function edd_authorizenet_get_transHashSHA2( $transaction_id, $transaction_amount ) {

	// Get the API Key we used to send API requests to Authorize.net.
	$api_key = edd_get_option( 'edda_api_login' );

	// Convert the Signature in the Authorize.net Settings to a Byte Array.
	$key = hex2bin( edd_get_option( 'edda_live_signature_key' ) );

	// Build the string that Authorize.net wants us to create (see link in function description).
	$string = '^' . $api_key . '^' . $transaction_id . '^' . $transaction_amount . '^';

	// Hash it using our signature as the secret.
	return strtoupper( HASH_HMAC( 'sha512', $string, $key ) );
}

/**
 * Authorize.net webhooks require slashes instead of URL variables, so create a permalink endpoint.
 *
 * @since    1.0.0
 * @param    array $rules The current rewrite rules from WP.
 * @return   array the modified rewrite rules for WP
 */
function edd_authorizenet_webhooks_rewrites( $rules ) {

	$new_rules = array(
		'edd_authorizenet_webhook_endpoint/' => 'index.php?edd_authorizenet_webhook_endpoint',
		'edd_authorizenet_webhook_endpoint'  => 'index.php?edd_authorizenet_webhook_endpoint',
	);
	$new_rules = array_merge( $new_rules, $rules );
	return $new_rules;
}
add_filter( 'rewrite_rules_array', 'edd_authorizenet_webhooks_rewrites' );

/**
 * Add the rewrite tags for the Authorize.net webhook endpoint
 *
 * @since    1.0.0
 * @return   void
 */
function edd_authorizenet_webhook_rewrite_tags() {
	add_rewrite_tag( '%edd_authorizenet_webhook_endpoint%', '([^/]*)' );
}
add_action( 'init', 'edd_authorizenet_webhook_rewrite_tags' );

/**
 * Get the Authorize.net API URL based on whether test mode is enabled in EDD
 *
 * @since       1.0.0.
 * @return      string
 */
function edd_authorizenet_get_api_url() {
	if ( edd_is_test_mode() ) {
		return 'https://apitest.authorize.net/rest/v1/';
	} else {
		return 'https://api.authorize.net/rest/v1/';
	}
}

/**
 * Get the Authorize.net authorization header string
 *
 * @since       1.0.0.
 * @return      string
 */
function edd_authorizenet_get_webhook_authorization_header() {

	// This follows the authentication steps listed here: https://developer.authorize.net/api/reference/features/webhooks.html.
	$api_key         = edd_get_option( 'edda_api_login' );
	$transaction_key = edd_get_option( 'edd_transaction_key' );
	$concatenated    = $api_key . ':' . $transaction_key;
	$base_64_key     = base64_encode( $concatenated );
	$header_string   = 'Authorization: Basic ' . $base_64_key;

	return $header_string;
}

/**
 * When Authorize.net sends a Webhook, this function handles it.
 *
 * @since       1.0.0.
 * @return      void
 */
function edd_authorizenet_webhook_endpoint_handler() {

	global $wp_query;

	// If this page load is not a webhook attempt by Authorize.net, exit this function here.
	if ( ! isset( $wp_query->query_vars['edd_authorizenet_webhook_endpoint'] ) ) {
		return false;
	}

	// Get and sanitize the data sent from Authorize.net in the body.
	$body         = file_get_contents( 'php://input' );
	$content      = trim( sanitize_text_field( file_get_contents( 'php://input' ) ) );
	$webhook_data = json_decode( $content, true );

	if ( ! is_array( $webhook_data ) ) {
		edd_debug_log( 'Exiting Authorize.net webhook - webhook data was blank.', true );
		wp_die( __( 'Invalid Webhook Data.', 'edda' ), __( 'Error', 'edda' ), array( 'response' => 403 ) );
	}

	if ( ! edd_authorizenet_is_webhook_valid( $body ) ) {
		edd_debug_log( 'Exiting Authorize.net webhook - invalid hash.', true );
		wp_die( __( 'Invalid hash.', 'edda' ), __( 'Error', 'edda' ), array( 'response' => 403 ) );
	}

	if ( empty( $webhook_data['eventType'] ) ) {
		edd_debug_log( 'Exiting Authorize.net webhook - missing event type.', true );
		wp_die( __( 'Missing event type.', 'edda' ), __( 'Error', 'edda' ), array( 'response' => 500 ) );
	}

	$event_type = $webhook_data['eventType'];

	edd_debug_log( sprintf( 'Authorize.net webhook - %s.', wp_json_encode( $webhook_data ) ) );

	switch ( $event_type ) {
		/**
		* Auth capture created.
		*
		* Note that this happens for both one-time payments and subscriptions.
		* We handle subscription renewal payments in the Authorize.net gateway class EDD_Recurring_Authorize
		*
		* 'payload' => array(
		*      'responseCode' => 1,     // Success
		*      'authCode'     => '',
		*      'avsResponse'  => 'Y',
		*      'authAmount'   => 00.00,
		*      'entityName'   => 'transaction',
		*      'id'           => '123', // Transaction ID.
		* )
		*/
		case 'net.authorize.payment.authcapture.created':
			$transaction_id = ! empty( $webhook_data['payload']['id'] ) ? $webhook_data['payload']['id'] : '';

			if ( empty( $transaction_id ) ) {
				edd_debug_log( 'Exiting Authorize.net webhook - missing transaction ID.', true );
				wp_die( __( 'Missing transaction ID.', 'edda' ), __( 'Error', 'edda' ), array( 'response' => 500 ) );
			}

			edd_debug_log( sprintf( 'Processing payment.authcapture.created for transaction ID %s.', $transaction_id ) );

			// Get the EDD Payment that corresponds with the Authorize.net Transaction ID in the webhook.
			$edd_payment = edd_get_payment( $transaction_id, true );

			// If there was no payment found.
			if ( ! $edd_payment || empty( $edd_payment->id ) ) {
				edd_debug_log( sprintf( 'Processing payment.authcapture.created for transaction ID %s, but no matching EDD Payment was found. ', $transaction_id ) );
				// Do nothing. This might be a webhook for a different website running EDD but sharing the same auth.net account.
				break;
			}

			// Add a note to the payment saying it was confirmed by a webhook.
			edd_insert_payment_note( $edd_payment->id, __( 'Payment confirmed valid by Authorize.net webhook', 'edda' ) );

			break;

		/**
		* Refund created
		*
		* Notifies you that a successfully settled transaction was refunded.
		*
		* 'payload' => array(
		*      'responseCode' => 1,     // Success
		*      'authCode'     => '',
		*      'avsResponse'  => 'Y',
		*      'authAmount'   => 00.00,
		*      'entityName'   => 'transaction',
		*      'id'           => '123', // Transaction ID.
		* )
		*/
		case 'net.authorize.payment.refund.created':
			// Process refund in EDD.
			$transaction_id = ! empty( $webhook_data['payload']['id'] ) ? $webhook_data['payload']['id'] : '';

			if ( empty( $transaction_id ) ) {
				edd_debug_log( 'Exiting Authorize.net webhook - missing transaction ID.', true );

				wp_die( __( 'Missing transaction ID.', 'edda' ), __( 'Error', 'edda' ), array( 'response' => 500 ) );
			}

			$payment_id = edd_get_purchase_id_by_transaction_id( $transaction_id );

			// If we have a payment that matches this transaction ID.
			if ( $payment_id ) {

				$payment = new EDD_Payment( $payment_id );

				$payment->status = 'refunded';
				$payment->save();
				$payment->add_note( sprintf( __( 'Charge %s refunded in Authorize.net.', ' edda' ), $transaction_id ) );

			}

			break;

	}

	do_action( 'edd_authorizenet_webhook_endpoint_handler', $webhook_data );

	die( 'Webhook caught and handled by the EDD Authorize.net Extension' );

}
add_action( 'wp', 'edd_authorizenet_webhook_endpoint_handler', 99 );

/**
 * Determines if an Authorize.net webhook is valid by verifying the SHA512 hash.
 *
 * @since   2.0
 * @param   string $body The body in the webhook.
 * @return  bool True if the webhook hash matches, false if not.
 */
function edd_authorizenet_is_webhook_valid( $body ) {

	// Get the auth hash from the header.
	$auth_hash = isset( $_SERVER['HTTP_X_ANET_SIGNATURE'] ) ? strtoupper( explode( '=', $_SERVER['HTTP_X_ANET_SIGNATURE'] )[1] ) : '';

	if ( empty( $auth_hash ) ) {
		edd_debug_log( 'Webhook hash not found. ' . wp_json_encode( $_SERVER ) );
		return false;
	}

	$authorizenet_signature = edd_get_option( 'edda_live_signature_key' );

	$generated_hash = strtoupper( hash_hmac( 'sha512', $body, $authorizenet_signature ) );

	return hash_equals( $auth_hash, $generated_hash );
}

if ( ! function_exists( 'getallheaders' ) ) {
	/**
	 * Workaround for users on nginx, where getallheaders isn't a PHP function.
	 *
	 * @since   2.0
	 * @return  bool True if the webhook hash matches, false if not.
	 */
	function getallheaders() {
		$headers = [];
		foreach ( $_SERVER as $name => $value ) {
			if ( 'HTTP_' === strtoupper( substr( $name, 0, 5 ) ) ) {
				$headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
			}
		}
		return $headers;
	}
}
