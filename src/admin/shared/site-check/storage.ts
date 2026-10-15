/**
 * Internal dependencies
 */
import { APP_IDS, knownCodes } from './problems';
import { isRecord } from './probe';
import type {
	AppFailure,
	AppId,
	CheckDefinition,
	CheckStatus,
	RunItems,
} from './types';

const KEY = 'publishio-site-check';
export const MAX_AGE = 3 * 60 * 60 * 1000;

const STATUSES: CheckStatus[] = [ 'pass', 'warning', 'fail', 'skip' ];

const isAppFailure = ( app: unknown ): app is AppFailure =>
	isRecord( app ) &&
	APP_IDS.includes( app[ 'id' ] as AppId ) &&
	typeof app[ 'code' ] === 'string' &&
	knownCodes( 'apps' ).includes( app[ 'code' ] );

export interface SavedCheck {
	checkedAt: number;
	items: RunItems;
}

/**
 * Keep a finished run in this browser for the Guide page. Only ids, statuses and codes are kept: every word shown comes from the plugin, so an edited entry can't put text on the page.
 *
 * @param version   Plugin version.
 * @param mcpUrl    MCP server URL the run checked.
 * @param items     The finished run.
 * @param checkedAt When it finished.
 */
export function saveCheck(
	version: string,
	mcpUrl: string,
	items: RunItems,
	checkedAt: number
): void {
	const results = Object.fromEntries(
		Object.entries( items ).map( ( [ id, item ] ) => [
			id,
			{
				status: item.result?.status,
				code: item.result?.code,
				...( item.result?.apps ? { apps: item.result.apps } : {} ),
			},
		] )
	);
	try {
		window.localStorage.setItem(
			KEY,
			JSON.stringify( { version, mcpUrl, checkedAt, results } )
		);
	} catch {}
}

/**
 * The run kept in this browser, or null when there's none, it's older than three hours, it's for another version or address, or it doesn't look right.
 *
 * @param checks  Checks the run must cover.
 * @param version Plugin version.
 * @param mcpUrl  Current MCP server URL.
 * @param now     Current time.
 */
export function loadCheck(
	checks: CheckDefinition[],
	version: string,
	mcpUrl: string,
	now = Date.now()
): SavedCheck | null {
	let saved: unknown;
	try {
		saved = JSON.parse( window.localStorage.getItem( KEY ) ?? 'null' );
	} catch {
		return null;
	}

	if (
		! isRecord( saved ) ||
		saved[ 'version' ] !== version ||
		saved[ 'mcpUrl' ] !== mcpUrl ||
		! isRecord( saved[ 'results' ] )
	) {
		return null;
	}

	const checkedAt = saved[ 'checkedAt' ];
	if (
		typeof checkedAt !== 'number' ||
		now - checkedAt > MAX_AGE ||
		checkedAt > now
	) {
		return null;
	}

	const results = saved[ 'results' ];
	const items = {} as RunItems;
	for ( const check of checks ) {
		const result = results[ check.id ];
		if ( ! isRecord( result ) ) {
			return null;
		}

		const { status, code, apps } = result;
		const validApps =
			undefined === apps ||
			( Array.isArray( apps ) && apps.every( isAppFailure ) );
		if (
			! STATUSES.includes( status as CheckStatus ) ||
			typeof code !== 'string' ||
			! knownCodes( check.id ).includes( code ) ||
			! validApps
		) {
			return null;
		}

		items[ check.id ] = {
			state: status as CheckStatus,
			result: {
				status: status as CheckStatus,
				code,
				summary: '',
				...( apps
					? {
							apps: ( apps as AppFailure[] ).map( ( app ) => ( {
								id: app.id,
								code: app.code,
							} ) ),
					  }
					: {} ),
			},
		};
	}

	return { checkedAt, items };
}
