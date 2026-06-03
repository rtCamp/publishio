/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { Button, TextControl } from '@wordpress/components';
import { __, _x } from '@wordpress/i18n';
import { copy, check, seen, unseen } from '@wordpress/icons';

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
	const [ copied, setCopied ] = useState( false );

	const displayValue = secret && ! revealed ? '•'.repeat( 24 ) : value;

	async function handleCopy() {
		try {
			await navigator.clipboard.writeText( value );
		} catch {
			const el = document.createElement( 'textarea' );
			el.value = value;
			document.body.appendChild( el );
			el.select();
			document.execCommand( 'copy' );
			document.body.removeChild( el );
		}
		setCopied( true );
		setTimeout( () => setCopied( false ), 2000 ); // eslint-disable-line @wordpress/react-no-unsafe-timeout
	}

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
							? __( 'Hide', 'publish-with-ai' )
							: __( 'Reveal', 'publish-with-ai' )
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
						? __( 'Copied!', 'publish-with-ai' )
						: _x( 'Copy', 'copy to clipboard', 'publish-with-ai' )
				}
				onClick={ handleCopy }
				size="compact"
				className="shrink-0 mb-0.5"
				aria-live="polite"
			/>
		</div>
	);
}
