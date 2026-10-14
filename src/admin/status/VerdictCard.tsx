/**
 * WordPress dependencies
 */
import { Card, CardBody, ProgressBar } from '@wordpress/components'; // eslint-disable-line @wordpress/use-recommended-components
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { CHECKS } from '../shared/site-check/checks';
import { StatusIcon } from '../shared/site-check/components/StatusIcon';
import type { RunItems } from '../shared/site-check/types';
import type { Verdict } from '../shared/site-check/verdict';
import { duration } from './utils';

interface VerdictCardProps {
	verdict: Verdict;
	items: RunItems;
	running: boolean;
	startedAt: number | null;
	checkedAt: number | null;
}

export function VerdictCard( {
	verdict,
	items,
	running,
	startedAt,
	checkedAt,
}: VerdictCardProps ) {
	const done = CHECKS.filter( ( check ) => items[ check.id ].result ).length;

	return (
		<Card>
			<CardBody className="flex items-start gap-4">
				<StatusIcon
					state={ verdict.tone }
					size={ 40 }
					label={ verdict.title }
				/>
				<div
					className="flex min-w-0 flex-1 flex-col gap-1.5"
					aria-live="polite"
				>
					<h2 className="m-0 text-xl font-semibold text-gray-900">
						{ verdict.title }
					</h2>
					<p className="m-0 text-[13px] text-gray-700">
						{ verdict.text }
					</p>
					{ running && (
						<ProgressBar
							className="!mt-1 !w-full !max-w-none"
							value={ Math.round(
								( done / CHECKS.length ) * 100
							) }
						/>
					) }
					{ ! running && startedAt && checkedAt && (
						<p className="m-0 text-xs tabular-nums text-gray-500">
							{ sprintf(
								/* translators: %s: how long the checks took */
								__(
									'Checked just now from this browser, in %s',
									'publishio'
								),
								duration( checkedAt - startedAt )
							) }
						</p>
					) }
				</div>
			</CardBody>
		</Card>
	);
}
