<?php
/**
 * WordPress-safe encryption utilities.
 *
 * Useful for encrypting sensitive data before storing it in the database, with a fallback to return raw values if OpenSSL is unavailable.
 *
 * @package rtCamp\Publish_With_AI\Framework
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Framework;

/**
 * Class - Encryptor
 */
final class Encryptor {
	/**
	 * The OpenSSL encryption method.
	 */
	private const METHOD = 'aes-256-gcm';

	/**
	 * The GCM authentication tag length in bytes.
	 */
	private const TAG_LENGTH = 16;

	/**
	 * The IV length for GCM mode.
	 */
	private const IV_LENGTH = 12;

	/**
	 * Encrypts a value using AES-256-GCM authenticated encryption.
	 *
	 * @param string $raw_value The value to encrypt.
	 *
	 * @return string|false The encrypted value, or false on failure.
	 */
	public static function encrypt( string $raw_value ): string|false {
		if ( ! extension_loaded( 'openssl' ) ) {
			_doing_it_wrong(
				__METHOD__,
				'OpenSSL extension is not loaded. Cannot encrypt value.',
				'0.0.1',
			);
			return false;
		}

		$iv  = random_bytes( self::IV_LENGTH );
		$tag = '';

		$value = openssl_encrypt(
			$raw_value,
			self::METHOD,
			self::get_key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			self::TAG_LENGTH
		);

		return false !== $value ? base64_encode( $iv . $tag . $value ) : false;
	}

	/**
	 * Decrypts a value encrypted with AES-256-GCM.
	 *
	 * @param string $raw_value The encrypted value.
	 *
	 * @return string|false The decrypted value, or false on failure/tampering.
	 */
	public static function decrypt( string $raw_value ): string|false {
		if ( ! extension_loaded( 'openssl' ) ) {
			_doing_it_wrong(
				__METHOD__,
				'OpenSSL extension is not loaded. Cannot decrypt value.',
				'0.0.1',
			);
			return false;
		}

		$decoded_value = base64_decode( $raw_value, true );
		if ( false === $decoded_value ) {
			// Don't leak potentially sensitive data, e.g. an unencrypted value that was accidentally passed in.
			_doing_it_wrong(
				__METHOD__,
				'Invalid input: not a valid base64-encoded string.',
				'0.0.1',
			);
			return false;
		}

		// Extract IV, tag, and ciphertext.
		$iv         = substr( $decoded_value, 0, self::IV_LENGTH );
		$tag        = substr( $decoded_value, self::IV_LENGTH, self::TAG_LENGTH );
		$ciphertext = substr( $decoded_value, self::IV_LENGTH + self::TAG_LENGTH );

		return openssl_decrypt(
			$ciphertext,
			self::METHOD,
			self::get_key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);
	}

	/**
	 * Gets the encryption key.
	 *
	 * Uses RTCAMP_PUBLISH_WITH_AI_ENCRYPTION_KEY if defined, otherwise falls back to LOGGED_IN_KEY.
	 *
	 * @throws \RuntimeException If no valid encryption key is configured.
	 */
	private static function get_key(): string {
		if ( defined( 'RTCAMP_PUBLISH_WITH_AI_ENCRYPTION_KEY' ) && '' !== RTCAMP_PUBLISH_WITH_AI_ENCRYPTION_KEY ) {
			return hash( 'sha256', RTCAMP_PUBLISH_WITH_AI_ENCRYPTION_KEY, true );
		}

		if ( defined( 'LOGGED_IN_KEY' ) && '' !== LOGGED_IN_KEY ) {
			return hash( 'sha256', LOGGED_IN_KEY, true );
		}

		// No encryption key configured. Fail hard instead of silently using an insecure fallback.
		// Define RTCAMP_PUBLISH_WITH_AI_ENCRYPTION_KEY in wp-config.php with a cryptographically secure value.
		// WordPress core's LOGGED_IN_KEY is an acceptable fallback for sites that have it set.
		throw new \RuntimeException(
			'Publish With AI: No encryption key configured. Define RTCAMP_PUBLISH_WITH_AI_ENCRYPTION_KEY in wp-config.php.'
		);
	}
}
