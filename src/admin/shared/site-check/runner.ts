/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type {
	CheckContext,
	CheckDefinition,
	CheckId,
	CheckResult,
	RunItems,
} from './types';

const PENDING = [ 'queued', 'waiting', 'running' ];

export function initialItems( checks: CheckDefinition[] ): RunItems {
	return Object.fromEntries(
		checks.map( ( check ) => [
			check.id,
			{ state: check.needs.length ? 'waiting' : 'queued' },
		] )
	) as RunItems;
}

/**
 * Run checks in parallel. A check with `needs` starts once all of them passed, and is skipped if any didn't.
 *
 * @param checks   Checks to run.
 * @param context  Everything checks need, except other checks' outputs.
 * @param onChange Called with a copy of every check's state whenever one changes.
 */
export async function runChecks(
	checks: CheckDefinition[],
	context: Omit< CheckContext, 'output' >,
	onChange: ( items: RunItems ) => void
): Promise< RunItems > {
	const items = initialItems( checks );
	const outputs: Partial< Record< CheckId, Record< string, string > > > = {};
	const names = Object.fromEntries(
		checks.map( ( check ) => [ check.id, check.name ] )
	);
	const fullContext: CheckContext = {
		...context,
		output: ( id ) => outputs[ id ] ?? {},
	};

	const update = ( id: CheckId, item: RunItems[ CheckId ] ) => {
		items[ id ] = item;
		onChange( { ...items } );
	};

	const dependentsOf = ( id: CheckId ) =>
		checks.filter(
			( check ) =>
				check.needs.includes( id ) &&
				'waiting' === items[ check.id ].state
		);

	const settle = async ( check: CheckDefinition ): Promise< void > => {
		if (
			check.needs.some( ( id ) => PENDING.includes( items[ id ].state ) )
		) {
			return;
		}

		const blocker = check.needs.find(
			( id ) => 'pass' !== items[ id ].state
		);
		if ( ! blocker ) {
			return run( check );
		}

		const summary = sprintf(
			/* translators: %s: name of the check this one needs */
			__( 'Skipped because %s didn’t pass', 'publishio' ),
			names[ blocker ] ?? blocker
		);
		update( check.id, {
			state: 'skip',
			result: { status: 'skip', code: 'needs', summary },
		} );
		await Promise.all( dependentsOf( check.id ).map( settle ) );
	};

	const run = async ( check: CheckDefinition ): Promise< void > => {
		const startedAt = Date.now();
		update( check.id, { state: 'running', startedAt } );

		let result: CheckResult;
		try {
			result = await check.run( fullContext );
		} catch ( error ) {
			result = {
				status: 'fail',
				code: 'unexpected',
				summary: __( 'The check stopped with an error', 'publishio' ),
				notes: [ String( error ) ],
			};
		}

		if ( result.output ) {
			outputs[ check.id ] = result.output;
		}
		update( check.id, {
			state: result.status,
			result,
			startedAt,
			finishedAt: Date.now(),
		} );
		await Promise.all( dependentsOf( check.id ).map( settle ) );
	};

	await Promise.all(
		checks.filter( ( check ) => ! check.needs.length ).map( run )
	);

	return { ...items };
}
