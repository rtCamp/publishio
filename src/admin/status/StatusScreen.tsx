/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { Button, Card, CardBody, Icon } from '@wordpress/components'; // eslint-disable-line @wordpress/use-recommended-components
import { __ } from '@wordpress/i18n';
import { help } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { AdminHeader } from '../shared/AdminHeader';
import { CHECKS } from '../shared/site-check/checks';
import { refresh } from '../shared/site-check/components/refresh';
import { StatusIcon } from '../shared/site-check/components/StatusIcon';
import { buildReport } from '../shared/site-check/report';
import { useSiteCheck } from '../shared/site-check/useSiteCheck';
import { CaveatPanel } from './CaveatPanel';
import { CheckList } from './CheckList';
import { ReportModal } from './ReportModal';
import { VerdictCard } from './VerdictCard';

function RunChecksCard( { onRun }: { onRun: () => void } ) {
	return (
		<Card>
			<CardBody className="flex flex-wrap items-center gap-4">
				<StatusIcon state="queued" size={ 32 } />
				<div className="min-w-0 flex-[1_1_260px]">
					<h2 className="m-0 mb-1 text-[15px] font-semibold text-gray-900">
						{ __(
							"The checks didn't start by themselves",
							'publishio'
						) }
					</h2>
					<p className="m-0 text-[13px] text-gray-500">
						{ __(
							"This page was opened from another site. Run the checks when you're ready.",
							'publishio'
						) }
					</p>
				</div>
				<Button
					variant="primary"
					__next40pxDefaultSize
					onClick={ onRun }
				>
					{ __( 'Run checks', 'publishio' ) }
				</Button>
			</CardBody>
		</Card>
	);
}

function HelpCard( { onShowReport }: { onShowReport: () => void } ) {
	return (
		<div className="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-lg bg-[#eef1fd] px-4 py-3.5 text-[13px] text-gray-700">
			<Icon
				icon={ help }
				className="shrink-0 fill-[var(--wp-admin-theme-color)]"
			/>
			<p className="m-0 min-w-0 flex-[1_1_16rem]">
				{ __(
					'Not sure how to fix this? Copy the report and paste it into Claude or ChatGPT, and ask it to walk you through the fix. You can also send it to your developer, your host or rtCamp.',
					'publishio'
				) }
			</p>
			<Button
				variant="secondary"
				className="ms-auto shrink-0"
				onClick={ onShowReport }
			>
				{ __( 'Show report', 'publishio' ) }
			</Button>
		</div>
	);
}

export function StatusScreen() {
	const siteCheck = useSiteCheck( {
		autoRun: window.publishioAdmin?.autoRunChecks ?? true,
	} );
	const [ showReport, setShowReport ] = useState( false );
	const { items, running, idle, checkedAt } = siteCheck;
	const done = ! running && null !== checkedAt;
	const hasProblems = CHECKS.some( ( check ) =>
		[ 'fail', 'warning' ].includes( items[ check.id ].state )
	);

	return (
		<>
			<AdminHeader
				title={ __( 'Status', 'publishio' ) }
				description={ __(
					'Check whether AI apps can connect to this site.',
					'publishio'
				) }
				actions={
					! idle && (
						<>
							<Button
								variant="secondary"
								disabled={ ! done }
								accessibleWhenDisabled
								onClick={ () => setShowReport( true ) }
							>
								{ __( 'Show report', 'publishio' ) }
							</Button>
							<Button
								variant="primary"
								icon={ refresh }
								disabled={ running }
								accessibleWhenDisabled
								onClick={ siteCheck.start }
							>
								{ __( 'Check again', 'publishio' ) }
							</Button>
						</>
					)
				}
			/>
			<div className="mx-auto box-border flex w-full max-w-4xl flex-col gap-4 p-4 md:p-6">
				{ idle ? (
					<RunChecksCard onRun={ siteCheck.start } />
				) : (
					<>
						<VerdictCard
							verdict={ siteCheck.verdict }
							items={ items }
							running={ running }
							startedAt={ siteCheck.startedAt }
							checkedAt={ checkedAt }
						/>
						{ done && hasProblems && (
							<HelpCard
								onShowReport={ () => setShowReport( true ) }
							/>
						) }
						<CheckList items={ items } running={ running } />
					</>
				) }
				<CaveatPanel
					mcpUrl={ window.publishioAdmin?.mcpServerUrl ?? '' }
					issuerUrl={ window.publishioAdmin?.issuerUrl ?? '' }
				/>
			</div>
			{ showReport && null !== checkedAt && (
				<ReportModal
					text={ buildReport( {
						checks: CHECKS,
						items,
						facts: siteCheck.facts,
						checkedAt,
					} ) }
					onClose={ () => setShowReport( false ) }
				/>
			) }
		</>
	);
}
