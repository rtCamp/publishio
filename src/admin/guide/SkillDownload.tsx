/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { download } from '@wordpress/icons';

/** Save the content guide as SKILL.md, from the copy the page was given. */
function save( text: string ) {
	const url = URL.createObjectURL(
		new Blob( [ text ], { type: 'text/markdown' } )
	);
	const link = document.createElement( 'a' );

	link.href = url;
	link.download = 'SKILL.md';
	link.click();
	URL.revokeObjectURL( url );
}

export function SkillDownload() {
	const text = window.publishioAdmin?.skillFile ?? '';

	if ( ! text ) {
		return null;
	}

	return (
		<div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
			<Button
				variant="secondary"
				icon={ download }
				onClick={ () => save( text ) }
				__next40pxDefaultSize
			>
				{ __( 'Download SKILL.md', 'publishio' ) }
			</Button>
			<p className="m-0 min-w-0 flex-[1_1_16rem] text-[13px] text-gray-500">
				{ __(
					'Upload it as a skill in AI apps that support skills, such as Claude, so they follow the Publishio content guide.',
					'publishio'
				) }
			</p>
		</div>
	);
}
