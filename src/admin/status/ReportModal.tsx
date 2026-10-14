/**
 * WordPress dependencies
 */
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check, copy } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { useCopied } from '../shared/useCopied';

interface ReportModalProps {
	text: string;
	onClose: () => void;
}

export function ReportModal( { text, onClose }: ReportModalProps ) {
	const { copied, copyText } = useCopied();

	return (
		<Modal
			title={ __( 'Status report', 'publishio' ) }
			onRequestClose={ onClose }
			size="large"
			className="publishio-tailwind publishio-report-modal"
		>
			<div className="flex min-h-0 flex-1 flex-col gap-3">
				<p className="m-0 text-[13px] text-gray-700">
					{ __(
						"This is exactly what will be copied. It has your site's addresses and software versions, and what each check got back. It has no passwords, user names or emails.",
						'publishio'
					) }
				</p>
				<pre
					tabIndex={ 0 }
					className="m-0 min-h-24 flex-1 overflow-auto whitespace-pre-wrap break-words md:whitespace-pre md:break-normal rounded border border-gray-200 bg-gray-50 p-4 font-mono text-xs leading-relaxed"
				>
					{ text }
				</pre>
				<p className="m-0 text-xs text-gray-500">
					{ __(
						'Paste it into Claude or ChatGPT for help fixing a problem, or share it with your developer, your host or rtCamp.',
						'publishio'
					) }
				</p>
				<div className="flex justify-end gap-2">
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Close', 'publishio' ) }
					</Button>
					<Button
						variant="primary"
						icon={ copied ? check : copy }
						onClick={ () => copyText( text ) }
					>
						{ copied
							? __( 'Copied!', 'publishio' )
							: __( 'Copy report', 'publishio' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
