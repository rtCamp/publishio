/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { APP_IDS } from './problems';
import { isRecord } from './probe';
import type { AppCheck, AppId, AppsResult, SiteFacts } from './types';

const str = ( value: unknown ) => ( typeof value === 'string' ? value : '' );

function toFacts( data: unknown ): SiteFacts {
	if ( ! isRecord( data ) ) {
		throw new Error( 'Unexpected site facts.' );
	}

	const adapter = data[ 'mcp_adapter' ];
	return {
		publishioVersion: str( data[ 'publishio_version' ] ),
		wpVersion: str( data[ 'wp_version' ] ),
		phpVersion: str( data[ 'php_version' ] ),
		multisite: true === data[ 'multisite' ],
		homeUrl: str( data[ 'home_url' ] ),
		siteUrl: str( data[ 'site_url' ] ),
		mcpUrl: str( data[ 'mcp_url' ] ),
		permalinks: 'plain' === data[ 'permalinks' ] ? 'plain' : 'pretty',
		mcpAdapter: isRecord( adapter )
			? {
					version: str( adapter[ 'version' ] ),
					bundled: true === adapter[ 'bundled' ],
					source: str( adapter[ 'source' ] ),
			  }
			: null,
		tools: typeof data[ 'tools' ] === 'number' ? data[ 'tools' ] : 0,
	};
}

function toApp( data: unknown ): AppCheck[] {
	if ( ! isRecord( data ) || ! APP_IDS.includes( data[ 'id' ] as AppId ) ) {
		return [];
	}

	const optional = ( key: string ) =>
		typeof data[ key ] === 'string' ? { [ key ]: data[ key ] } : {};
	return [
		{
			id: data[ 'id' ] as AppId,
			url: str( data[ 'url' ] ),
			ok: true === data[ 'ok' ],
			code: str( data[ 'code' ] ),
			...( typeof data[ 'ms' ] === 'number' ? { ms: data[ 'ms' ] } : {} ),
			...( typeof data[ 'status' ] === 'number'
				? { status: data[ 'status' ] }
				: {} ),
			...optional( 'server' ),
			...optional( 'error' ),
			...optional( 'detail' ),
		},
	];
}

function toApps( data: unknown ): AppsResult {
	if ( ! isRecord( data ) || ! Array.isArray( data[ 'apps' ] ) ) {
		throw new Error( 'Unexpected app check result.' );
	}

	const transport = data[ 'transport' ];
	return {
		apps: data[ 'apps' ].flatMap( toApp ),
		...( isRecord( transport )
			? {
					transport: {
						curl: str( transport[ 'curl' ] ) || null,
						ssl: str( transport[ 'ssl' ] ) || null,
						blockExternal: true === transport[ 'block_external' ],
						proxy: true === transport[ 'proxy' ],
					},
			  }
			: {} ),
	};
}

export const siteCheckApi = {
	facts: async (): Promise< SiteFacts > =>
		toFacts( await apiFetch( { path: '/publishio/v1/status' } ) ),
	apps: async (): Promise< AppsResult > =>
		toApps( await apiFetch( { path: '/publishio/v1/status/apps' } ) ),
};
