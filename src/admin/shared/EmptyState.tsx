/**
 * External dependencies
 */
import type { ReactNode } from 'react';

interface EmptyStateProps {
	message: string;
	action?: ReactNode;
}

export function EmptyState( { message, action }: EmptyStateProps ) {
	return (
		<div className="flex flex-col items-center justify-center gap-4 py-20 text-center">
			<p className="text-sm text-gray-500 m-0">{ message }</p>
			{ action }
		</div>
	);
}
