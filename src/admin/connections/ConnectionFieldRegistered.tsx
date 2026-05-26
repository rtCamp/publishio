/**
 * Internal dependencies
 */
import type { OAuthConnection } from './types';
import { relativeDate } from './utils';

interface Props {
	item: OAuthConnection;
}

export function ConnectionFieldRegistered( { item }: Props ) {
	const date = new Date( item.registered_at * 1000 );
	return (
		<time dateTime={ date.toISOString() } title={ date.toLocaleString() }>
			{ relativeDate( item.registered_at ) }
		</time>
	);
}
