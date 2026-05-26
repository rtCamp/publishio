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
