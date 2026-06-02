/**
 * Internal dependencies
 */
import { relativeDate } from '../../utils';

interface Props {
	item: { last_active_at: number };
}

export function LastActiveField( { item }: Props ) {
	const date = new Date( item.last_active_at * 1000 );

	return (
		<time dateTime={ date.toISOString() } title={ date.toLocaleString() }>
			{ relativeDate( item.last_active_at ) }
		</time>
	);
}
