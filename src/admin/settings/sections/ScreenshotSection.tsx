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
import { screenshotApi } from '../../screenshot/api';
import type { ScreenshotSettings } from '../../screenshot/types';

const NOTICES_CONTEXT = 'rtpwai-settings';

interface Props {
	noticesContext?: string;
}

export function ScreenshotSection( {
	noticesContext = NOTICES_CONTEXT,
}: Props ) {
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
				{ type: 'snackbar', context: noticesContext }
			);
		} catch {
			createErrorNotice(
				__(
					'Failed to save settings. Please try again.',
					'rtcamp-publish-with-ai'
				),
				{
					type: 'snackbar',
					context: noticesContext,
					explicitDismiss: true,
				}
			);
		} finally {
			setIsSaving( false );
		}
	}

	return (
		<div className="flex flex-col gap-6">
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Enable Screenshots', 'rtcamp-publish-with-ai' ) }
				help={ __(
					'When enabled, the AI can capture screenshots of patterns and pages during publishing.',
					'rtcamp-publish-with-ai'
				) }
				checked={ settings.enabled }
				onChange={ ( enabled ) =>
					setSettings( ( s ) => ( s ? { ...s, enabled } : s ) )
				}
			/>

			{ settings.enabled && (
				<>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Provider', 'rtcamp-publish-with-ai' ) }
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
	);
}
