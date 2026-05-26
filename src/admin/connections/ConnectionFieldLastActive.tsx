/**
 * Internal dependencies
 */
import type { OAuthConnection } from './types';
import { relativeDate } from './utils';

interface Props {
	item: OAuthConnection;
}

export function ConnectionFieldLastActive( { item }: Props ) {
	if ( item.last_active_at === null ) {
		return <span className="text-gray-400">—</span>;
	}

	const date = new Date( item.last_active_at * 1000 );
	return (
		<time dateTime={ date.toISOString() } title={ date.toLocaleString() }>
			{ relativeDate( item.last_active_at ) }
		</time>
	);
}
