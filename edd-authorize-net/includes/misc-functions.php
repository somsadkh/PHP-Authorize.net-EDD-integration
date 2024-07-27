<?php

use EDD_Authorize_Net\Exceptions\PaymentException;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

/**
 * Process a Single Payment through the Authorize.net extension
 *
 * @access      private
 * @since       2.0.0
 *
 * @param array $purchase_data The data which will be used to create the payment.
 *
 * @return      void
 */
function edda_process_payment( $purchase_data ) {

	global $edd_options;

	if ( ! isset( $_POST['card_number'] ) || '' === $_POST['card_number'] ) {
		edd_set_error( 'empty_card', __( 'You must enter a card number', 'edd' ) );
	}
	if ( ! isset( $_POST['card_name'] ) || '' === $_POST['card_name'] ) {
		edd_set_error( 'empty_card_name', __( 'You must enter the name on your card', 'edd' ) );
	}
	if ( ! isset( $_POST['card_exp_month'] ) || '' === $_POST['card_exp_month'] ) {
		edd_set_error( 'empty_month', __( 'You must enter an expiration month', 'edd' ) );
	}
	if ( ! isset( $_POST['card_exp_year'] ) || '' === $_POST['card_exp_year'] ) {
		edd_set_error( 'empty_year', __( 'You must enter an expiration year', 'edd' ) );
	}
	if ( ! isset( $_POST['card_cvc'] ) || '' === $_POST['card_cvc'] || 3 > strlen( $_POST['card_cvc'] ) ) {
		edd_set_error( 'empty_cvc', __( 'You must enter a valid CVC', 'edd' ) );
	}

	$errors = edd_get_errors();

	if ( $errors ) {
		edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
	}

	$card_info  = $purchase_data['card_info'];
	$card_names = explode( ' ', $card_info['card_name'] );
	$first_name = isset( $card_names[0] ) ? $card_names[0] : $purchase_data['user_info']['first_name'];
	if ( ! empty( $card_names[1] ) ) {
		unset( $card_names[0] );
		$last_name = implode( ' ', $card_names );
	} else {

		$last_name = $purchase_data['user_info']['last_name'];

	}

	$edd_customer = new EDD_Customer( $purchase_data['user_email'] );
	$amount       = number_format( $purchase_data['price'], 2, '.', '' );

	$payment_data = array(
		'price'        => $amount,
		'date'         => $purchase_data['date'],
		'user_email'   => $purchase_data['user_email'],
		'purchase_key' => $purchase_data['purchase_key'],
		'currency'     => edd_get_currency(),
		'downloads'    => $purchase_data['downloads'],
		'cart_details' => $purchase_data['cart_details'],
		'user_info'    => $purchase_data['user_info'],
		'status'       => 'pending',
	);

	$payment_id = edd_insert_payment( $payment_data );
	if ( ! $payment_id ) {
		edd_set_error( 'authorize_error', __( 'Error: Failed to record payment. Please try again.', 'edda' ) );
		edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
	}

	/* Create a merchantAuthenticationType object with authentication details retrieved from the constants file */
	$merchant_authentication = new AnetAPI\MerchantAuthenticationType();
	$merchant_authentication->setName( edd_get_option( 'edda_api_login' ) );
	$merchant_authentication->setTransactionKey( edd_get_option( 'edd_transaction_key' ) );

	// Set the transaction's refId.
	$ref_id = 'ref' . time();

	// Create the payment data for a credit card.
	$credit_card = new AnetAPI\CreditCardType();
	$credit_card->setCardNumber( str_replace( ' ', '', wp_strip_all_tags( trim( $card_info['card_number'] ) ) ) );
	$credit_card->setExpirationDate( wp_strip_all_tags( trim( $card_info['card_exp_month'] ) ) . '/' . wp_strip_all_tags( trim( $card_info['card_exp_year'] ) ) );
	$credit_card->setCardCode( wp_strip_all_tags( trim( $card_info['card_cvc'] ) ) );

	// Add the payment data to a paymentType object.
	$new_payment = new AnetAPI\PaymentType();
	$new_payment->setCreditCard( $credit_card );

	// Create order information.
	$order = new AnetAPI\OrderType();
	$order->setInvoiceNumber( $payment_id );
	if ( function_exists( 'mb_substr' ) ) {
		$description = mb_substr( edd_get_purchase_summary( $purchase_data, false ), 0, 49 );
	} else {
		$description = substr( edd_get_purchase_summary( $purchase_data, false ), 0, 49 );
	}
	$order->setDescription( $description );

	// Authorize.net doesn't accept international characters in all cases, so we'll stripe those.
	$first_name   = remove_accents( $first_name );
	$last_name    = remove_accents( $last_name );
	$card_address = remove_accents( $card_info['card_address'] . ' ' . $card_info['card_address_2'] );
	$card_city    = remove_accents( $card_info['card_city'] );
	$card_state   = remove_accents( $card_info['card_state'] );
	$card_zip     = remove_accents( $card_info['card_zip'] );
	$card_country = remove_accents( $card_info['card_country'] );

	// Set the customer's Bill To address.
	$customer_address = new AnetAPI\CustomerAddressType();
	$customer_address->setFirstName( $first_name );
	$customer_address->setLastName( $last_name );
	$customer_address->setAddress( $card_address );
	$customer_address->setCity( $card_city );
	$customer_address->setState( $card_state );
	$customer_address->setZip( $card_zip );
	$customer_address->setCountry( $card_country );

	// This is Authorize.net's version of an Idempotency Key. It prevents the same purchase data within X seconds
	// $duplicateWindowSetting = new AnetAPI\SettingType();
	// $duplicateWindowSetting->setSettingName( 'duplicateWindow' );
	// $duplicateWindowSetting->setSettingValue( '10' );

	// Set the customer's identifying information.
	$customer_data = new AnetAPI\CustomerDataType();
	$customer_data->setType( 'individual' );
	$customer_data->setId( $edd_customer->id );
	$customer_data->setEmail( $purchase_data['user_email'] );

	// Create a TransactionRequestType object and add the previous objects to it.
	$transaction_request_type = new AnetAPI\TransactionRequestType();
	$transaction_request_type->setTransactionType( 'authCaptureTransaction' );
	$transaction_request_type->setAmount( $amount );
	$transaction_request_type->setOrder( $order );
	$transaction_request_type->setPayment( $new_payment );
	$transaction_request_type->setBillTo( $customer_address );
	$transaction_request_type->setCustomer( $customer_data );
	// $transaction_request_type->addToTransactionSettings( $duplicateWindowSetting );

	// Assemble the complete transaction request.
	$request = new AnetAPI\CreateTransactionRequest();
	$request->setMerchantAuthentication( $merchant_authentication );
	$request->setRefId( $ref_id );
	$request->setTransactionRequest( $transaction_request_type );

	// Create the controller and get the response.
	$controller = new AnetController\CreateTransactionController( $request );

	// Set the Authorize.net endpoint based on test mode or live mode in EDD.
	if ( edd_is_test_mode() ) {
		$endpoint = \net\authorize\api\constants\ANetEnvironment::SANDBOX;
	} else {
		$endpoint = \net\authorize\api\constants\ANetEnvironment::PRODUCTION;
	}

	try {
		/*
		 * We're going to do a bunch of different error checking here.
		 * All errors throw an exception, which handles redirecting back
		 * to checkout with errors.
		 */
		$response = $controller->executeWithApiResponse( $endpoint );

		// If the response from Authorize.net had no value.
		if ( null === $response ) {
			throw new PaymentException( __( 'No response returned', 'edda' ) );
		}

		// Check to see if the API request was successfully received and acted upon.
		if ( 'Ok' !== $response->getMessages()->getResultCode() ) {
			$transaction_response = $response->getTransactionResponse();

			if ( null !== $transaction_response && null !== $transaction_response->getErrors() ) {
				$error_code = $transaction_response->getErrors()[0]->getErrorCode();
				$error      = $transaction_response->getErrors()[0]->getErrorText();
			} else {
				$error_code = $response->getMessages()->getMessage()[0]->getCode();
				$error      = $response->getMessages()->getMessage()[0]->getText();
			}

			throw new PaymentException( $error, $error_code );
		}

		// Since the API request was successful, look for a transaction response and parse it to display the results of authorizing the card.
		$transaction_response = $response->getTransactionResponse();

		// If the transaction was not successful, bail.
		if ( empty( $transaction_response ) || '1' !== $transaction_response->getResponseCode() ) {
			// Check if Authorize.net gave us any helpful error messages we can show.
			if ( null !== $transaction_response->getErrors() ) {
				throw new PaymentException(
					$transaction_response->getErrors()[0]->getErrorText(),
					$transaction_response->getErrors()[0]->getErrorCode()
				);
			}

			$response_code = ! empty( $transaction_response ) ? $transaction_response->getResponseCode() : false;
			throw new PaymentException(
				edd_authorize_net_error_code_to_message( $response_code ),
				'payment_failed'
			);
		}

		$authorize_hash = $transaction_response->getTransHashSha2();
		$my_hash        = edd_authorizenet_get_transHashSHA2( $transaction_response->getTransId(), $amount );

		if ( ! hash_equals( $authorize_hash, $my_hash ) ) {
			// If the hash did not match, this might be a man-in-the-middle attack.
			throw new PaymentException(
				__( 'Error: your payment could not be recorded, but you may have been charged. Please contact the store owner.', 'edda' ),
				'authorize_error'
			);
		}

		/*
		 * At this point we have a successful transaction!
		 */
		$payment_object                 = edd_get_payment( $payment_id );
		$payment_object->status         = 'complete';
		$payment_object->transaction_id = $transaction_response->getTransID();
		$payment_object->save();
		edd_empty_cart();
		edd_send_to_success_page();
	} catch ( PaymentException $e ) {
		edd_set_error( $e->getPaymentErrorCode(), $e->getMessage() );
		edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
	}
}

