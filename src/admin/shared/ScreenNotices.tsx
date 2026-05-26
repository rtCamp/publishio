/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { SnackbarList } from '@wordpress/components';

interface ScreenNoticesProps {
	context: string;
}

export function ScreenNotices( { context }: ScreenNoticesProps ) {
	const { removeNotice } = useDispatch( noticesStore );

	const notices = useSelect(
		( select ) => select( noticesStore ).getNotices( context ),
		[ context ]
	);

	return (
		<SnackbarList
			notices={ notices }
			className="fixed bottom-4 end-4 z-50 block w-fit"
			onRemove={ ( id ) => removeNotice( id, context ) }
		/>
	);
}
