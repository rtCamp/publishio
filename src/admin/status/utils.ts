/**
 * A duration in milliseconds or seconds, whichever reads better.
 *
 * @param ms Duration in milliseconds.
 */
export function duration( ms: number ): string {
	return ms < 1000
		? `${ Math.max( 1, Math.round( ms ) ) } ms`
		: `${ ( ms / 1000 ).toFixed( 1 ) } s`;
}
