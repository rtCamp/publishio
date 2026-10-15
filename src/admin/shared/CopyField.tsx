/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
// eslint-disable-next-line @wordpress/use-recommended-components -- TextControl retained; @wordpress/ui migration deferred.
import { Button, TextControl } from '@wordpress/components';
import { __, _x } from '@wordpress/i18n';
import { copy, check, seen, unseen } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { useCopied } from './useCopied';

interface CopyFieldProps {
	label: string;
	value: string;
	/** When true the value is masked and must be revealed before copying. */
	secret?: boolean;
	readOnly?: boolean;
}

export function CopyField( {
	label,
	value,
	secret = false,
	readOnly = true,
}: CopyFieldProps ) {
	const [ revealed, setRevealed ] = useState( false );
	const { copied, copyText } = useCopied();

	const displayValue = secret && ! revealed ? '•'.repeat( 24 ) : value;

	return (
		<div className="flex items-end gap-2">
			<div className="flex-1 min-w-0">
				<TextControl
					__next40pxDefaultSize
					label={ label }
					value={ displayValue }
					readOnly={ readOnly }
					onChange={ () => {} }
					className="font-mono"
				/>
			</div>
			{ secret && (
				<Button
					icon={ revealed ? unseen : seen }
					label={
						revealed
							? __( 'Hide', 'publishio' )
							: __( 'Reveal', 'publishio' )
					}
					onClick={ () => setRevealed( ( v ) => ! v ) }
					size="compact"
					className="shrink-0 mb-0.5"
				/>
			) }
			<Button
				icon={ copied ? check : copy }
				label={
					copied
						? __( 'Copied!', 'publishio' )
						: _x( 'Copy', 'copy to clipboard', 'publishio' )
				}
				onClick={ () => copyText( value ) }
				size="compact"
				className="shrink-0 mb-0.5"
				aria-live="polite"
			/>
		</div>
	);
}
