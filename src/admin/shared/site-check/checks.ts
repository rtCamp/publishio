/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { APP_HOSTS, APP_IDS, APP_NAMES, appNames } from './problems';
import {
	classify,
	describe,
	fromWordPress,
	isRecord,
	probe,
	restCode,
} from './probe';
import type { ProbeResult } from './probe';
import type {
	AppCheck,
	CheckContext,
	CheckDefinition,
	CheckResult,
	Exchange,
} from './types';

/** Not a real token: Publishio rejects it before the MCP adapter runs, so a healthy site logs nothing. */
const MADE_UP_KEY = 'publishio-status-check';

const INITIALIZE = JSON.stringify( {
	jsonrpc: '2.0',
	id: 1,
	method: 'initialize',
	params: {
		protocolVersion: '2025-06-18',
		capabilities: {},
		clientInfo: { name: 'publishio-status-check', version: '1' },
	},
} );

const untrailed = ( url: string ) => url.replace( /\/$/, '' );

/**
 * Who can reach a host: this computer, the local network, or anyone.
 *
 * Matched by name, not DNS, since `/etc/hosts` and split DNS often map public sites to 127.0.0.1.
 *
 * @param hostname URL host name.
 */
export function hostReach(
	hostname: string
): 'public' | 'this_computer' | 'private_network' {
	const host = hostname.toLowerCase().replace( /^\[|\]$/g, '' );
	// URL parsing writes every IPv4 form (such as 2130706433 or 0x7f.1) as four decimal parts.
	const ipv4 = /^(\d{1,3})\.(\d{1,3})\.\d{1,3}\.\d{1,3}$/.exec( host );
	const [ a, b ] = ipv4
		? [ Number( ipv4[ 1 ] ), Number( ipv4[ 2 ] ) ]
		: [ -1, -1 ];

	if (
		'localhost' === host ||
		host.endsWith( '.localhost' ) ||
		'::1' === host ||
		127 === a
	) {
		return 'this_computer';
	}

	const privateName = ! host.includes( '.' ) && ! host.includes( ':' );
	const privateSuffix =
		/\.(local|test|internal|lan|home\.arpa|example|invalid)$/.test( host );
	const privateIpv4 =
		10 === a ||
		( 172 === a && b >= 16 && b <= 31 ) ||
		( 192 === a && 168 === b ) ||
		( 169 === a && 254 === b ) ||
		( 100 === a && b >= 64 && b <= 127 );
	const privateIpv6 =
		host.includes( ':' ) && /^(f[cd]|fe[89ab])/.test( host );

	return privateName || privateSuffix || privateIpv4 || privateIpv6
		? 'private_network'
		: 'public';
}

/**
 * A well-known address for a URL. RFC 8414 (authorization servers) and RFC 9728 (protected resources) put the well-known segment between the host and the URL's path.
 *
 * @param url  Issuer or resource URL.
 * @param name Well-known name, such as `oauth-authorization-server`.
 */
export function wellKnownUrl( url: string, name: string ): string {
	const parsed = new URL( url );
	return `${ parsed.origin }/.well-known/${ name }${ parsed.pathname.replace(
		/\/$/,
		''
	) }`;
}

/**
 * The `resource_metadata` address in a WWW-Authenticate header, whichever challenge it's in. Only http(s), since the page then fetches it.
 *
 * @param wwwAuthenticate The WWW-Authenticate header.
 */
function challengeMetadataUrl( wwwAuthenticate: string ): string {
	return (
		/\bresource_metadata="(https?:\/\/[^"]+)"/i.exec(
			wwwAuthenticate
		)?.[ 1 ] ?? ''
	);
}

const fail = (
	code: string,
	summary: string,
	extra: Partial< CheckResult > = {}
): CheckResult => ( { status: 'fail', code, summary, ...extra } );

