/**
 * Tests for the site check module: checks, runner, verdict, store and report.
 */
/**
 * External dependencies
 */
import {
	CHECKS,
	hostReach,
	wellKnownUrl,
} from '@/admin/shared/site-check/checks';
import { problemFor } from '@/admin/shared/site-check/problems';
import { classify } from '@/admin/shared/site-check/probe';
import type { ProbeResult, Reply } from '@/admin/shared/site-check/probe';
import { buildReport } from '@/admin/shared/site-check/report';
import { initialItems, runChecks } from '@/admin/shared/site-check/runner';
import {
	MAX_AGE,
	loadCheck,
	saveCheck,
} from '@/admin/shared/site-check/storage';
import { verdictOf } from '@/admin/shared/site-check/verdict';
import type {
	AppsResult,
	CheckDefinition,
	CheckId,
	CheckResult,
	RunItems,
	SiteFacts,
} from '@/admin/shared/site-check/types';

jest.mock( '@wordpress/i18n', () => jest.requireActual( '@wordpress/i18n' ) );

const SITE = 'https://example.com';
const MCP_URL = `${ SITE }/wp-json/mcp/publishio`;
const TOKEN_URL = `${ SITE }/wp-json/publishio-oauth/v1/token`;
const RESOURCE_URL = `${ SITE }/.well-known/oauth-protected-resource/wp-json/mcp/publishio`;
const SERVER_URL = `${ SITE }/.well-known/oauth-authorization-server`;
const AUTHORIZE_URL = `${ SITE }/publishio-oauth/authorize`;

const FACTS: SiteFacts = {
	publishioVersion: '1.3.0',
	wpVersion: '7.1.3',
	phpVersion: '8.4.18',
	multisite: false,
	homeUrl: SITE,
	siteUrl: SITE,
	mcpUrl: MCP_URL,
	permalinks: 'pretty',
	mcpAdapter: { version: '0.6.1', bundled: true, source: 'publishio' },
	tools: 21,
};

const APPS_OK: AppsResult = {
	apps: [
		{
			id: 'claude',
			url: 'https://claude.ai/oauth/mcp-oauth-client-metadata',
			ok: true,
			code: 'ok',
			status: 200,
			ms: 120,
		},
		{
			id: 'claude_code',
			url: 'https://claude.ai/oauth/claude-code-client-metadata',
			ok: true,
			code: 'ok',
			status: 200,
			ms: 110,
		},
		{
			id: 'chatgpt',
			url: 'https://chatgpt.com/oauth/client.json',
			ok: true,
			code: 'ok',
			status: 200,
			ms: 140,
		},
	],
};

const TRANSPORT: NonNullable< AppsResult[ 'transport' ] > = {
	curl: '8.5.0',
	ssl: 'OpenSSL/3.0.13',
	blockExternal: false,
	proxy: false,
};

/**
 * The healthy apps result, with some apps failing.
 *
 * @param failures Failure code by app id.
 */
function appsFailing( failures: Record< string, string > ): AppsResult {
	return {
		apps: APPS_OK.apps.map( ( app ) =>
			failures[ app.id ]
				? { ...app, ok: false, code: failures[ app.id ]! }
				: app
		),
		transport: TRANSPORT,
	};
}

type Answer = {
	status: number;
	body?: unknown;
	headers?: Record< string, string >;
};

function fakeFetch(
	answers: Record< string, Answer >,
	redirectsAway: string[] = []
) {
	return jest.fn( async ( url: string, init?: RequestInit ) => {
		if ( redirectsAway.includes( url ) && 'manual' === init?.redirect ) {
			return { type: 'opaqueredirect', status: 0 };
		}
		const answer = answers[ url ];
		if ( ! answer || redirectsAway.includes( url ) ) {
			throw new TypeError( 'Failed to fetch' );
		}
		const isJson = typeof answer.body !== 'string';
		const headers: Record< string, string > = {
			'content-type': isJson ? 'application/json' : 'text/html',
			...answer.headers,
		};
		return {
			url,
			redirected: false,
			status: answer.status,
			headers: {
				get: ( name: string ) => headers[ name.toLowerCase() ] ?? null,
			},
			text: async () =>
				isJson
					? JSON.stringify( answer.body )
					: ( answer.body as string ),
		};
	} );
}

