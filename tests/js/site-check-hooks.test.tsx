/**
 * Tests for running the site check from the Status and Guide pages.
 */
/**
 * External dependencies
 */
import {
	act,
	fireEvent,
	render,
	renderHook,
	screen,
} from '@testing-library/react';

/**
 * Internal dependencies
 */
import { siteCheckApi } from '@/admin/shared/site-check/api';
import { useSiteCheck } from '@/admin/shared/site-check/useSiteCheck';
import { GuideScreen } from '@/admin/guide/GuideScreen';

jest.mock( '@wordpress/i18n', () => jest.requireActual( '@wordpress/i18n' ) );

// The real package can't load under Jest (its uuid dependency is ESM only), so every component renders its children, and as a button when it's clickable.
jest.mock( '@wordpress/components', () => {
	const { createElement } = jest.requireActual( 'react' );
	const Stub = ( {
		children,
		onClick,
	}: {
		children?: unknown;
		onClick?: () => void;
	} ) => createElement( onClick ? 'button' : 'div', { onClick }, children );
	return new Proxy(
		{ __esModule: true },
		{
			get: ( target: Record< string, unknown >, name: string ) =>
				name in target ? target[ name ] : Stub,
		}
	);
} );

jest.mock( '@/admin/shared/site-check/api', () => ( {
	siteCheckApi: {
		facts: jest.fn( () => new Promise( () => {} ) ),
		apps: jest.fn( () => new Promise( () => {} ) ),
	},
} ) );

const SITE = 'https://example.com';

beforeEach( () => {
	window.localStorage.clear();
	global.fetch = jest.fn(
		() => new Promise( () => {} )
	) as unknown as typeof fetch;
	window.publishioAdmin = {
		pluginVersion: '1.3.0',
		mcpServerUrl: `${ SITE }/wp-json/mcp/publishio`,
		issuerUrl: SITE,
		tokenUrl: `${ SITE }/wp-json/publishio-oauth/v1/token`,
		statusUrl: `${ SITE }/wp-admin/admin.php?page=publishio-status`,
		autoRunChecks: true,
		guideImages: { claude: {}, openai: {} },
	} as unknown as NonNullable< Window[ 'publishioAdmin' ] >;
} );

describe( 'useSiteCheck', () => {
	it( 'is running from the first render when it runs on mount', async () => {
		const idle: boolean[] = [];
		await act( async () => {
			renderHook( () => {
				const siteCheck = useSiteCheck( { autoRun: true } );
				idle.push( siteCheck.idle );
				return siteCheck;
			} );
		} );

		expect( idle[ 0 ] ).toBe( false );
	} );

	it( 'is idle when it waits for the Run checks button', () => {
		const { result } = renderHook( () =>
			useSiteCheck( { autoRun: false } )
		);

		expect( result.current.idle ).toBe( true );
	} );
} );

describe( 'GuideScreen', () => {
	it( 'keeps the background run when a guide is opened and closed', async () => {
		await act( async () => {
			render( <GuideScreen /> );
		} );
		await act( async () => {
			fireEvent.click( screen.getAllByText( 'Open Guide →' )[ 0 ]! );
		} );
		await act( async () => {
			fireEvent.click( screen.getByText( '← Back' ) );
		} );

		expect( siteCheckApi.facts ).toHaveBeenCalledTimes( 1 );
		expect(
			screen.getByText( 'Checking whether AI apps can connect…' )
		).toBeInTheDocument();
	} );
} );
