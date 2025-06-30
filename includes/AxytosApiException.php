<?php

namespace Axytos\WooCommerce;

/**
 * Custom exception for Axytos API errors
 *
 * This exception is thrown when there are communication issues with the Axytos API,
 * allowing for better error handling and user-friendly messages.
 */
class AxytosApiException extends \Exception {

	/**
	 * Error type constants
	 */
	const TYPE_CONNECTION_ERROR = 'connection_error';
	const TYPE_HTTP_ERROR       = 'http_error';
	const TYPE_API_ERROR        = 'api_error';

	/**
	 * The type of API error
	 *
	 * @var string
	 */
	private $error_type;

	/**
	 * Additional error context
	 *
	 * @var array
	 */
	private $error_context;

	/**
	 * Create a new AxytosApiException
	 *
	 * @param string          $message The error message
	 * @param string          $error_type The type of error (connection_error, http_error, api_error)
	 * @param array           $error_context Additional context about the error
	 * @param int             $code The error code
	 * @param \Throwable|null $previous The previous exception
	 */
	public function __construct(
		string $message = '',
		string $error_type = self::TYPE_API_ERROR,
		array $error_context = array(),
		int $code = 0,
		\Throwable $previous = null
	) {
		parent::__construct( $message, $code, $previous );
		$this->error_type    = $error_type;
		$this->error_context = $error_context;
	}

	/**
	 * Get the error type
	 *
	 * @return string
	 */
	public function getErrorType(): string {
		return $this->error_type;
	}

	/**
	 * Get the error context
	 *
	 * @return array
	 */
	public function getErrorContext(): array {
		return $this->error_context;
	}

	/**
	 * Check if this is a connection error
	 *
	 * @return bool
	 */
	public function isConnectionError(): bool {
		return $this->error_type === self::TYPE_CONNECTION_ERROR;
	}

	/**
	 * Check if this is an HTTP error
	 *
	 * @return bool
	 */
	public function isHttpError(): bool {
		return $this->error_type === self::TYPE_HTTP_ERROR;
	}

	/**
	 * Check if this is an API error
	 *
	 * @return bool
	 */
	public function isApiError(): bool {
		return $this->error_type === self::TYPE_API_ERROR;
	}

	/**
	 * Create a connection error exception
	 *
	 * @param string $message The error message
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function connectionError( string $message, array $context = array() ): self {
		return new static( $message, self::TYPE_CONNECTION_ERROR, $context );
	}

	/**
	 * Create an HTTP error exception
	 *
	 * @param string $message The error message
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function httpError( string $message, array $context = array() ): self {
		return new static( $message, self::TYPE_HTTP_ERROR, $context );
	}

	/**
	 * Create an API error exception
	 *
	 * @param string $message The error message
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function apiError( string $message, array $context = array() ): self {
		return new static( $message, self::TYPE_API_ERROR, $context );
	}
}
