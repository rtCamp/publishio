/**
 * OAuth 2.1 helpers for the E2E tests.
 */
/**
 * External dependencies
 */
import crypto from 'node:crypto';
import { expect } from '@playwright/test';
/**
 * Internal dependencies
 */
import { wpCli } from './wp-cli';

export const TEST_REDIRECT_URI = 'http://localhost:9999/callback';

/** Must not be the site's host or a blocked suffix (.local, .localhost). */
export const TEST_CLIENT_HOST = 'e2e-client.example.com';

/**
 * @typedef {Object} Discovery
 * @property {string}  resource               MCP server URL (protected resource).
 * @property {string}  issuer                 Authorization server issuer.
 * @property {string}  authorizationEndpoint  Consent screen URL.
 * @property {string}  tokenEndpoint          Token endpoint URL.
 * @property {string}  [registrationEndpoint] Only on servers that still support dynamic client registration.
 * @property {boolean} cimd                   True when clients are identified by Client ID Metadata Document URLs.
 */

/**
 * @typedef {Object} RegisteredClient
 * @property {string}   client_id       Client ID (a metadata document URL on CIMD servers).
 * @property {string}   [client_secret] Never set for public clients.
 * @property {string}   client_name     Name shown on the consent screen.
 * @property {string[]} redirect_uris   Allowed callback URLs.
 */

/**
 * @typedef {Object} Tokens
 * @property {string} access_token  Bearer token for the MCP server.
 * @property {string} refresh_token Token to get a new access token.
 * @property {string} token_type    Always "Bearer".
 * @property {number} expires_in    Lifetime in seconds.
 * @property {string} scope         Granted scopes.
 */

const b64url = ( buf ) =>
	buf
		.toString( 'base64' )
		.replace( /\+/g, '-' )
		.replace( /\//g, '_' )
		.replace( /=+$/, '' );

export function createPkce() {
	const verifier = b64url( crypto.randomBytes( 32 ) );
	const challenge = b64url(
		crypto.createHash( 'sha256' ).update( verifier ).digest()
	);
	return { verifier, challenge };
}

export function randomState() {
	return b64url( crypto.randomBytes( 16 ) );
}

/**
 * @param {import('@playwright/test').APIRequestContext} api Request context to send with.
 */
export async function discover( api ) {
	const pr = await api.get( '/.well-known/oauth-protected-resource' );
	expect( pr.status(), 'protected resource metadata must be reachable' ).toBe(
		200
	);
	const prJson = await pr.json();

	const as = await api.get( '/.well-known/oauth-authorization-server' );
	expect(
		as.status(),
		'authorization server metadata must be reachable'
	).toBe( 200 );
	const asJson = await as.json();

	return {
		resource: prJson.resource,
		issuer: asJson.issuer,
		authorizationEndpoint: asJson.authorization_endpoint,
		tokenEndpoint: asJson.token_endpoint,
		registrationEndpoint: asJson.registration_endpoint,
		cimd: asJson.client_id_metadata_document_supported === true,
	};
}

/**
 * Cached CIMD client on CIMD servers, otherwise dynamic registration.
 * @param {import('@playwright/test').APIRequestContext} api       Request context to send with.
 * @param {Discovery}                                    discovery Result of discover().
 * @param {Object}                                       overrides Fields to override.
 */
export async function registerClient( api, discovery, overrides = {} ) {
	const metadata = {
		client_name: `E2E Client ${ randomState().slice( 0, 6 ) }`,
		redirect_uris: [ TEST_REDIRECT_URI ],
		token_endpoint_auth_method: 'none',
		grant_types: [ 'authorization_code', 'refresh_token' ],
		response_types: [ 'code' ],
		...overrides,
	};

	if ( discovery.cimd ) {
		return provisionMetadataClient( metadata );
	}

	expect(
		discovery.registrationEndpoint,
		'server supports neither CIMD nor dynamic registration'
	).toBeTruthy();
	const res = await api.post( discovery.registrationEndpoint, {
		data: metadata,
	} );
	expect( res.status(), await res.text() ).toBe( 201 );
	return res.json();
}

/**
 * Seed the CIMD cache directly; the test site cannot reliably fetch a real document.
 * @param {{client_name: string, redirect_uris: string[]}} metadata Client metadata.
 */
export function provisionMetadataClient( metadata ) {
	const client_id = `https://${ TEST_CLIENT_HOST }/clients/${ randomState().slice(
		0,
		12
	) }.json`;
	const record = {
		client_id,
		client_name: metadata.client_name,
		redirect_uris: metadata.redirect_uris,
		client_uri: null,
		tos_uri: null,
		policy_uri: null,
	};
	const payload = Buffer.from( JSON.stringify( record ) ).toString(
		'base64'
	);
	wpCli(
		`eval '$c = json_decode( base64_decode( "${ payload }" ), true ); set_transient( "publishio_cimd_" . hash( "sha256", $c["client_id"] ), $c, HOUR_IN_SECONDS );'`
	);
	return {
		client_id,
		client_name: record.client_name,
		redirect_uris: record.redirect_uris,
	};
}

export function buildAuthorizeUrl(
	discovery,
	client,
	pkce,
	state,
	overrides = {}
) {
	const url = new URL( discovery.authorizationEndpoint );
	const params = {
		response_type: 'code',
		client_id: client.client_id,
		redirect_uri: client.redirect_uris[ 0 ],
		code_challenge: pkce.challenge,
		code_challenge_method: 'S256',
		state,
		scope: 'mcp:read mcp:write',
		resource: discovery.resource,
		...overrides,
	};
	for ( const [ k, v ] of Object.entries( params ) ) {
		url.searchParams.set( k, v );
	}
	return url.toString();
}

/**
 * Submit the consent screen over HTTP; returns the redirect URL.
 * @param {import('@playwright/test').APIRequestContext} userApi      Request context logged in as the approving user.
 * @param {string}                                       authorizeUrl Authorization URL with all OAuth parameters.
 * @param {'approve'|'deny'}                             consent      Button to press.
 */
export async function submitConsentViaApi(
	userApi,
	authorizeUrl,
	consent = 'approve'
) {
	const page = await userApi.get( authorizeUrl, { maxRedirects: 0 } );
	expect(
		page.status(),
		'consent screen should render for a logged-in editor'
	).toBe( 200 );
	const html = await page.text();

	const nonce = html.match( /name="_wpnonce" value="([^"]+)"/ )?.[ 1 ];
	expect( nonce, 'consent form must include a nonce' ).toBeTruthy();

	const form = { _wpnonce: nonce, consent };
	for ( const [ k, v ] of new URL( authorizeUrl ).searchParams ) {
		form[ k ] = v;
	}

	const res = await userApi.post( authorizeUrl.split( '?' )[ 0 ], {
		form,
		maxRedirects: 0,
	} );
	expect(
		res.status(),
		'consent submission should redirect to the client'
	).toBe( 302 );
	return new URL( res.headers().location );
}

