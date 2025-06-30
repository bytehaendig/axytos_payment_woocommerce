<?php

namespace Axytos\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Axytos Encryption Service
 *
 * Handles encryption and decryption of sensitive data like API keys and other settings.
 * Provides a centralized service for consistent encryption across the plugin.
 */
class AxytosEncryptionService {

	/**
	 * The encryption key used for encrypting/decrypting data
	 *
	 * @var string
	 */
	private $encryption_key;

	/**
	 * The encryption method to use
	 *
	 * @var string
	 */
	private $encryption_method = 'aes-256-cbc';

	/**
	 * Initialize the encryption service
	 */
	public function __construct() {
		$this->encryption_key = wp_salt( 'auth' );
	}

	/**
	 * Encrypt a value
	 *
	 * @param string $value The value to encrypt
	 * @return string The encrypted value (base64 encoded)
	 */
	public function encrypt( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$ivlen = openssl_cipher_iv_length( $this->encryption_method );
		$iv    = openssl_random_pseudo_bytes( $ivlen );

		$encrypted = openssl_encrypt(
			$value,
			$this->encryption_method,
			$this->encryption_key,
			0,
			$iv
		);

		if ( $encrypted === false ) {
			return $value; // Return original value if encryption fails
		}

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt an encrypted value
	 *
	 * @param string $encrypted_value The encrypted value to decrypt
	 * @return string The decrypted value
	 */
	public function decrypt( $encrypted_value ) {
		if ( empty( $encrypted_value ) ) {
			return '';
		}

		$decoded = base64_decode( $encrypted_value, true );
		if ( $decoded === false ) {
			// Invalid base64, return original value (might be unencrypted)
			return $encrypted_value;
		}

		$ivlen = openssl_cipher_iv_length( $this->encryption_method );

		if ( strlen( $decoded ) < $ivlen ) {
			// Invalid encrypted data, return original value
			return $encrypted_value;
		}

		$iv        = substr( $decoded, 0, $ivlen );
		$encrypted = substr( $decoded, $ivlen );

		$decrypted = openssl_decrypt(
			$encrypted,
			$this->encryption_method,
			$this->encryption_key,
			0,
			$iv
		);

		// If decryption fails, return original value (might be unencrypted)
		return $decrypted !== false ? $decrypted : $encrypted_value;
	}

	/**
	 * Check if OpenSSL encryption is available
	 *
	 * @return bool True if OpenSSL is available and the encryption method is supported
	 */
	public function is_encryption_available() {
		return extension_loaded( 'openssl' ) &&
			in_array( $this->encryption_method, openssl_get_cipher_methods() );
	}

	/**
	 * Get a list of sensitive setting keys that should be encrypted
	 *
	 * @return array List of setting keys that contain sensitive data
	 */
	public static function get_sensitive_keys() {
		return array( 'AxytosAPIKey', 'webhook_api_key' );
	}

	/**
	 * Encrypt multiple settings values
	 *
	 * @param array $settings The settings array
	 * @return array The settings array with sensitive values encrypted
	 */
	public function encrypt_settings( $settings ) {
		$sensitive_keys = self::get_sensitive_keys();

		foreach ( $sensitive_keys as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				$settings[ $key ] = $this->encrypt( $settings[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Decrypt multiple settings values
	 *
	 * @param array $settings The settings array
	 * @return array The settings array with sensitive values decrypted
	 */
	public function decrypt_settings( $settings ) {
		$sensitive_keys = self::get_sensitive_keys();

		foreach ( $sensitive_keys as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				$settings[ $key ] = $this->decrypt( $settings[ $key ] );
			}
		}

		return $settings;
	}
}
