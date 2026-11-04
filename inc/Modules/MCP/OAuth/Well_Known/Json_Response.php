<?php
/**
 * Sends a well-known metadata document.
 *
 * @package rtCamp\Publishio\Modules\MCP\OAuth\Well_Known
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\OAuth\Well_Known;

/**
 * Trait - Json_Response
 */
trait Json_Response {
	/**
	 * Send a JSON response and terminate the request.
	 *
	 * This bypasses the WordPress template loader intentionally — there is no
	 * theme to render for a machine-readable JSON metadata document. The
	 * rewrite-rule + parse_request approach is needed because these documents
	 * must live at `/.well-known/…` (RFC 8414, RFC 9728), which the REST API cannot do.
	 *
	 * CORS: Allow-Origin: * is safe here; these documents expose no user data,
	 * only static server capability metadata.
	 *
	 * @param array<string, mixed> $data Response data.
	 */
	private function send_json_response( array $data ): void {
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		header( 'Access-Control-Allow-Origin: *' );

		echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		exit;
	}
}