const address: CheckDefinition = {
	id: 'address',
	name: __( 'Public address', 'publishio' ),
	description: __(
		'Claude.ai and ChatGPT connect to your site from the internet, so your MCP server URL must be public and start with https://.',
		'publishio'
	),
	needs: [],
	runningLabel: __( 'Checking the address', 'publishio' ),
	run: async ( { mcpUrl } ) => {
		const url = new URL( mcpUrl );
		const notes = [
			/* translators: %s: MCP server URL */
			sprintf( __( 'MCP server URL: `%s`', 'publishio' ), mcpUrl ),
		];
		const reach = hostReach( url.hostname );

		if ( 'this_computer' === reach ) {
			return {
				status: 'warning',
				code: reach,
				summary: __( 'Only this computer can reach it', 'publishio' ),
				notes,
			};
		}
		if ( 'private_network' === reach ) {
			return {
				status: 'warning',
				code: reach,
				summary: __(
					'Only your local network can reach it',
					'publishio'
				),
				notes,
			};
		}
		if ( 'https:' !== url.protocol ) {
			return fail( 'no_https', __( 'Uses http://', 'publishio' ), {
				notes,
			} );
		}
		return {
			status: 'pass',
			code: 'ok',
			summary: __( 'Public and secure', 'publishio' ),
			notes,
		};
	},
};

const mcp: CheckDefinition = {
	id: 'mcp',
	name: __( 'MCP server', 'publishio' ),
	description: __(
		"Your site's MCP server must answer AI apps. A security plugin, firewall or password protection can stop it.",
		'publishio'
	),
	needs: [],
	runningLabel: __( 'Sending the first message an app sends', 'publishio' ),
	run: async ( { mcpUrl, origin } ) => {
		const target = new URL( mcpUrl ).origin;
		if ( target !== origin ) {
			const notes = [
				sprintf(
					/* translators: 1: this page's origin, 2: the MCP server's origin */
					__(
						'This page is on %1$s, and the MCP server is on %2$s.',
						'publishio'
					),
					origin,
					target
				),
			];
			return {
				status: 'skip',
				code: 'other_origin',
				summary: __( "Can't check from this page", 'publishio' ),
				notes,
			};
		}

		const result = await probe( mcpUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				Accept: 'application/json, text/event-stream',
				Authorization: `Bearer ${ MADE_UP_KEY }`,
			},
			body: INITIALIZE,
		} );
		const exchanges: Exchange[] = [
			{
				request: sprintf(
					/* translators: %s: request line */
					__(
						'%s: JSON-RPC `initialize`, made-up access key, no cookies',
						'publishio'
					),
					`\`POST ${ mcpUrl }\``
				),
				expected: __(
					'401 with `WWW-Authenticate: Bearer resource_metadata="…"`',
					'publishio'
				),
				got: describe( result ),
			},
		];

		if ( result.ok && 401 === result.reply.status ) {
			const metadata = challengeMetadataUrl(
				result.reply.wwwAuthenticate
			);
			if ( metadata ) {
				return {
					status: 'pass',
					code: 'ok',
					summary: __(
						'Answers AI apps and asks them to connect',
						'publishio'
					),
					exchanges,
					output: {
						resourceMetadataUrl: metadata,
						keyCode: restCode( result.reply.json ),
						keyGot: describe( result ),
					},
				};
			}
			if ( restCode( result.reply.json ) ) {
				return fail(
					'no_challenge',
					__( 'The request to connect is missing', 'publishio' ),
					{ exchanges }
				);
			}
		}

		if ( result.ok && 'rest_no_route' === restCode( result.reply.json ) ) {
			return fail(
				'not_found',
				__( "Publishio's MCP server isn't running", 'publishio' ),
				{ exchanges }
			);
		}

		return fail(
			classify( result ),
			__( "Doesn't answer AI apps", 'publishio' ),
			{ exchanges }
		);
	},
};

async function discoveryProblem(
	result: ProbeResult,
	context: CheckContext
): Promise< string > {
	// Discovery can't work at all with Plain permalinks, whatever the response: usually a redirect to the homepage.
	if ( 'plain' === ( await context.facts() )?.permalinks ) {
		return 'plain_permalinks';
	}
	if ( result.ok && ! result.reply.redirected ) {
		const { reply } = result;
		if (
			( 403 === reply.status || 404 === reply.status ) &&
			! fromWordPress( reply )
		) {
			return 'blocked_before_wordpress';
		}
		if ( 404 === reply.status ) {
			return 'not_found_in_wordpress';
		}
		if ( 200 === reply.status ) {
			return isRecord( reply.json )
				? 'wrong_details'
				: 'answered_by_other';
		}
	}
	return classify( result );
}

