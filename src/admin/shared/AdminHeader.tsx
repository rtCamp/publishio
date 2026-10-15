/**
 * External dependencies
 */
import type { ReactNode } from 'react';
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

interface AdminHeaderProps {
	title: string;
	description?: string;
	actions?: ReactNode;
}

export function AdminHeader( {
	title,
	description,
	actions,
}: AdminHeaderProps ) {
	const logoUrl = window.publishioAdmin?.logoUrl;

	return (
		<header className="flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-4 md:flex-nowrap md:px-6 border-b border-gray-200 bg-white">
			{ logoUrl && (
				<img
					src={ logoUrl }
					alt=""
					className="w-8 h-8 shrink-0"
					aria-hidden="true"
				/>
			) }

			<span className="text-base font-semibold text-gray-900 shrink-0">
				{ __( 'Publishio', 'publishio' ) }
			</span>

			<div className="publishio-header-content min-w-0 basis-full md:basis-auto md:flex-1 md:ps-4 md:border-s border-gray-200">
				<h1 className="m-0 text-sm font-medium text-gray-900">
					{ title }
				</h1>
				{ description && (
					<p className="m-0 mt-0.5 text-xs text-gray-500">
						{ description }
					</p>
				) }
			</div>

			{ actions && (
				<div className="publishio-header-actions flex flex-wrap items-center gap-2 md:shrink-0">
					{ actions }
				</div>
			) }
		</header>
	);
}
