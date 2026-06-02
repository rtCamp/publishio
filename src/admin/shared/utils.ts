export type App = 'claude' | 'openai' | 'other';

const KNOWN_APPS: Array< {
	app: Exclude< App, 'other' >;
	domains: string[];
} > = [
	{ app: 'claude', domains: [ 'claude.ai', 'anthropic.com' ] },
	{ app: 'openai', domains: [ 'openai.com', 'chatgpt.com' ] },
];

function matchDomain( hostname: string ): Exclude< App, 'other' > | null {
	for ( const { app, domains } of KNOWN_APPS ) {
		if (
			domains.some(
				( d ) => hostname === d || hostname.endsWith( '.' + d )
			)
		) {
			return app;
		}
	}
	return null;
}

export function detectApps( redirectUris: string[] ): App[] {
	const found = new Set< App >();

	for ( const uri of redirectUris ) {
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
