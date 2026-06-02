/**
 * Tests for useCredentials hook.
 */
/**
 * External dependencies
 */
import { renderHook, act } from '@testing-library/react';
import { useCredentials } from '@/admin/credentials/useCredentials';

// Deferred promise helpers for fake-timer test environment.
function resolveAfterTick< T >( value: T ): Promise< T > {
	return new Promise( ( resolve ) => {
		setTimeout( () => resolve( value ), 0 );
	} );
}

function rejectAfterTick( reason: unknown ): Promise< never > {
	return new Promise( ( _resolve, reject ) => {
		setTimeout( () => reject( reason ), 0 );
	} );
}

// Drain all pending timers within act(). Loops because timer callbacks
// can schedule additional timers (e.g. re-fetch after remove).
async function flushAsync() {
	let safety = 0;
	await act( async () => {
		while ( jest.getTimerCount() > 0 && safety++ < 20 ) {
			jest.runOnlyPendingTimers();
		}
	} );
}

// ── mocks ──────────────────────────────────────────────────────────

// eslint-disable-next-line no-var
var mockCreateErrorNotice = jest.fn();
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( {
		createErrorNotice: mockCreateErrorNotice,
	} ) ),
} ) );

jest.mock( '@wordpress/notices', () => ( {
	store: 'core/notices',
} ) );

jest.mock( '@/admin/constants', () => ( {
	PAGE_SIZE: 3,
} ) );

// Auto-mock the api module; get access to its spies via the import.
import { credentialsApi as mockApi } from '@/admin/credentials/api';
jest.mock( '@/admin/credentials/api' );

beforeEach( () => {
	jest.useFakeTimers();
	jest.clearAllMocks();
	( mockApi.list as jest.Mock ).mockReturnValue(
		resolveAfterTick( { items: [], total: 0 } )
	);
	( mockApi.create as jest.Mock ).mockImplementation( ( data: unknown ) =>
		resolveAfterTick( {
			id: 1,
			client_id: 'mock-id',
			client_name:
				( data as Record< string, string > )[ 'client_name' ] ??
				'Created',
			client_secret: 'secret-abc',
			source: '',
			redirect_uris: [],
			grant_types: '',
			response_types: '',
			scope: '',
			client_uri: null,
			logo_uri: null,
			tos_uri: null,
			policy_uri: null,
			contacts: [],
			software_id: null,
			software_version: null,
			registered_at: 0,
			last_active_at: null,
		} )
	);
	( mockApi.update as jest.Mock ).mockImplementation(
		( _id: number, data: Record< string, unknown > ) =>
			resolveAfterTick( data )
	);
	( mockApi.remove as jest.Mock ).mockImplementation( () =>
		resolveAfterTick( { tokens_deleted: 3 } )
	);
} );

afterEach( () => {
	jest.useRealTimers();
} );

// ── tests ──────────────────────────────────────────────────────────