add_action( 'edd_gateway_authorize', 'edda_process_payment' );

/**
 * Convert an authorize error code into an error message.
 *
 * @param int $error_code The error code from Authorize.net.
 *
 * @return string The corresponding error code.
 */
function edd_authorize_net_error_code_to_message( $error_code ) {
	switch ( $error_code ) {
		case '2':
		case '3':
		case '4':
			// https://developer.authorize.net/api/reference/responseCodes.html?code=2
			// https://developer.authorize.net/api/reference/responseCodes.html?code=3
			// https://developer.authorize.net/api/reference/responseCodes.html?code=4
			$return = __( 'This transaction has been declined.', 'edda' );
			break;
		case '5':
			// https://developer.authorize.net/api/reference/responseCodes.html?code=5.
			$return = __( 'A valid amount is required.', 'edda' );
			break;
		case '6':
			// https://developer.authorize.net/api/reference/responseCodes.html?code=6.
			$return = __( 'The credit card number is invalid.', 'edda' );
			break;
		case '7':
			// https://developer.authorize.net/api/reference/responseCodes.html?code=7.
			$return = __( 'Credit card expiration date is invalid.', 'edda' );
			break;
		case '8':
			// https://developer.authorize.net/api/reference/responseCodes.html?code=8.
			$return = __( 'The credit card has expired.', 'edda' );
			break;
		default:
			if ( ! empty( $error_code ) ) {
				$return = sprintf( __( 'An error occurred. Error code: %d', 'edda' ), $error_code );
			} else {
				$return = __( 'An unknown error has occurred. Please try again or contact support if the error persists.', 'edda' );
			}
			break;
	}

	return $return;
}

/**
 * Mark the last name as a required field for Authorize.net.
 *
 * @since 2.0.5
 * @param array $required_fields
 * @return array
 */
function edda_purchase_form_required_fields( $required_fields ) {
	if ( 'authorize' === edd_get_chosen_gateway() ) {
		$required_fields['edd_last'] = array(
			'error_id'      => 'invalid_last_name',
			'error_message' => __( 'Please enter your last name.', 'edda' ),
		);
	}

	return $required_fields;
}
add_filter( 'edd_purchase_form_required_fields', 'edda_purchase_form_required_fields' );