const HEALTHY: Record< string, Answer > = {
	[ MCP_URL ]: {
		status: 401,
		body: {
			code: 'rest_oauth_invalid_token',
			message: 'Invalid or expired access token.',
			data: { status: 401 },
		},
		headers: {
			'www-authenticate': `Bearer resource_metadata="${ RESOURCE_URL }", scope="mcp:read mcp:write"`,
		},
	},
	[ RESOURCE_URL ]: {
		status: 200,
		body: { resource: MCP_URL, authorization_servers: [ SITE ] },
	},
	[ SERVER_URL ]: {
		status: 200,
		body: {
			issuer: SITE,
			authorization_endpoint: AUTHORIZE_URL,
			token_endpoint: TOKEN_URL,
			code_challenge_methods_supported: [ 'S256' ],
			client_id_metadata_document_supported: true,
		},
	},
	[ AUTHORIZE_URL ]: {
		status: 400,
		body: '<title>Authorization error</title>',
		headers: { 'content-security-policy': "frame-ancestors 'none'" },
	},
	[ TOKEN_URL ]: { status: 400, body: { error: 'unsupported_grant_type' } },
};

const context = (
	overrides: Partial< {
		origin: string;
		facts: SiteFacts;
		apps: AppsResult;
	} > = {}
) => ( {
	mcpUrl: MCP_URL,
	tokenUrl: TOKEN_URL,
	origin: overrides.origin ?? SITE,
	facts: async () => overrides.facts ?? FACTS,
	apps: async () => overrides.apps ?? APPS_OK,
} );

async function run( answers: Record< string, Answer >, overrides = {} ) {
	global.fetch = fakeFetch( answers ) as unknown as typeof fetch;
	return runChecks( CHECKS, context( overrides ), () => {} );
}

const codes = ( items: RunItems ) =>
	Object.fromEntries(
		Object.entries( items ).map( ( [ id, item ] ) => [
			id,
			`${ item.state }:${ item.result?.code }`,
		] )
	);

describe( 'hostReach', () => {
	it.each( [
		[ 'localhost', 'this_computer' ],
		[ 'publishio.localhost', 'this_computer' ],
		[ '127.0.0.1', 'this_computer' ],
		[ '[::1]', 'this_computer' ],
		[ 'mysite.local', 'private_network' ],
		[ 'mysite.test', 'private_network' ],
		[ 'intranet', 'private_network' ],
		[ '192.168.1.20', 'private_network' ],
		[ '172.20.0.5', 'private_network' ],
		[ '100.64.1.1', 'private_network' ],
		[ '[fd12::1]', 'private_network' ],
		[ 'example.com', 'public' ],
		[ '172.32.0.1', 'public' ],
		[ 'localhost.example.com', 'public' ],
		[ '127.5.5.5', 'this_computer' ],
		[ '10.0.0.1', 'private_network' ],
		[ '169.254.169.254', 'private_network' ],
		[ '100.127.255.1', 'private_network' ],
		[ '100.128.0.1', 'public' ],
		[ '172.15.0.1', 'public' ],
		[ '10.example.com', 'public' ],
		[ '172.16.example.org', 'public' ],
		[ '127.example.com', 'public' ],
	] )( '%s is %s', ( host, reach ) => {
		expect( hostReach( host ) ).toBe( reach );
	} );
} );

describe( 'wellKnownUrl', () => {
	it( 'puts the well-known segment before the path (RFC 8414 and RFC 9728)', () => {
		expect(
			wellKnownUrl( 'https://example.com', 'oauth-authorization-server' )
		).toBe( SERVER_URL );
		expect(
			wellKnownUrl(
				'https://example.com/blog/',
				'oauth-authorization-server'
			)
		).toBe( `${ SERVER_URL }/blog` );
		expect( wellKnownUrl( MCP_URL, 'oauth-protected-resource' ) ).toBe(
			RESOURCE_URL
		);
	} );

	it( 'takes linear time on a long run of slashes', () => {
		const started = performance.now();
		wellKnownUrl(
			`${ SITE }/${ '/'.repeat( 100000 ) }x`,
			'oauth-protected-resource'
		);

		expect( performance.now() - started ).toBeLessThan( 1000 );
	} );
} );

