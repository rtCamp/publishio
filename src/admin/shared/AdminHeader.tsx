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
	const logoUrl = window.rtPublishWithAIAdmin?.logoUrl;

	return (
		<header className="flex items-center gap-4 px-6 py-4 border-b border-gray-200 bg-white">
			{ logoUrl && (
				<img
					src={ logoUrl }
					alt=""
					className="w-8 h-8 shrink-0"
					aria-hidden="true"
				/>
			) }

			<span className="text-base font-semibold text-gray-900 shrink-0">
				{ __( 'Publish With AI', 'rtcamp-publish-with-ai' ) }
			</span>

			<div className="rtpwai-header-content flex-1 min-w-0 ps-4 border-s border-gray-200">
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
				<div className="rtpwai-header-actions flex items-center gap-2 shrink-0">
					{ actions }
				</div>
			) }
		</header>
	);
}