const details: CheckDefinition = {
	id: 'details',
	name: __( 'Connection details', 'publishio' ),
	description: __(
		"Before connecting, an AI app reads your site's connection details from two addresses under /.well-known/. They tell it where your approval page is. Some web servers and firewalls block them.",
		'publishio'
	),
	needs: [ 'mcp' ],
	runningLabel: __( 'Reading the connection details', 'publishio' ),
	run: async ( context ) => {
		const steps = [
			__( '1. Resource details', 'publishio' ),
			__( '2. Authorization server details', 'publishio' ),
			__( '3. Approval page', 'publishio' ),
		];
		const exchanges: Exchange[] = [];
		const stop = async (
			result: ProbeResult,
			failed: number,
			code?: string
		): Promise< CheckResult > => {
			const skipped = steps.slice( failed ).map( ( step ) => ( {
				step,
				request: '–',
				expected: '–',
				got: sprintf(
					/* translators: %d: number of the step that failed */
					__( 'Not checked: step %d failed', 'publishio' ),
					failed
				),
			} ) );
			return fail(
				code ?? ( await discoveryProblem( result, context ) ),
				__( "AI apps can't read them", 'publishio' ),
				{ exchanges: [ ...exchanges, ...skipped ] }
			);
		};

		const resourceUrl =
			context.output( 'mcp' )[ 'resourceMetadataUrl' ] ?? '';
		const resource = await probe( resourceUrl );
		exchanges.push( {
			step: steps[ 0 ]!,
			request: `\`GET ${ resourceUrl }\``,
			expected: __(
				'200 JSON from Publishio, `resource` equal to the MCP server URL',
				'publishio'
			),
			got: describe( resource ),
		} );
		const resourceDoc =
			resource.ok &&
			200 === resource.reply.status &&
			isRecord( resource.reply.json )
				? resource.reply.json
				: null;
		if ( ! resourceDoc ) {
			return stop( resource, 1 );
		}

		const servers = resourceDoc[ 'authorization_servers' ];
		const issuer =
			Array.isArray( servers ) && typeof servers[ 0 ] === 'string'
				? servers[ 0 ]
				: '';
		if (
			untrailed( String( resourceDoc[ 'resource' ] ) ) !==
				untrailed( context.mcpUrl ) ||
			! issuer
		) {
			exchanges[ 0 ]!.got +=
				' ' +
				sprintf(
					/* translators: 1: resource found, 2: MCP server URL */
					__( '`resource` is `%1$s`, expected `%2$s`.', 'publishio' ),
					String( resourceDoc[ 'resource' ] ),
					context.mcpUrl
				);
			return stop( resource, 1, 'wrong_details' );
		}

		const metadataUrl = wellKnownUrl(
			issuer,
			'oauth-authorization-server'
		);
		const server = await probe( metadataUrl );
		exchanges.push( {
			step: steps[ 1 ]!,
			request: `\`GET ${ metadataUrl }\``,
			expected: __(
				'200 JSON from Publishio with this site as the issuer, S256 and Client ID Metadata Document support',
				'publishio'
			),
			got: describe( server ),
		} );
		const serverDoc =
			server.ok &&
			200 === server.reply.status &&
			isRecord( server.reply.json )
				? server.reply.json
				: null;
		if ( ! serverDoc ) {
			return stop( server, 2 );
		}

		const methods = serverDoc[ 'code_challenge_methods_supported' ];
		const authorizeUrl = serverDoc[ 'authorization_endpoint' ];
		const wrong = [
			serverDoc[ 'issuer' ] !== issuer && 'issuer',
			! ( Array.isArray( methods ) && methods.includes( 'S256' ) ) &&
				'code_challenge_methods_supported',
			true !== serverDoc[ 'client_id_metadata_document_supported' ] &&
				'client_id_metadata_document_supported',
			typeof authorizeUrl !== 'string' && 'authorization_endpoint',
			typeof serverDoc[ 'token_endpoint' ] !== 'string' &&
				'token_endpoint',
		].filter( ( field ): field is string => Boolean( field ) );
		if ( wrong.length || typeof authorizeUrl !== 'string' ) {
			exchanges[ 1 ]!.got +=
				' ' +
				sprintf(
					/* translators: %s: list of metadata fields */
					__( 'Missing or wrong: %s.', 'publishio' ),
					wrong.map( ( field ) => `\`${ field }\`` ).join( ', ' )
				);
			return stop( server, 2, 'wrong_details' );
		}

		const approval = await probe( authorizeUrl );
		exchanges.push( {
			step: steps[ 2 ]!,
			request: `\`GET ${ authorizeUrl }\``,
			expected: __(
				'400, Publishio\'s "Authorization error" page',
				'publishio'
			),
			got: describe( approval ),
		} );
		// Publishio's error page is the only 400 here that forbids framing.
		if (
			! (
				approval.ok &&
				400 === approval.reply.status &&
				approval.reply.csp.includes( "frame-ancestors 'none'" )
			)
		) {
			return stop( approval, 3 );
		}

		return {
			status: 'pass',
			code: 'ok',
			summary: __(
				'Both addresses answer, and the approval page opens',
				'publishio'
			),
			exchanges,
		};
	},
};