describe( 'classify', () => {
	const reply = ( fields: Partial< Reply > ): ProbeResult => ( {
		ok: true,
		reply: {
			url: SITE,
			redirected: false,
			status: 200,
			contentType: 'text/html',
			server: '',
			cfMitigated: '',
			wwwAuthenticate: '',
			csp: '',
			wordpressPage: false,
			json: null,
			title: '',
			ms: 1,
			...fields,
		},
	} );

	it.each( [
		[
			{ ok: false, error: 'Failed to fetch', ms: 1 } as ProbeResult,
			'unreachable',
		],
		[
			{
				ok: false,
				error: 'Failed to fetch',
				ms: 1,
				redirectedAway: true,
			} as ProbeResult,
			'redirected_away',
		],
		[ reply( { redirected: true } ), 'redirected' ],
		[
			reply( { status: 401, wwwAuthenticate: 'Basic realm="Staging"' } ),
			'password_protected',
		],
		[ reply( { status: 502 } ), 'server_error' ],
		[
			reply( { status: 403, server: 'openresty' } ),
			'blocked_before_wordpress',
		],
		[
			reply( {
				status: 404,
				json: { code: 'rest_no_route', message: 'No route' },
			} ),
			'not_found',
		],
		[
			reply( {
				status: 403,
				json: {
					code: 'rest_cannot_access',
					message: 'Only logged-in users.',
				},
			} ),
			'rest_blocked',
		],
	] )( 'labels %#', ( result, code ) => {
		expect( classify( result ) ).toBe( code );
	} );
} );

