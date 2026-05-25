type Provider = 'claude' | 'openai' | 'other';

const KNOWN_PROVIDERS: Array< {
	provider: Exclude< Provider, 'other' >;
	domains: string[];
} > = [
	{ provider: 'claude', domains: [ 'claude.ai', 'anthropic.com' ] },
	{ provider: 'openai', domains: [ 'openai.com', 'chatgpt.com' ] },
];

function matchDomain( hostname: string ): Exclude< Provider, 'other' > | null {
	for ( const { provider, domains } of KNOWN_PROVIDERS ) {
		if (
			domains.some(
				( d ) => hostname === d || hostname.endsWith( '.' + d )
			)
		) {
			return provider;
		}
	}
	return null;
}

export function detectProviders( redirectUris: string[] ): Provider[] {
	const found = new Set< Provider >();

	for ( const uri of redirectUris ) {
		try {
			const hostname = new URL( uri ).hostname.toLowerCase();
			const provider = matchDomain( hostname );
			found.add( provider ?? 'other' );
		} catch {
			found.add( 'other' );
		}
	}

	return found.size ? Array.from( found ) : [ 'other' ];
}

export function relativeDate( timestamp: number ): string {
	const rtf = new Intl.RelativeTimeFormat( 'en', { numeric: 'auto' } );
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
