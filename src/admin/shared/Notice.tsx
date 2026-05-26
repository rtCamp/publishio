/**
 * External dependencies
 */
import type { ReactNode } from 'react';
import clsx from 'clsx';

type NoticeStatus = 'error' | 'warning' | 'info' | 'success';

interface NoticeProps {
	status?: NoticeStatus;
	children: ReactNode;
	className?: string;
}

const STYLES: Record< NoticeStatus, string > = {
	error: 'border-red-400 bg-red-50 text-red-800',
	warning: 'border-amber-400 bg-amber-50 text-amber-800',
	info: 'border-blue-400 bg-blue-50 text-blue-800',
	success: 'border-green-400 bg-green-50 text-green-800',
};

const ICONS: Record< NoticeStatus, string > = {
	error: '✕',
	warning: '!',
	info: 'i',
	success: '✓',
};

const ICON_STYLES: Record< NoticeStatus, string > = {
	error: 'bg-red-400 text-white',
	warning: 'bg-amber-400 text-white',
	info: 'bg-blue-400 text-white',
	success: 'bg-green-400 text-white',
};

export function Notice( {
	status = 'info',
	children,
	className = '',
}: NoticeProps ) {
	return (
		<div
			className={ clsx(
				`flex items-start gap-2 rounded-md border px-3 py-2 text-sm`,
				STYLES[ status ],
				className
			) }
			role="alert"
		>
			<span
				className={ clsx(
					`mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-[10px] font-bold leading-none`,
					ICON_STYLES[ status ]
				) }
				aria-hidden="true"
			>
				{ ICONS[ status ] }
			</span>
			<span>{ children }</span>
		</div>
	);
}