describe( 'checks against a site', () => {
	it( 'all pass on a healthy site', async () => {
		const items = await run( HEALTHY );

		expect( codes( items ) ).toEqual( {
			address: 'pass:ok',
			mcp: 'pass:ok',
			details: 'pass:ok',
			apps: 'pass:ok',
			access: 'pass:ok',
		} );
		expect( verdictOf( CHECKS, items ) ).toMatchObject( {
			tone: 'success',
			title: 'Ready',
		} );
	} );

	it( 'names the web server when it blocks /.well-known/ (#118), and only that check fails', async () => {
		const items = await run( {
			...HEALTHY,
			[ RESOURCE_URL ]: {
				status: 403,
				body: '<title>403 Forbidden</title>',
				headers: { server: 'openresty' },
			},
		} );

		expect( codes( items ) ).toMatchObject( {
			details: 'fail:blocked_before_wordpress',
			access: 'pass:ok',
		} );
		expect( items.details.result?.exchanges?.[ 0 ]?.got ).toContain(
			'server: openresty'
		);
		expect( items.details.result?.exchanges?.[ 1 ]?.got ).toContain(
			'Not checked'
		);
	} );

	it( 'blames Plain permalinks for a WordPress 404', async () => {
		const items = await run(
			{
				...HEALTHY,
				[ RESOURCE_URL ]: {
					status: 404,
					body: '<link href="/wp-content/themes/x.css">',
				},
			},
			{ facts: { ...FACTS, permalinks: 'plain' } }
		);

		expect( items.details.result?.code ).toBe( 'plain_permalinks' );
	} );

	it( 'reports a dropped access key in the access check only', async () => {
		const items = await run( {
			...HEALTHY,
			[ MCP_URL ]: {
				...HEALTHY[ MCP_URL ]!,
				body: {
					code: 'rest_forbidden',
					message: 'Sorry, you are not allowed to do that.',
				},
			},
		} );

		expect( codes( items ) ).toMatchObject( {
			mcp: 'pass:ok',
			details: 'pass:ok',
			access: 'fail:key_dropped',
		} );
	} );

	it( 'skips the checks that need the MCP server when it fails', async () => {
		const items = await run( {
			...HEALTHY,
			[ MCP_URL ]: {
				status: 401,
				body: '<title>401</title>',
				headers: { 'www-authenticate': 'Basic realm="Staging"' },
			},
		} );

		expect( codes( items ) ).toMatchObject( {
			mcp: 'fail:password_protected',
			details: 'skip:needs',
			access: 'skip:needs',
			apps: 'pass:ok',
		} );
	} );

	it( 'finds the Bearer challenge after another challenge', async () => {
		const items = await run( {
			...HEALTHY,
			[ MCP_URL ]: {
				...HEALTHY[ MCP_URL ]!,
				headers: {
					'www-authenticate': `Basic realm="x", Bearer resource_metadata="${ RESOURCE_URL }"`,
				},
			},
		} );

		expect( codes( items ) ).toMatchObject( {
			mcp: 'pass:ok',
			details: 'pass:ok',
		} );
	} );

	it( 'ignores a resource_metadata address that isn’t http(s)', async () => {
		const items = await run( {
			...HEALTHY,
			[ MCP_URL ]: {
				...HEALTHY[ MCP_URL ]!,
				headers: {
					'www-authenticate':
						'Bearer resource_metadata="javascript:alert(1)"',
				},
			},
		} );

		expect( items.mcp.result?.code ).toBe( 'no_challenge' );
	} );

	it( 'names a redirect to another site, such as a sign-in gate', async () => {
		global.fetch = fakeFetch( HEALTHY, [
			MCP_URL,
		] ) as unknown as typeof fetch;
		const items = await runChecks( CHECKS, context(), () => {} );

		expect( items.mcp.result?.code ).toBe( 'redirected_away' );
		expect( items.mcp.result?.exchanges?.[ 0 ]?.got ).toContain(
			'Redirected to another site'
		);
	} );

	it( 'skips the browser checks when the page is on another origin', async () => {
		const items = await run( HEALTHY, {
			origin: 'https://admin.example.com',
		} );

		expect( codes( items ) ).toMatchObject( {
			mcp: 'skip:other_origin',
			details: 'skip:needs',
			access: 'skip:needs',
		} );
		expect( verdictOf( CHECKS, items ).tone ).toBe( 'blocked' );
	} );

	it( 'skips App verification too when its request fails from another origin', async () => {
		global.fetch = fakeFetch( HEALTHY ) as unknown as typeof fetch;
		const items = await runChecks(
			CHECKS,
			{
				...context( { origin: 'http://127.0.0.1:10008' } ),
				apps: () => Promise.reject( new Error( 'rest_forbidden' ) ),
			},
			() => {}
		);

		expect( codes( items ) ).toMatchObject( {
			mcp: 'skip:other_origin',
			apps: 'skip:other_origin',
		} );
		expect( verdictOf( CHECKS, items ) ).toMatchObject( {
			tone: 'blocked',
			text: expect.stringContaining(
				'Site Address and WordPress Address'
			),
		} );
	} );

	it( 'still fails App verification when its request fails on the same origin', async () => {
		global.fetch = fakeFetch( HEALTHY ) as unknown as typeof fetch;
		const items = await runChecks(
			CHECKS,
			{
				...context(),
				apps: () => Promise.reject( new Error( 'rest_forbidden' ) ),
			},
			() => {}
		);

		expect( items.apps.result?.code ).toBe( 'check_failed' );
	} );

	it( 'warns for a local address but runs everything else', async () => {
		global.fetch = fakeFetch( {} ) as unknown as typeof fetch;
		const items = await runChecks(
			[ CHECKS[ 0 ]! ],
			{
				...context(),
				mcpUrl: 'http://localhost:10008/wp-json/mcp/publishio',
			},
			() => {}
		);

		expect( items.address.result?.code ).toBe( 'this_computer' );
	} );

	it( 'warns when only one app fails, naming the ones that work', async () => {
		const items = await run( HEALTHY, {
			apps: appsFailing( { chatgpt: 'bad_status' } ),
		} );

		expect( items.apps.result ).toMatchObject( {
			status: 'warning',
			code: 'bad_status',
			apps: [ { id: 'chatgpt', code: 'bad_status' } ],
		} );
		expect( verdictOf( CHECKS, items ).title ).toBe(
			'Ready for Claude and Claude Code only'
		);
	} );

	it( 'explains each cause when apps fail for different reasons', async () => {
		const items = await run( HEALTHY, {
			apps: appsFailing( {
				claude: 'dns',
				claude_code: 'dns',
				chatgpt: 'bad_status',
			} ),
		} );
		const result = items.apps.result!;
		const problem = problemFor( 'apps', result.code, result.apps );

		expect( result.status ).toBe( 'fail' );
		expect( problem.message ).toContain(
			"Your server couldn't look up claude.ai."
		);
		expect( problem.message ).toContain(
			'chatgpt.com refused your server.'
		);
		expect( problem.fix ).toContain( "check the server's DNS" );
		expect( problem.fix ).toContain( 'A firewall at chatgpt.com' );
	} );
} );

