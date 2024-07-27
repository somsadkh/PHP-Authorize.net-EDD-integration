<?php
/**
 * PaymentException.php
 *
 * @package   edd-authorize-net
 * @copyright Copyright (c) 2021, Sandhills Development, LLC
 * @license   GPL2+
 * @since     2.0.3
 */

namespace EDD_Authorize_Net\Exceptions;

class PaymentException extends \Exception {

	/**
	 * @var string
	 */
	protected $payment_error_code;

	/**
	 * PaymentException constructor.
	 *
	 * @param string          $message  Error message.
	 * @param string          $code     Error code as a string.
	 * @param null|\Throwable $previous The previous throwable used for the exception chaining.
	 */
	public function __construct( $message = "", $code = 'api_error', $previous = null ) {
		$this->payment_error_code = $code;

		parent::__construct( $message, 0, $previous );
	}

	/**
	 * Retrieves the payment error code.
	 *
	 * @return string
	 */
	public function getPaymentErrorCode() {
		return $this->payment_error_code;
	}

}
