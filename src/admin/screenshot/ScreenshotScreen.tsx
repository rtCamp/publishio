/**
 * WordPress dependencies
 */
import { useEffect, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import {
	Button,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { AdminHeader } from '../shared/AdminHeader';
import { ScreenNotices } from '../shared/ScreenNotices';
import { screenshotApi } from './api';
import type { ScreenshotSettings } from './types';

const NOTICES_CONTEXT = 'rtpwai-screenshot';

export function ScreenshotScreen() {
	const [ settings, setSettings ] = useState< ScreenshotSettings | null >(
		null
	);
	const [ apiKey, setApiKey ] = useState( '' );
	const [ isSaving, setIsSaving ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	useEffect( () => {
		screenshotApi.get().then( setSettings );
	}, [] );

	if ( ! settings ) {
		return null;
	}

	const currentProvider = settings.providers.find(
		( p ) => p.id === settings.provider
	);

	async function handleSave() {
		if ( ! settings ) {
			return;
		}

		setIsSaving( true );

		try {
			const updated = await screenshotApi.update( {
				enabled: settings.enabled,
				provider: settings.provider,
				api_key: apiKey,
			} );
			setSettings( updated );
			setApiKey( '' );
			createSuccessNotice(
				__( 'Settings saved.', 'rtcamp-publish-with-ai' ),
				{ type: 'snackbar', context: NOTICES_CONTEXT }
			);
		} catch {
			createErrorNotice(
				__(
					'Failed to save settings. Please try again.',
					'rtcamp-publish-with-ai'
				),
				{
					type: 'snackbar',
					context: NOTICES_CONTEXT,
					explicitDismiss: true,
				}
			);
		} finally {
			setIsSaving( false );
		}
	}

	return (
		<>
			<AdminHeader
				title={ __( 'Screenshots', 'rtcamp-publish-with-ai' ) }
				description={ __(
					'Configure screenshot capture for AI publishing previews.',
					'rtcamp-publish-with-ai'
				) }
			/>

			<main className="p-6">
				<div className="bg-white rounded-lg border border-gray-200 p-6 max-w-2xl flex flex-col gap-6">
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Enable Screenshots',
							'rtcamp-publish-with-ai'
						) }
						help={ __(
							'When enabled, the AI can capture screenshots of patterns and pages during publishing.',
							'rtcamp-publish-with-ai'
						) }
						checked={ settings.enabled }
						onChange={ ( enabled ) =>
							setSettings( ( s ) =>
								s ? { ...s, enabled } : s
							)
						}
					/>

					{ settings.enabled && (
						<>
							<SelectControl
								__nextHasNoMarginBottom
								label={ __(
									'Provider',
									'rtcamp-publish-with-ai'
								) }
								value={ settings.provider }
								options={ settings.providers.map( ( p ) => ( {
									value: p.id,
									label: p.label,
								} ) ) }
								onChange={ ( provider ) =>
									setSettings( ( s ) =>
										s ? { ...s, provider } : s
									)
								}
							/>

							<TextControl
								__nextHasNoMarginBottom
								label={
									currentProvider?.key_label ??
									__( 'API Key', 'rtcamp-publish-with-ai' )
								}
								type="password"
								value={ apiKey }
								placeholder={
									settings.has_api_key
										? __(
												'Leave blank to keep current key',
												'rtcamp-publish-with-ai'
										  )
										: ''
								}
								help={
									currentProvider?.requires_key === false
										? __(
												'Optional — providing a key unlocks higher rate limits.',
												'rtcamp-publish-with-ai'
										  )
										: undefined
								}
								onChange={ setApiKey }
							/>
						</>
					) }

					<div>
						<Button
							variant="primary"
							onClick={ handleSave }
							isBusy={ isSaving }
							disabled={ isSaving }
						>
							{ __( 'Save', 'rtcamp-publish-with-ai' ) }
						</Button>
					</div>
				</div>
			</main>

			<ScreenNotices context={ NOTICES_CONTEXT } />
		</>
	);
}
