/**
 * WordPress dependencies
 */
import { Card, CardBody, CardHeader, PanelBody } from '@wordpress/components'; // eslint-disable-line @wordpress/use-recommended-components
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { CHECKS } from '../shared/site-check/checks';
import { problemFor } from '../shared/site-check/problems';
import { StatusIcon } from '../shared/site-check/components/StatusIcon';
import type {
	CheckDefinition,
	CheckState,
	RunItem,
	RunItems,
} from '../shared/site-check/types';
import { TechnicalDetails } from './TechnicalDetails';
import { duration } from './utils';

const LABEL_COLOR: Record< CheckState, string > = {
	pass: 'text-[#007017]',
	fail: 'text-[#b32d2e]',
	warning: 'text-[#926300]',
	running: 'text-[var(--wp-admin-theme-color)]',
	skip: 'text-gray-500',
	waiting: 'text-gray-500',
	queued: 'text-gray-500',
};

const isProblem = ( item: RunItem ) =>
	'fail' === item.state || 'warning' === item.state;

function label( check: CheckDefinition, item: RunItem ): string {
	if ( item.result ) {
		return item.result.summary;
	}
	if ( 'running' === item.state ) {
		return `${ check.runningLabel }…`;
	}
	if ( 'waiting' === item.state ) {
		const needs = check.needs
			.map(
				( id ) =>
					CHECKS.find( ( other ) => other.id === id )?.name ?? id
			)
			.join( ', ' );
		/* translators: %s: names of the checks this one needs */
		return sprintf( __( 'Waiting for %s', 'publishio' ), needs );
	}
	return __( 'Not run yet', 'publishio' );
}

function CheckDetail( {
	check,
	item,
}: {
	check: CheckDefinition;
	item: RunItem;
} ) {
	if ( ! item.result ) {
		return null;
	}
	const problem = problemFor(
		check.id,
		item.result.code,
		item.result.apps ?? []
	);

	return (
		<div className="mt-1.5 -ms-8 flex flex-col gap-2.5 md:ms-0">
			<p className="m-0 text-sm font-medium text-gray-900">
				{ problem.message }
			</p>
			<div className="rounded bg-gray-50 px-3 py-2.5">
				<span className="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500">
					{ __( 'How to fix', 'publishio' ) }
				</span>
				<p className="m-0 text-[13px] text-gray-700">{ problem.fix }</p>
			</div>
			<div className="publishio-card-panel overflow-hidden rounded border border-gray-200">
				<PanelBody
					title={ __( 'Technical details', 'publishio' ) }
					initialOpen={ false }
				>
					<TechnicalDetails
						result={ item.result }
						problem={ problem }
					/>
				</PanelBody>
			</div>
		</div>
	);
}

function CheckRow( {
	check,
	item,
	expanded = false,
}: {
	check: CheckDefinition;
	item: RunItem;
	expanded?: boolean;
} ) {
	const time =
		item.result?.exchanges?.length && item.startedAt && item.finishedAt
			? duration( item.finishedAt - item.startedAt )
			: '';

	return (
		<li className="m-0 flex items-start gap-3 border-t border-gray-100 px-4 py-3.5 first:border-t-0">
			<StatusIcon state={ item.state } />
			<div className="min-w-0 flex-1">
				<div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
					<h3 className="m-0 text-[13px] font-semibold text-gray-900">
						{ check.name }
					</h3>
					<span
						className={ `text-[13px] ${
							LABEL_COLOR[ item.state ]
						}` }
					>
						{ label( check, item ) }
					</span>
					{ time && (
						<span className="ms-auto text-xs tabular-nums text-gray-500">
							{ time }
						</span>
					) }
				</div>
				{ expanded ? (
					<CheckDetail check={ check } item={ item } />
				) : (
					<p className="m-0 mt-1 text-xs text-gray-500">
						{ check.description }
					</p>
				) }
			</div>
		</li>
	);
}

interface CheckListProps {
	items: RunItems;
	running: boolean;
}

export function CheckList( { items, running }: CheckListProps ) {
	if ( running ) {
		return (
			<Card>
				<CardHeader>
					<h2 className="m-0 text-sm font-semibold text-gray-900">
						{ __( 'Checks', 'publishio' ) }
					</h2>
				</CardHeader>
				<CardBody className="!p-0">
					<ul className="m-0 list-none p-0">
						{ CHECKS.map( ( check ) => (
							<CheckRow
								key={ check.id }
								check={ check }
								item={ items[ check.id ] }
							/>
						) ) }
					</ul>
				</CardBody>
			</Card>
		);
	}

	const problems = CHECKS.filter( ( check ) =>
		isProblem( items[ check.id ] )
	);
	const others = CHECKS.filter(
		( check ) => ! isProblem( items[ check.id ] )
	);
	const skipped = others.some(
		( check ) => 'skip' === items[ check.id ].state
	);
	const othersTitle = skipped
		? /* translators: %d: number of checks */
		  sprintf( __( 'Passed and skipped (%d)', 'publishio' ), others.length )
		: /* translators: %d: number of checks */
		  sprintf( __( 'Passed (%d)', 'publishio' ), others.length );

	return (
		<>
			{ problems.length > 0 && (
				<Card>
					<CardHeader>
						<h2 className="m-0 text-sm font-semibold text-gray-900">
							{ sprintf(
								/* translators: %d: number of problems */
								_n(
									'%d problem',
									'%d problems',
									problems.length,
									'publishio'
								),
								problems.length
							) }
						</h2>
					</CardHeader>
					<CardBody className="!p-0">
						<ul className="m-0 list-none p-0">
							{ problems.map( ( check ) => (
								<CheckRow
									key={ check.id }
									check={ check }
									item={ items[ check.id ] }
									expanded
								/>
							) ) }
						</ul>
					</CardBody>
				</Card>
			) }
			{ others.length > 0 && (
				<Card className="publishio-card-panel overflow-hidden">
					<PanelBody
						title={ othersTitle }
						initialOpen={ ! problems.length }
					>
						<ul className="m-0 -mx-4 list-none p-0">
							{ others.map( ( check ) => (
								<CheckRow
									key={ check.id }
									check={ check }
									item={ items[ check.id ] }
								/>
							) ) }
						</ul>
					</PanelBody>
				</Card>
			) }
		</>
	);
}
