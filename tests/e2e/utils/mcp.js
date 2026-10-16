/**
 * Minimal MCP client for the E2E tests.
 */
/**
 * External dependencies
 */
import { expect } from '@playwright/test';

export const PROTOCOL_VERSION = '2025-06-18';

/**
 * Tool name for an ability.
 * @param {string} abilityName Ability name, e.g. "publishio/create-post".
 */
export const tool = ( abilityName ) => abilityName.replace( /\//g, '-' );

export class McpClient {
	nextId = 1;
	sessionId;
	api;
	endpoint;
	accessToken;

	/**
	 * @param {import('@playwright/test').APIRequestContext} api         Request context the calls go through.
	 * @param {string}                                       endpoint    MCP server URL.
	 * @param {string}                                       accessToken OAuth access token, if any.
	 */
	constructor( api, endpoint, accessToken ) {
		this.api = api;
		this.endpoint = endpoint;
		this.accessToken = accessToken;
	}

	headers() {
		const h = {
			'Content-Type': 'application/json',
			Accept: 'application/json, text/event-stream',
		};
		if ( this.accessToken ) {
			h.Authorization = `Bearer ${ this.accessToken }`;
		}
		if ( this.sessionId ) {
			h[ 'Mcp-Session-Id' ] = this.sessionId;
			h[ 'MCP-Protocol-Version' ] = PROTOCOL_VERSION;
		}
		return h;
	}

	/**
	 * Send a raw JSON-RPC request.
	 * @param {string}  method       JSON-RPC method.
	 * @param {unknown} params       JSON-RPC params.
	 * @param {boolean} notification Send as a notification (no id, no response expected).
	 */
	async send( method, params, notification = false ) {
		const body = { jsonrpc: '2.0', method };
		if ( params !== undefined ) {
			body.params = params;
		}
		if ( ! notification ) {
			body.id = this.nextId++;
		}
		return this.api.post( this.endpoint, {
			headers: this.headers(),
			data: body,
		} );
	}

	static async parse( res ) {
		const type = res.headers()[ 'content-type' ] ?? '';
		const text = await res.text();
		if ( type.includes( 'text/event-stream' ) ) {
			const data = text
				.split( '\n' )
				.filter( ( l ) => l.startsWith( 'data:' ) )
				.map( ( l ) => l.slice( 5 ).trim() )
				.pop();
			return data ? JSON.parse( data ) : undefined;
		}
		return text ? JSON.parse( text ) : undefined;
	}

	async rpc( method, params ) {
		const res = await this.send( method, params );
		expect( res.status(), `${ method } HTTP status` ).toBe( 200 );
		const json = await McpClient.parse( res );
		expect(
			json.error,
			`${ method } JSON-RPC error: ${ JSON.stringify( json.error ) }`
		).toBeUndefined();
		return json.result;
	}

	async initialize() {
		const res = await this.send( 'initialize', {
			protocolVersion: PROTOCOL_VERSION,
			capabilities: {},
			clientInfo: { name: 'publishio-e2e', version: '1.0.0' },
		} );
		expect( res.status(), await res.text() ).toBe( 200 );
		this.sessionId = res.headers()[ 'mcp-session-id' ];
		expect(
			this.sessionId,
			'initialize must return an Mcp-Session-Id header'
		).toBeTruthy();

		const ack = await this.send(
			'notifications/initialized',
			undefined,
			true
		);
		expect( [ 200, 202 ] ).toContain( ack.status() );

		return ( await McpClient.parse( res ) ).result;
	}

	async listTools() {
		return ( await this.rpc( 'tools/list' ) ).tools;
	}

	async callTool( abilityName, args = {} ) {
		const result = await this.rpc( 'tools/call', {
			name: tool( abilityName ),
			arguments: args,
		} );
		const text =
			result?.content?.find( ( c ) => c.type === 'text' )?.text ?? '';
		let data = result?.structuredContent;
		if ( data === undefined && text ) {
			try {
				data = JSON.parse( text );
			} catch {
				data = text;
			}
		}
		if ( data && ! Array.isArray( data ) && Array.isArray( data.result ) ) {
			data = data.result;
		}
		return { isError: !! result?.isError, data, text, raw: result };
	}
}
