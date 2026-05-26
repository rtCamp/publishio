/**
 * Internal dependencies
 */
import { relativeDate } from '../../utils';

interface Props {
	item: { last_active_at: number | null };
}

export function LastActiveField( { item }: Props ) {
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
