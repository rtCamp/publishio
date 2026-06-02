/**
 * Tests for useConnections hook.
 */
/**
 * External dependencies
 */
import { renderHook, act } from '@testing-library/react';
import { useConnections } from '@/admin/connections/useConnections';

// Use deferred promises with fake timers so React act() wraps all state updates.

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

// Import the mocked api module for direct access to its spies.
import { connectionsApi as mockApi } from '@/admin/connections/api';
jest.mock( '@/admin/connections/api' );

beforeEach( () => {
	jest.useFakeTimers();
	jest.clearAllMocks();
	( mockApi.list as jest.Mock ).mockReturnValue(
		resolveAfterTick( { items: [], total: 0 } )
	);
	( mockApi.remove as jest.Mock ).mockImplementation( () =>
		resolveAfterTick( { tokens_deleted: 5 } )
	);
} );

afterEach( () => {
	jest.useRealTimers();
} );

function mockListWith( items: unknown[], total: number ) {
	( mockApi.list as jest.Mock ).mockReturnValue(
		resolveAfterTick( { items, total } )
	);
}

// ── tests ──────────────────────────────────────────────────────────

describe( 'useConnections', () => {
	describe( 'initial state', () => {
		it( 'starts with loading true, empty connections, page 1', () => {
			const { result } = renderHook( () => useConnections() );

			expect( result.current.isLoading ).toBe( true );
			expect( result.current.connections ).toEqual( [] );
			expect( result.current.total ).toBe( 0 );
			expect( result.current.page ).toBe( 1 );
			expect( result.current.pageSize ).toBe( 3 );
		} );
	} );

	describe( 'data fetching', () => {
		it( 'fetches connections and updates state', async () => {
			const items: Record< string, unknown >[] = [
				{ id: 1, client_name: 'App One' },
				{ id: 2, client_name: 'App Two' },
			];
			mockListWith( items, 2 );

			const { result } = renderHook( () => useConnections() );
			await flushAsync();

			expect( result.current.isLoading ).toBe( false );
			expect( result.current.connections ).toEqual( items );
			expect( result.current.total ).toBe( 2 );
		} );

		it( 'shows error notice when fetch fails', async () => {
			( mockApi.list as jest.Mock ).mockReturnValue(
				rejectAfterTick( new Error( 'Network error' ) )
			);

			const { result } = renderHook( () => useConnections() );
			await flushAsync();

			expect( result.current.isLoading ).toBe( false );
			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				'Failed to load connections.',
				expect.objectContaining( {
					type: 'snackbar',
					explicitDismiss: true,
				} )
			);
		} );

		it( 're-fetches when page changes', async () => {
			const { result } = renderHook( () => useConnections() );
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

	describe( 'remove', () => {
		it( 'calls API remove, returns tokens, adjusts total', async () => {
			// Initial fetch returns 3 items; re-fetch after remove returns 2 items.
			( mockApi.list as jest.Mock )
				.mockReturnValueOnce(
					resolveAfterTick( {
						items: [
							{
								client_id: 'dcr_app1',
								client_name: 'App',
								user: { id: 1 },
							},
						],
						total: 3,
					} )
				)
				.mockReturnValueOnce(
					resolveAfterTick( { items: [], total: 2 } )
				);

			const { result } = renderHook( () => useConnections() );
			await flushAsync();

			expect( result.current.total ).toBe( 3 );

			let tokensDeleted = 0;
			result.current
				.remove( 'dcr_app1', 1 )
				.then( ( n ) => ( tokensDeleted = n ) );
			await flushAsync();

			expect( mockApi.remove ).toHaveBeenCalledWith( 'dcr_app1', 1 );
			expect( tokensDeleted ).toBe( 5 );
			expect( result.current.total ).toBe( 2 );
		} );

		it( 'navigates to page 1 when removing last item on last page', async () => {
			// PAGE_SIZE=3, total=4 → page 2 has 1 item.
			// Three list calls: initial (page 1), after setPage(2), after remove (page 1).
			( mockApi.list as jest.Mock )
				.mockReturnValueOnce(
					resolveAfterTick( { items: [], total: 4 } )
				)
				.mockReturnValueOnce(
					resolveAfterTick( {
						items: [
							{
								client_id: 'dcr_last',
								client_name: 'Last',
								user: { id: 4 },
							},
						],
						total: 4,
					} )
				)
				.mockReturnValueOnce(
					resolveAfterTick( { items: [], total: 3 } )
				);

			const { result } = renderHook( () => useConnections() );
			act( () => result.current.setPage( 2 ) );
			await flushAsync();

			expect( result.current.page ).toBe( 2 );

			result.current.remove( 'dcr_last', 4 );
			await flushAsync();

			expect( result.current.page ).toBe( 1 );
			expect( result.current.total ).toBe( 3 );
		} );

		it( 'stays on current page when items remain in range', async () => {
			// Three list calls: initial (page 1), after setPage(2), after remove (page 2).
			( mockApi.list as jest.Mock )
				.mockReturnValueOnce(
					resolveAfterTick( { items: [], total: 6 } )
				)
				.mockReturnValueOnce(
					resolveAfterTick( {
						items: [
							{
								client_id: 'dcr_a',
								client_name: 'A',
								user: { id: 4 },
							},
							{
								client_id: 'dcr_b',
								client_name: 'B',
								user: { id: 5 },
							},
							{
								client_id: 'dcr_c',
								client_name: 'C',
								user: { id: 6 },
							},
						],
						total: 6,
					} )
				)
				.mockReturnValueOnce(
					resolveAfterTick( {
						items: [
							{
								client_id: 'dcr_b',
								client_name: 'B',
								user: { id: 5 },
							},
							{
								client_id: 'dcr_c',
								client_name: 'C',
								user: { id: 6 },
							},
						],
						total: 5,
					} )
				);

			const { result } = renderHook( () => useConnections() );
			act( () => result.current.setPage( 2 ) );
			await flushAsync();

			expect( result.current.page ).toBe( 2 );

			result.current.remove( 'dcr_a', 4 );
			await flushAsync();

			expect( result.current.page ).toBe( 2 );
			expect( result.current.total ).toBe( 5 );
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

			const { result, unmount } = renderHook( () => useConnections() );
			expect( result.current.isLoading ).toBe( true );
			unmount();

			// Resolve after unmount — the cancelled guard prevents state updates.
			// Should not throw.
			resolvePromise!( {
				items: [ { id: 1, client_name: 'App' } ],
				total: 1,
			} );
			await Promise.resolve();
		} );
	} );
} );
