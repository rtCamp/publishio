/**
 * WordPress dependencies
 */
import { Button, Card, CardBody, Tooltip } from '@wordpress/components'; // eslint-disable-line @wordpress/use-recommended-components
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { CopyField } from '../shared/CopyField';
import { refresh } from '../shared/site-check/components/refresh';
import { StatusIcon } from '../shared/site-check/components/StatusIcon';
import type { SiteCheck } from '../shared/site-check/useSiteCheck';
import type { Verdict } from '../shared/site-check/verdict';
import { relativeDate } from '../shared/utils';

const BACKGROUND: Record< Verdict[ 'tone' ], string > = {
	running: 'bg-gray-50',
	success: 'bg-[#edfaef]',
	warning: 'bg-[#fff7e1]',
	error: 'bg-[#fcf0f1]',
	blocked: 'bg-gray-50',
};

function headline( verdict: Verdict ): string {
	switch ( verdict.tone ) {
		case 'running':
			return __( 'Checking whether AI apps can connect…', 'publishio' );
		case 'success':
			return __( 'Ready for AI apps', 'publishio' );
		case 'error':
			return __( "AI apps can't connect yet", 'publishio' );
		default:
			return verdict.title;
	}
}

function checked( checkedAt: number | null ): string {
	if ( null === checkedAt ) {
		return '';
	}
	if ( Date.now() - checkedAt < 60 * 1000 ) {
		return __( 'Checked just now.', 'publishio' );
	}
	return sprintf(
		/* translators: %s: how long ago, such as "2 hours ago" */
		__( 'Checked %s.', 'publishio' ),
		relativeDate( checkedAt / 1000 )
	);
}

export function ConnectCard( { siteCheck }: { siteCheck: SiteCheck } ) {
	const { verdict, running, idle, checkedAt } = siteCheck;

	let title = headline( verdict );
	let detail = verdict.text;
	if ( idle ) {
		title = __( 'Not checked yet', 'publishio' );
		detail = __(
			'Open the Status page to check whether AI apps can connect to this site.',
			'publishio'
		);
	} else if ( 'success' === verdict.tone ) {
		detail = checked( checkedAt );
	}

	return (
		<Card>
			<CardBody className="flex flex-col gap-3.5">
				<div>
					<h2 className="m-0 text-sm font-semibold text-gray-900">
						{ __( 'Connect an AI app', 'publishio' ) }
					</h2>
					<p className="m-0 mt-1 text-[13px] text-gray-500">
						{ __(
							'Add this URL to your AI app as a custom connector. The guides below walk you through it.',
							'publishio'
						) }
					</p>
				</div>
				<CopyField
					label={ __( 'MCP Server URL', 'publishio' ) }
					value={ window.publishioAdmin?.mcpServerUrl ?? '' }
				/>
				<div
					className={ `flex flex-wrap items-center gap-x-3 gap-y-2 rounded px-3 py-2.5 ${
						BACKGROUND[ idle ? 'running' : verdict.tone ]
					}` }
					aria-live="polite"
				>
					<StatusIcon
						state={ idle ? 'queued' : verdict.tone }
						size={ 18 }
					/>
					<div className="min-w-0 flex-[1_1_14rem] text-[13px]">
						<strong className="block text-gray-900">
							{ title }
						</strong>
						<span className="block text-gray-700">{ detail }</span>
					</div>
					<Tooltip
						text={
							running
								? __(
										'Your site is being checked in the background.',
										'publishio'
								  )
								: __(
										'Runs every check again on the Status page. The result shown here is kept in this browser for 3 hours.',
										'publishio'
								  )
						}
					>
						<Button
							variant="link"
							icon={ refresh }
							className="!ms-auto shrink-0"
							href={ window.publishioAdmin?.statusUrl ?? '' }
							disabled={ running }
							accessibleWhenDisabled
						>
							{ __( 'Check again', 'publishio' ) }
						</Button>
					</Tooltip>
				</div>
			</CardBody>
		</Card>
	);
}