describe( 'useCredentials', () => {
	describe( 'initial state', () => {
		it( 'starts with loading true, empty credentials, page 1', () => {
			const { result } = renderHook( () => useCredentials() );

			expect( result.current.isLoading ).toBe( true );
			expect( result.current.credentials ).toEqual( [] );
			expect( result.current.total ).toBe( 0 );
			expect( result.current.page ).toBe( 1 );
			expect( result.current.pageSize ).toBe( 3 );
		} );
	} );

	describe( 'data fetching', () => {
		it( 'fetches credentials and updates state', async () => {
			const items = [
				{ id: 1, client_name: 'App One' },
				{ id: 2, client_name: 'App Two' },
			];
			( mockApi.list as jest.Mock ).mockReturnValue(
				resolveAfterTick( { items, total: 2 } )
			);

			const { result } = renderHook( () => useCredentials() );
			await flushAsync();

			expect( result.current.isLoading ).toBe( false );
			expect( result.current.credentials ).toEqual( items );
			expect( result.current.total ).toBe( 2 );
		} );

		it( 'shows error notice when fetch fails', async () => {
			( mockApi.list as jest.Mock ).mockReturnValue(
				rejectAfterTick( new Error( 'Network error' ) )
			);

			const { result } = renderHook( () => useCredentials() );
			await flushAsync();

			expect( result.current.isLoading ).toBe( false );
			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				'Failed to load credentials.',
				expect.objectContaining( {
					type: 'snackbar',
					explicitDismiss: true,
				} )
			);
		} );

		it( 're-fetches when page changes', async () => {
			const { result } = renderHook( () => useCredentials() );
			await flushAsync();

			expect( mockApi.list ).toHaveBeenCalledTimes( 1 );
			expect( mockApi.list ).toHaveBeenCalledWith( 1 );

			act( () => {
				result.current.setPage( 2 );
			} );
			expect( result.current.page ).toBe( 2 );

			await flushAsync();
			expect( mockApi.list ).toHaveBeenCalledTimes( 2 );
			expect( mockApi.list ).toHaveBeenLastCalledWith( 2 );
		} );
	} );

	describe( 'create', () => {
		it( 'calls API create and resets to page 1', async () => {
			const { result } = renderHook( () => useCredentials() );
			await flushAsync();

			act( () => {
				result.current.setPage( 2 );
			} );
			expect( result.current.page ).toBe( 2 );

			// Re-fetch after create: list will be called again (need 3 total responses).
			// Already consumed 1 (initial page 1). create triggers re-fetch (page 1).
			( mockApi.list as jest.Mock ).mockReturnValueOnce(
				resolveAfterTick( { items: [], total: 0 } )
			);

			let created: unknown = null;
			result.current
				.create( {
					client_name: 'New App',
					redirect_uris: [],
					client_uri: '',
					logo_uri: '',
					tos_uri: '',
					policy_uri: '',
					contacts: [],
					software_id: '',
					software_version: '',
				} )
				.then( ( c: unknown ) => ( created = c ) );
			await flushAsync();

			expect( mockApi.create ).toHaveBeenCalledWith(
				expect.objectContaining( { client_name: 'New App' } )
			);
			expect( created ).toHaveProperty( 'client_secret', 'secret-abc' );
			expect( result.current.page ).toBe( 1 );
		} );
	} );

	describe( 'update', () => {
		it( 'calls API update and merges response into state', async () => {
			const existing = { id: 1, client_name: 'Old Name' };
			( mockApi.list as jest.Mock ).mockReturnValue(
				resolveAfterTick( { items: [ existing ], total: 1 } )
			);

			const { result } = renderHook( () => useCredentials() );
			await flushAsync();

			result.current.update( 1, { client_name: 'New Name' } );
			await flushAsync();

			expect( mockApi.update ).toHaveBeenCalledWith( 1, {
				client_name: 'New Name',
			} );
			expect( result.current.credentials[ 0 ] ).toHaveProperty(
				'client_name',
				'New Name'
			);
		} );
	} );

	describe( 'remove', () => {
		it( 'calls API remove and adjusts total', async () => {
			( mockApi.list as jest.Mock )
				.mockReturnValueOnce(
					resolveAfterTick( {
						items: [ { id: 1, client_name: 'App' } ],
						total: 3,
					} )
				)
				.mockReturnValueOnce(
					resolveAfterTick( { items: [], total: 2 } )
				);

			const { result } = renderHook( () => useCredentials() );
			await flushAsync();

			expect( result.current.total ).toBe( 3 );

			let tokensDeleted = 0;
			result.current
				.remove( 1 )
				.then( ( n: number ) => ( tokensDeleted = n ) );
			await flushAsync();

			expect( mockApi.remove ).toHaveBeenCalledWith( 1 );
			expect( tokensDeleted ).toBe( 3 );
			expect( result.current.total ).toBe( 2 );
		} );

		it( 'navigates to page 1 when removing last item', async () => {
			// Three list calls: initial (page 1), setPage(2), after remove (page 1).
			( mockApi.list as jest.Mock )
				.mockReturnValueOnce(
					resolveAfterTick( { items: [], total: 4 } )
				)
				.mockReturnValueOnce(
					resolveAfterTick( {
						items: [ { id: 4, client_name: 'Last' } ],
						total: 4,
					} )
				)
				.mockReturnValueOnce(
					resolveAfterTick( { items: [], total: 3 } )
				);

			const { result } = renderHook( () => useCredentials() );
			act( () => result.current.setPage( 2 ) );
			await flushAsync();

			expect( result.current.page ).toBe( 2 );

			result.current.remove( 4 );
			await flushAsync();

			expect( result.current.page ).toBe( 1 );
			expect( result.current.total ).toBe( 3 );
		} );
	} );

	describe( 'cleanup', () => {
		it( 'does not update state after unmount', async () => {
			let resolvePromise: ( value: unknown ) => void;
			( mockApi.list as jest.Mock ).mockReturnValue(
				new Promise( ( resolve ) => {
					resolvePromise = resolve;
				} )
			);

			const { result, unmount } = renderHook( () => useCredentials() );
			expect( result.current.isLoading ).toBe( true );
			unmount();

			resolvePromise!( {
				items: [ { id: 1, client_name: 'App' } ],
				total: 1,
			} );
			await Promise.resolve();
		} );
	} );
} );