function appGot( app: AppCheck ): string {
	const time = undefined === app.ms ? '' : ` (${ app.ms } ms)`;
	const host = APP_HOSTS[ app.id ];

	switch ( app.code ) {
		case 'ok':
			return (
				sprintf(
					/* translators: %d: HTTP status */
					__( '%d, accepted', 'publishio' ),
					app.status ?? 200
				) + time
			);
		case 'blocked_by_wordpress':
			return sprintf(
				/* translators: %s: host name */
				__(
					"Not attempted: `WP_HTTP_BLOCK_EXTERNAL` is on, and %s isn't in `WP_ACCESSIBLE_HOSTS`",
					'publishio'
				),
				host
			);
		case 'no_curl':
			return __(
				"Not attempted: WordPress can't use cURL with SSL on this server",
				'publishio'
			);
		case 'ssl_verify_off':
			return __(
				'Not attempted: `https_ssl_verify` is filtered to false',
				'publishio'
			);
		case 'dns':
			return __(
				"The host didn't resolve, or resolved to a private address",
				'publishio'
			);
		case 'request_intercepted':
			return __(
				'Not sent through cURL: a filter answered it, or WordPress used another transport',
				'publishio'
			);
		case 'not_json':
			return (
				sprintf(
					/* translators: 1: HTTP status, 2: content type */
					__( '%1$d `%2$s`, not JSON', 'publishio' ),
					app.status ?? 200,
					app.detail ?? ''
				) +
				( app.server ? `, \`server: ${ app.server }\`` : '' ) +
				time
			);
		case 'invalid_document':
			return (
				sprintf(
					/* translators: 1: HTTP status, 2: why the document was rejected */
					__( '%1$d, not accepted: %2$s', 'publishio' ),
					app.status ?? 200,
					app.detail ?? ''
				) + time
			);
		default:
			if ( app.error ) {
				return (
					sprintf(
						/* translators: %s: cURL error */
						__( 'Connection failed: `%s`', 'publishio' ),
						app.error
					) + time
				);
			}
			if ( undefined === app.status ) {
				return __( 'No response', 'publishio' ) + time;
			}
			return (
				sprintf(
					/* translators: 1: HTTP status, 2: host name */
					__( '%1$d from %2$s', 'publishio' ),
					app.status,
					host
				) +
				( app.server ? `, \`server: ${ app.server }\`` : '' ) +
				time
			);
	}
}

