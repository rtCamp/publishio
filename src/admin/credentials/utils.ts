export function getInvalidUris( uris: string[] ): string[] {
	return uris.filter( ( uri ) => {
		try {
			const url = new URL( uri );
			if ( url.hash ) {
				return true;
			}
			const isLocalhost = [ 'localhost', '127.0.0.1', '::1' ].includes(
				url.hostname
			);
			return ! (
				url.protocol === 'https:' ||
				( url.protocol === 'http:' && isLocalhost )
			);
		} catch {
			return true;
		}
	} );
}

/**
 * Returns true when the value is empty (field will be cleared) or a valid https URL.
 * Mirrors the backend validate_callback on optional URI fields.
 */
export function isValidHttpsUrl( value: string ): boolean {
	if ( ! value.trim() ) {
		return true;
	}
	try {
		return new URL( value.trim() ).protocol === 'https:';
	} catch {
		return false;
	}
}

/**
 * Extracts a human-readable message from a thrown apiFetch error.
 * WP REST API rejects with `{ code, message, data }` — surface `message` when present.
 */
export function getApiErrorMessage( err: unknown ): string | null {
	if ( err && typeof err === 'object' && 'message' in err ) {
		const msg = ( err as { message: unknown } ).message;
		return typeof msg === 'string' && msg ? msg : null;
	}
	return null;
}
