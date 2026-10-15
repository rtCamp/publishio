/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

export interface Reply {
	url: string;
	redirected: boolean;
	status: number;
	contentType: string;
	server: string;
	cfMitigated: string;
	wwwAuthenticate: string;
	csp: string;
	wordpressPage: boolean;
	json: unknown;
	title: string;
	ms: number;
}

export type ProbeResult =
	| { ok: true; reply: Reply }
	| {
			ok: false;
			error: string;
			ms: number;
			redirectedAway?: boolean;
			timedOut?: boolean;
	  };

/** How long a request may take, reading the response included. */
export const PROBE_TIMEOUT = 15000;

export type Probe = (
	url: string,
	init?: RequestInit
) => Promise< ProbeResult >;

/**
 * Send a request the way an AI app's server would: without the admin's cookies.
 *
 * @param url  Address to request.
 * @param init Fetch options.
 */
export const probe: Probe = async ( url, init = {} ) => {
	const started = performance.now();
	const send = ( redirect: RequestRedirect ) =>
		fetch( url, {
			...init,
			credentials: 'omit',
			cache: 'no-store',
			redirect,
			signal: AbortSignal.timeout( PROBE_TIMEOUT ),
		} );

	try {
		const response = await send( 'follow' );
		const text = await response.text();
		const header = ( name: string ) => response.headers.get( name ) ?? '';
		const contentType = header( 'content-type' );

		return {
			ok: true,
			reply: {
				url: response.url || url,
				redirected: response.redirected,
				status: response.status,
				contentType: contentType.split( ';' )[ 0 ]?.trim() ?? '',
				server: header( 'server' ).split( /[\/\s]/ )[ 0 ] ?? '',
				cfMitigated: header( 'cf-mitigated' ),
				wwwAuthenticate: header( 'www-authenticate' ),
				csp: header( 'content-security-policy' ),
				wordpressPage:
					/rel="https:\/\/api\.w\.org\/"/.test( header( 'link' ) ) ||
					/\/wp-(content|includes)\/|<body id="error-page"/.test(
						text
					),
				json: contentType.includes( 'json' ) ? parseJson( text ) : null,
				title: pageTitle( text ),
				ms: Math.round( performance.now() - started ),
			},
		};
	} catch ( error ) {
		const ms = Math.round( performance.now() - started );
		const message =
			error instanceof Error ? error.message : String( error );
		if ( error instanceof DOMException && 'TimeoutError' === error.name ) {
			return { ok: false, error: message, ms, timedOut: true };
		}
		// A redirect to another site fails CORS like a network error; without following it, the browser reports it as an opaque redirect.
		const redirectedAway = await send( 'manual' ).then(
			( response ) => 'opaqueredirect' === response.type,
			() => false
		);
		return {
			ok: false,
			error: message,
			ms,
			...( redirectedAway ? { redirectedAway } : {} ),
		};
	}
};

function parseJson( text: string ): unknown {
	try {
		return JSON.parse( text );
	} catch {
		return null;
	}
}

function pageTitle( text: string ): string {
	const match = /<title[^<>]*>([^<]*)<\/title>/i.exec( text );
	return ( match?.[ 1 ] ?? '' ).replace( /\s+/g, ' ' ).trim().slice( 0, 100 );
}

export function isRecord( value: unknown ): value is Record< string, unknown > {
	return (
		typeof value === 'object' && value !== null && ! Array.isArray( value )
	);
}

/**
 * The `code` of a WordPress REST error, or an empty string.
 *
 * @param json Parsed response body.
 */
export function restCode( json: unknown ): string {
	return isRecord( json ) &&
		typeof json[ 'code' ] === 'string' &&
		typeof json[ 'message' ] === 'string'
		? json[ 'code' ]
		: '';
}

/**
 * Whether WordPress answered: a REST error, or a page with WordPress's REST API link header or its asset paths.
 *
 * @param reply The response.
 */
export function fromWordPress( reply: Reply ): boolean {
	return '' !== restCode( reply.json ) || reply.wordpressPage;
}

/**
 * A response in one line, for the report.
 *
 * @param result The probe result.
 */
export function describe( result: ProbeResult ): string {
	if ( ! result.ok && result.redirectedAway ) {
		return __(
			'Redirected to another site, which this page can’t follow or see',
			'publishio'
		);
	}

	if ( ! result.ok && result.timedOut ) {
		return sprintf(
			/* translators: %d: seconds */
			__( 'No response within %d seconds', 'publishio' ),
			PROBE_TIMEOUT / 1000
		);
	}

	if ( ! result.ok ) {
		/* translators: %s: the browser's network error */
		return sprintf( __( 'No response: "%s"', 'publishio' ), result.error );
	}

	const { reply } = result;
	const parts = [
		`${ reply.status }${
			reply.contentType ? ` \`${ reply.contentType }\`` : ''
		}`,
	];
	const code = restCode( reply.json );

	if ( code ) {
		const message = isRecord( reply.json )
			? String( reply.json[ 'message' ] )
			: '';
		parts.push(
			sprintf(
				/* translators: 1: WordPress error code, 2: WordPress error message */
				__(
					'from WordPress, code `%1$s`, message "%2$s"',
					'publishio'
				),
				code,
				message
			)
		);
	} else {
		if ( reply.wwwAuthenticate ) {
			parts.push(
				`\`WWW-Authenticate: ${
					reply.wwwAuthenticate.split( /\s/ )[ 0 ]
				}\``
			);
		}
		if ( reply.server ) {
			parts.push( `\`server: ${ reply.server }\`` );
		}
		if ( reply.cfMitigated ) {
			parts.push( `\`cf-mitigated: ${ reply.cfMitigated }\`` );
		}
		if ( reply.title ) {
			parts.push(
				/* translators: %s: HTML page title */
				sprintf( __( 'page title "%s"', 'publishio' ), reply.title )
			);
		}
		if ( reply.status >= 400 && ! fromWordPress( reply ) ) {
			parts.push( __( 'not from WordPress', 'publishio' ) );
		}
	}

	if ( reply.redirected ) {
		/* translators: %s: final URL after redirects */
		parts.push( sprintf( __( 'ended at %s', 'publishio' ), reply.url ) );
	}

	return `${ parts.join( ', ' ) } (${ reply.ms } ms)`;
}

/**
 * Failure code for a response a check didn't expect, so the same cause gets the same words in every check.
 *
 * @param result The probe result.
 */
export function classify( result: ProbeResult ): string {
	if ( ! result.ok && result.timedOut ) {
		return 'timed_out';
	}

	if ( ! result.ok ) {
		return result.redirectedAway ? 'redirected_away' : 'unreachable';
	}

	const { reply } = result;
	if ( reply.redirected ) {
		return 'redirected';
	}
	if (
		401 === reply.status &&
		/^\s*(basic|digest)\b/i.test( reply.wwwAuthenticate )
	) {
		return 'password_protected';
	}
	if ( reply.status >= 500 ) {
		return 'server_error';
	}
	if ( reply.status >= 400 && ! fromWordPress( reply ) ) {
		return 'blocked_before_wordpress';
	}
	if ( 404 === reply.status && 'rest_no_route' === restCode( reply.json ) ) {
		return 'not_found';
	}
	if ( 401 === reply.status || 403 === reply.status ) {
		return 'rest_blocked';
	}

	return 'unexpected';
}