const apps: CheckDefinition = {
	id: 'apps',
	name: __( 'App verification', 'publishio' ),
	description: __(
		"When the approval page opens for an app, your site checks the app's identity on the app's own website (claude.ai or chatgpt.com). Some hosts block these outgoing connections.",
		'publishio'
	),
	needs: [],
	runningLabel: __( 'Contacting claude.ai and chatgpt.com', 'publishio' ),
	run: async ( context ) => {
		let data;
		try {
			data = await context.apps();
		} catch {
			// The REST API is on the site's address too, so from another origin the request goes without the login cookie.
			if ( new URL( context.mcpUrl ).origin !== context.origin ) {
				return {
					status: 'skip',
					code: 'other_origin',
					summary: __( "Can't check from this page", 'publishio' ),
				};
			}
			return fail(
				'check_failed',
				__( "Couldn't run on your server", 'publishio' ),
				{
					apps: APP_IDS.map( ( id ) => ( {
						id,
						code: 'check_failed',
					} ) ),
				}
			);
		}

		const exchanges: Exchange[] = data.apps.map( ( app ) => ( {
			step: APP_NAMES[ app.id ],
			request: `\`GET ${ app.url }\``,
			expected: __( '200 JSON, accepted', 'publishio' ),
			got: appGot( app ),
		} ) );
		const failed = data.apps.filter( ( app ) => ! app.ok );
		if ( ! failed.length ) {
			return {
				status: 'pass',
				code: 'ok',
				summary: sprintf(
					/* translators: %s: app names */
					__( '%s verified', 'publishio' ),
					appNames( data.apps.map( ( app ) => app.id ) )
				),
				exchanges,
			};
		}

		const { transport } = data;
		const notes = transport
			? [
					sprintf(
						/* translators: 1: cURL version, 2: SSL library version */
						__(
							'Outgoing connections: cURL %1$s, %2$s',
							'publishio'
						),
						transport.curl ?? '–',
						transport.ssl ?? '–'
					),
					`\`WP_HTTP_BLOCK_EXTERNAL\`: ${
						transport.blockExternal ? 'on' : 'off'
					}`,
					sprintf(
						/* translators: %s: on or off */
						__( 'Proxy: %s', 'publishio' ),
						transport.proxy ? 'on' : 'off'
					),
			  ]
			: [];
		return {
			status: failed.length === data.apps.length ? 'fail' : 'warning',
			code: failed[ 0 ]!.code,
			apps: failed.map( ( { id, code } ) => ( { id, code } ) ),
			summary: sprintf(
				/* translators: %s: app names */
				__( "Couldn't verify %s", 'publishio' ),
				appNames( failed.map( ( app ) => app.id ) )
			),
			exchanges,
			notes,
		};
	},
};

const access: CheckDefinition = {
	id: 'access',
	name: __( 'Access after approval', 'publishio' ),
	description: __(
		'After you click Allow, the app collects an access key from your site and sends it with every request. If your server drops the key, the app connects but every action fails.',
		'publishio'
	),
	needs: [ 'mcp' ],
	runningLabel: __( 'Checking the token address and the key', 'publishio' ),
	run: async ( context ) => {
		const mcpOutput = context.output( 'mcp' );
		const token = await probe( context.tokenUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: '',
		} );
		const tokenOk =
			token.ok &&
			400 === token.reply.status &&
			isRecord( token.reply.json ) &&
			'unsupported_grant_type' === token.reply.json[ 'error' ];
		const exchanges: Exchange[] = [
			{
				step: __( 'Token address', 'publishio' ),
				request: sprintf(
					/* translators: %s: request line */
					__( '%s, empty form, no cookies', 'publishio' ),
					`\`POST ${ context.tokenUrl }\``
				),
				expected: __(
					'400 JSON, `error: unsupported_grant_type`',
					'publishio'
				),
				got: tokenOk
					? sprintf(
							/* translators: %d: milliseconds */
							__( 'As expected (%d ms)', 'publishio' ),
							token.reply.ms
					  )
					: describe( token ),
			},
			{
				step: __( 'Access key', 'publishio' ),
				request: __(
					"The MCP server check's request, which carried a made-up key",
					'publishio'
				),
				expected: __(
					'401 from WordPress, code `rest_oauth_invalid_token`: Publishio got the key and rejected it',
					'publishio'
				),
				got: mcpOutput[ 'keyGot' ] ?? '',
			},
		];
		const notes =
			token.ok && token.reply.server
				? [
						sprintf(
							/* translators: %s: web server name */
							__(
								'Web server: %s (from the `server` header)',
								'publishio'
							),
							token.reply.server
						),
				  ]
				: [];

		if ( ! tokenOk ) {
			return fail(
				classify( token ),
				__( 'The token address is blocked', 'publishio' ),
				{ exchanges, notes }
			);
		}

		switch ( mcpOutput[ 'keyCode' ] ) {
			case 'rest_oauth_invalid_token':
				return {
					status: 'pass',
					code: 'ok',
					summary: __( 'Access keys reach Publishio', 'publishio' ),
					exchanges,
				};
			case 'rest_forbidden':
				return fail(
					'key_dropped',
					__( 'Your server drops the access key', 'publishio' ),
					{ exchanges, notes }
				);
			default:
				return fail(
					'blocked_earlier',
					__( 'Another plugin rejects the key first', 'publishio' ),
					{ exchanges, notes }
				);
		}
	},
};

export const CHECKS: CheckDefinition[] = [
	address,
	mcp,
	details,
	apps,
	access,
];