describe( 'verdictOf', () => {
	const finished = (
		address: CheckResult,
		apps: CheckResult = { status: 'pass', code: 'ok', summary: '' }
	): RunItems => {
		const items = initialItems( CHECKS );
		for ( const check of CHECKS ) {
			const result: CheckResult = {
				status: 'pass',
				code: 'ok',
				summary: '',
			};
			items[ check.id ] = { state: 'pass', result };
		}
		items.address = { state: address.status, result: address };
		items.apps = { state: apps.status, result: apps };
		return items;
	};
	const local: CheckResult = {
		status: 'warning',
		code: 'this_computer',
		summary: '',
	};

	it( 'is ready for Claude Code only on a local address when its identity checks out', () => {
		expect( verdictOf( CHECKS, finished( local ) ) ).toMatchObject( {
			tone: 'warning',
			title: 'Ready for Claude Code only',
			text: expect.stringContaining( 'only works on this computer' ),
		} );
	} );

	it( 'is not ready on a local address when Claude Code can’t be verified', () => {
		const apps: CheckResult = {
			status: 'warning',
			code: 'connection_failed',
			summary: '',
			apps: [
				{ id: 'claude', code: 'connection_failed' },
				{ id: 'claude_code', code: 'connection_failed' },
			],
		};

		expect( verdictOf( CHECKS, finished( local, apps ) ) ).toMatchObject( {
			tone: 'error',
			title: 'Not ready',
			text: expect.stringContaining( "couldn't connect to claude.ai" ),
		} );
	} );
} );

describe( 'runChecks', () => {
	const check = (
		id: CheckId,
		needs: CheckId[],
		result: CheckResult,
		log: string[]
	): CheckDefinition => ( {
		id,
		name: id,
		description: '',
		needs,
		runningLabel: '',
		run: async ( { output } ) => {
			log.push(
				`start ${ id } ${ JSON.stringify( needs.map( output ) ) }`
			);
			await Promise.resolve();
			log.push( `end ${ id }` );
			return result;
		},
	} );

	it( 'starts independent checks together, and passes outputs to the checks that need them', async () => {
		const log: string[] = [];
		const items = await runChecks(
			[
				check(
					'mcp',
					[],
					{
						status: 'pass',
						code: 'ok',
						summary: '',
						output: { key: 'value' },
					},
					log
				),
				check(
					'apps',
					[],
					{ status: 'pass', code: 'ok', summary: '' },
					log
				),
				check(
					'access',
					[ 'mcp' ],
					{ status: 'pass', code: 'ok', summary: '' },
					log
				),
			],
			context(),
			() => {}
		);

		expect( log.slice( 0, 2 ) ).toEqual( [
			'start mcp []',
			'start apps []',
		] );
		expect( log ).toContain( 'start access [{"key":"value"}]' );
		expect( items.access.state ).toBe( 'pass' );
	} );

	it( 'reports every state change, starting with waiting for the dependents', async () => {
		const states: string[] = [];
		await runChecks(
			[
				check(
					'mcp',
					[],
					{ status: 'fail', code: 'unexpected', summary: '' },
					[]
				),
				check(
					'details',
					[ 'mcp' ],
					{ status: 'pass', code: 'ok', summary: '' },
					[]
				),
			],
			context(),
			( items ) =>
				states.push( `${ items.mcp.state }/${ items.details.state }` )
		);

		expect( states ).toEqual( [
			'running/waiting',
			'fail/waiting',
			'fail/skip',
		] );
	} );
} );

