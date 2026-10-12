/**
 * Internal dependencies
 */
import { relativeDate } from '../../utils';

interface Props {
	item: { connected_at: number };
}

export function ConnectedField( { item }: Props ) {
	const date = new Date( item.connected_at * 1000 );
	return (
		<time dateTime={ date.toISOString() } title={ date.toLocaleString() }>
			{ relativeDate( item.connected_at ) }
		</time>
	);
}
