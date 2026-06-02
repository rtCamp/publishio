/**
 * Tests for shared utility functions.
 */
/**
 * External dependencies
 */
import { detectApps, relativeDate } from '@/admin/shared/utils';

describe( 'detectApps', () => {
	it( 'detects claude from claude.ai redirect URI', () => {
		expect( detectApps( [ 'https://claude.ai/oauth/callback' ] ) ).toEqual(
			[ 'claude' ]
		);
	} );

	it( 'detects claude from anthropic.com redirect URI', () => {
		expect(
			detectApps( [ 'https://api.anthropic.com/oauth/callback' ] )
		).toEqual( [ 'claude' ] );
	} );

	it( 'detects claude from subdomain of known domain', () => {
		expect( detectApps( [ 'https://app.claude.ai/callback' ] ) ).toEqual( [
			'claude',
		] );
	} );

	it( 'detects openai from openai.com redirect URI', () => {
		expect( detectApps( [ 'https://api.openai.com/callback' ] ) ).toEqual( [
			'openai',
		] );
	} );

	it( 'detects openai from chatgpt.com redirect URI', () => {
		expect( detectApps( [ 'https://chat.openai.com/callback' ] ) ).toEqual(
			[ 'openai' ]
		);
	} );

	it( 'returns other for unknown domains', () => {
		expect( detectApps( [ 'https://example.com/callback' ] ) ).toEqual( [
			'other',
		] );
	} );

	it( 'returns other for invalid URIs', () => {
		expect( detectApps( [ 'not-a-valid-url' ] ) ).toEqual( [ 'other' ] );
	} );

	it( 'deduplicates when same app found in multiple URIs', () => {
		expect(
			detectApps( [
				'https://claude.ai/a',
				'https://api.anthropic.com/b',
			] )
		).toEqual( [ 'claude' ] );
	} );

	it( 'returns all detected apps sorted by discovery order', () => {
		expect(
			detectApps( [
				'https://claude.ai/a',
				'https://api.openai.com/b',
				'https://example.com/c',
			] )
		).toEqual( [ 'claude', 'openai', 'other' ] );
	} );

	it( 'returns other for empty array', () => {
		expect( detectApps( [] ) ).toEqual( [ 'other' ] );
	} );

	it( 'uses case-insensitive hostname matching', () => {
		expect( detectApps( [ 'https://CLAUDE.AI/callback' ] ) ).toEqual( [
			'claude',
		] );
	} );
} );

describe( 'relativeDate', () => {
	// Timestamps are seconds since epoch. relativeDate expects seconds input.
	const now = Math.floor( Date.now() / 1000 );

	it( 'returns a string', () => {
		// 1 minute ago
		const result = relativeDate( now - 60 );
		expect( typeof result ).toBe( 'string' );
		expect( result.length ).toBeGreaterThan( 0 );
	} );

	it( 'uses minute unit for differences under 1 hour', () => {
		const twoMinutesAgo = now - 120;
		const result = relativeDate( twoMinutesAgo );
		expect( result ).toMatch( /min/i );
	} );

	it( 'uses hour unit for differences between 1h and 24h', () => {
		const twoHoursAgo = now - 7200;
		const result = relativeDate( twoHoursAgo );
		expect( result ).toMatch( /hour/i );
	} );

	it( 'uses day unit for differences >= 24 hours', () => {
		const twoDaysAgo = now - 172800;
		const result = relativeDate( twoDaysAgo );
		expect( result ).toMatch( /day/i );
	} );

	it( 'handles future timestamps', () => {
		const inOneHour = now + 3600;
		expect( () => relativeDate( inOneHour ) ).not.toThrow();
	} );
} );
