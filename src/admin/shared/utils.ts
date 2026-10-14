export type App = 'claude' | 'openai' | 'other';

// The app-to-domain map comes from PHP (Client_Metadata::APP_HOSTS), so the consent screen and the admin pick the same logo.
function matchDomain( hostname: string ): App | null {
	const appHosts = window.publishioAdmin?.appHosts ?? {};

	for ( const [ app, domains ] of Object.entries( appHosts ) ) {
		if (
			domains.some(
				( d ) => hostname === d || hostname.endsWith( '.' + d )
			)
		) {
			return app as App;
		}
	}
	return null;
}

export function detectApps( urls: string[] ): App[] {
	const found = new Set< App >();

	for ( const uri of urls ) {
		try {
			const hostname = new URL( uri ).hostname.toLowerCase();
			const app = matchDomain( hostname );
			found.add( app ?? 'other' );
		} catch {
			found.add( 'other' );
		}
	}

	return found.size ? Array.from( found ) : [ 'other' ];
}

export function relativeDate( timestamp: number ): string {
	const rtf = new Intl.RelativeTimeFormat( navigator.language || 'en', {
		numeric: 'auto',
	} );
	const diffSeconds = ( timestamp * 1000 - Date.now() ) / 1000;
	const abs = Math.abs( diffSeconds );

	if ( abs >= 86400 ) {
		return rtf.format( Math.round( diffSeconds / 86400 ), 'day' );
	}
	if ( abs >= 3600 ) {
		return rtf.format( Math.round( diffSeconds / 3600 ), 'hour' );
	}
	return rtf.format( Math.round( diffSeconds / 60 ), 'minute' );
}

/**
 * Copy text, falling back to a hidden textarea where the Clipboard API is refused.
 *
 * @param text Text to copy.
 */
export async function copyToClipboard( text: string ): Promise< void > {
	try {
		await navigator.clipboard.writeText( text );
	} catch {
		const el = document.createElement( 'textarea' );
		el.value = text;
		document.body.appendChild( el );
		el.select();
		document.execCommand( 'copy' );
		document.body.removeChild( el );
	}
}
