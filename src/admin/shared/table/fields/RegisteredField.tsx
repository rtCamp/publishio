/**
 * Internal dependencies
 */
import { relativeDate } from '../../utils';

interface Props {
	item: { registered_at: number };
}

export function RegisteredField( { item }: Props ) {
	const date = new Date( item.registered_at * 1000 );
	return (
		<time dateTime={ date.toISOString() } title={ date.toLocaleString() }>
			{ relativeDate( item.registered_at ) }
		</time>
	);
}