describe( 'stored result', () => {
	beforeEach( () => window.localStorage.clear() );

	const finished = (): RunItems => {
		const items = initialItems( CHECKS );
		for ( const check of CHECKS ) {
			items[ check.id ] = {
				state: 'pass',
				result: {
					status: 'pass',
					code: 'ok',
					summary: 'Long text that is not stored',
				},
			};
		}
		items.apps = {
			state: 'warning',
			result: {
				status: 'warning',
				code: 'bad_status',
				summary: '',
				apps: [ { id: 'chatgpt', code: 'bad_status' } ],
			},
		};
		return items;
	};

	it( 'keeps only statuses and codes, and comes back for the same version and address', () => {
		saveCheck( '1.3.0', MCP_URL, finished(), 1000 );

		expect(
			window.localStorage.getItem( 'publishio-site-check' )
		).not.toContain( 'Long text' );
		expect(
			loadCheck( CHECKS, '1.3.0', MCP_URL, 2000 )?.items.apps.result
		).toMatchObject( {
			code: 'bad_status',
			apps: [ { id: 'chatgpt', code: 'bad_status' } ],
		} );
	} );

	it.each( [
		[
			'another version',
			() => loadCheck( CHECKS, '1.4.0', MCP_URL, 2000 ),
		],
		[
			'another address',
			() =>
				loadCheck(
					CHECKS,
					'1.3.0',
					'https://other.test/wp-json/mcp/publishio',
					2000
				),
		],
		[
			'older than three hours',
			() => loadCheck( CHECKS, '1.3.0', MCP_URL, 1000 + MAX_AGE + 1 ),
		],
	] )( 'is ignored for %s', ( _label, load ) => {
		saveCheck( '1.3.0', MCP_URL, finished(), 1000 );

		expect( load() ).toBeNull();
	} );

	it( 'is ignored when edited to an unknown code', () => {
		saveCheck( '1.3.0', MCP_URL, finished(), 1000 );
		const saved = JSON.parse(
			window.localStorage.getItem( 'publishio-site-check' ) ?? '{}'
		);
		saved.results.mcp.code = 'edit_wp_config_now';
		window.localStorage.setItem(
			'publishio-site-check',
			JSON.stringify( saved )
		);

		expect( loadCheck( CHECKS, '1.3.0', MCP_URL, 2000 ) ).toBeNull();
	} );

	it.each( [
		[ 'the old list of app ids', [ 'chatgpt' ] ],
		[ 'an unknown app', [ { id: 'gemini', code: 'bad_status' } ] ],
		[ 'an unknown app code', [ { id: 'chatgpt', code: 'not_ready' } ] ],
	] )( 'is ignored when it holds %s', ( _label, apps ) => {
		saveCheck( '1.3.0', MCP_URL, finished(), 1000 );
		const saved = JSON.parse(
			window.localStorage.getItem( 'publishio-site-check' ) ?? '{}'
		);
		saved.results.apps.apps = apps;
		window.localStorage.setItem(
			'publishio-site-check',
			JSON.stringify( saved )
		);

		expect( loadCheck( CHECKS, '1.3.0', MCP_URL, 2000 ) ).toBeNull();
	} );
} );

describe( 'buildReport', () => {
	it( 'lists the problem with its requests and fix, then skipped and passed checks', async () => {
		const items = await run( {
			...HEALTHY,
			[ RESOURCE_URL ]: {
				status: 403,
				body: '<title>403 Forbidden</title>',
				headers: { server: 'openresty' },
			},
		} );
		const report = buildReport( {
			checks: CHECKS,
			items,
			facts: FACTS,
			checkedAt: Date.UTC( 2026, 9, 14, 13, 5 ),
		} );

		expect( report ).toContain( '**Not ready** (1 problem)' );
		expect( report ).toContain( '### ✕ Connection details' );
		expect( report ).toContain( '| Step | Request | Expected | Got |' );
		expect( report ).toContain( 'location ^~ /.well-known/oauth-' );
		expect( report ).toContain( '- ✓ **MCP server:**' );
		expect( report ).toContain(
			'MCP Adapter 0.6.1, bundled with Publishio'
		);
		expect( report ).not.toMatch( /undefined|null/ );
	} );
} );
