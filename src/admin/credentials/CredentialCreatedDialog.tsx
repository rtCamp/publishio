/**
 * WordPress dependencies
 */
import { Modal, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { CreatedOAuthCredential } from './types';
import { CopyField } from '../shared/CopyField';
import { Notice } from '../shared/Notice';

interface CredentialCreatedDialogProps {
	credential: CreatedOAuthCredential;
	onClose: () => void;
}

export function CredentialCreatedDialog( {
	credential,
	onClose,
}: CredentialCreatedDialogProps ) {
	return (
		<Modal
			title={ __( 'Credential Created', 'rtcamp-publish-with-ai' ) }
			onRequestClose={ onClose }
			size="medium"
			isDismissible={ false }
		>
			<Notice status="warning" className="mb-4">
				{ __(
					'Copy your client secret now. It will not be shown again.',
					'rtcamp-publish-with-ai'
				) }
			</Notice>

			<div className="flex flex-col gap-4 mt-4">
				<CopyField
					label={ __( 'Client ID', 'rtcamp-publish-with-ai' ) }
					value={ credential.client_id }
				/>

				<CopyField
					label={ __( 'Client Secret', 'rtcamp-publish-with-ai' ) }
					value={ credential.client_secret }
					secret
				/>
			</div>

			<p className="text-xs text-gray-500 mt-4">
				{ __(
					'Store these credentials securely. The client secret cannot be retrieved after closing this dialog.',
					'rtcamp-publish-with-ai'
				) }
			</p>

			<div className="flex justify-end mt-6 pt-4 border-t border-gray-200">
				<Button variant="primary" onClick={ onClose }>
					{ __( 'Done', 'rtcamp-publish-with-ai' ) }
				</Button>
			</div>
		</Modal>
	);
}