export async function exchangeCode( api, discovery, client, code, verifier ) {
	return api.post( discovery.tokenEndpoint, {
		form: {
			grant_type: 'authorization_code',
			code,
			client_id: client.client_id,
			redirect_uri: client.redirect_uris[ 0 ],
			code_verifier: verifier,
			resource: discovery.resource,
		},
	} );
}

export async function refreshTokens( api, discovery, client, refreshToken ) {
	return api.post( discovery.tokenEndpoint, {
		form: {
			grant_type: 'refresh_token',
			refresh_token: refreshToken,
			client_id: client.client_id,
		},
	} );
}

/**
 * Full connect flow over HTTP.
 * @param {import('@playwright/test').APIRequestContext} anonApi         Request context without cookies, as an AI client.
 * @param {import('@playwright/test').APIRequestContext} userApi         Request context logged in as the approving user.
 * @param {Object}                                       clientOverrides Client metadata overrides.
 */
export async function connect( anonApi, userApi, clientOverrides = {} ) {
	const discovery = await discover( anonApi );
	const client = await registerClient( anonApi, discovery, clientOverrides );
	const pkce = createPkce();
	const state = randomState();

	const redirect = await submitConsentViaApi(
		userApi,
		buildAuthorizeUrl( discovery, client, pkce, state )
	);
	expect( redirect.searchParams.get( 'state' ) ).toBe( state );
	const code = redirect.searchParams.get( 'code' );
	expect( code ).toBeTruthy();

	const tokenRes = await exchangeCode(
		anonApi,
		discovery,
		client,
		code,
		pkce.verifier
	);
	expect( tokenRes.status(), await tokenRes.text() ).toBe( 200 );

	return { discovery, client, tokens: await tokenRes.json() };
}

/**
 * Uses the request event: page.route() misses redirected requests; the failed navigation is expected.
 * @param {import('@playwright/test').Page} page Page to use.
 */
export function captureCallback( page ) {
	let callback;
	const origin = new URL( TEST_REDIRECT_URI ).origin;
	page.on( 'request', ( request ) => {
		if ( request.url().startsWith( origin ) ) {
			callback = new URL( request.url() );
		}
	} );
	return () => callback;
}
