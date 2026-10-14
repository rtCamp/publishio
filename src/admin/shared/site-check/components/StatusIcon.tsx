/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { CheckState } from '../types';
import type { Tone } from '../verdict';

import './status-icon.scss';

export type IconState = CheckState | Tone;

const SHAPE: Record< IconState, CheckState > = {
	pass: 'pass',
	success: 'pass',
	fail: 'fail',
	error: 'fail',
	warning: 'warning',
	skip: 'skip',
	blocked: 'skip',
	running: 'running',
	waiting: 'waiting',
	queued: 'queued',
};

const disc = ( fill: string ) => (
	<circle cx="12" cy="12" r="12" fill={ fill } />
);

const GLYPHS: Record< Exclude< CheckState, 'running' >, ReactNode > = {
	pass: (
		<>
			{ disc( '#00a32a' ) }
			<path
				fill="#fff"
				transform="translate(1.2 1.2) scale(0.9)"
				d="M16.5 7.5 10 13.9l-2.5-2.4-1 1 3.5 3.6 7.5-7.6z"
			/>
		</>
	),
	fail: (
		<>
			{ disc( '#d63638' ) }
			<path
				d="M8.7 8.7l6.6 6.6M15.3 8.7l-6.6 6.6"
				stroke="#fff"
				strokeWidth="2.4"
				strokeLinecap="round"
				fill="none"
			/>
		</>
	),
	warning: (
		<>
			{ disc( '#f0b849' ) }
			<path
				d="M12 7.2v5.6"
				stroke="#1e1e1e"
				strokeWidth="2.4"
				strokeLinecap="round"
			/>
			<circle cx="12" cy="16.6" r="1.35" fill="#1e1e1e" />
		</>
	),
	skip: (
		<>
			{ disc( '#8c8f94' ) }
			<path
				d="M7.4 8.2 11.2 12l-3.8 3.8M12.8 8.2 16.6 12l-3.8 3.8"
				stroke="#fff"
				strokeWidth="2"
				strokeLinecap="round"
				strokeLinejoin="round"
				fill="none"
			/>
		</>
	),
	waiting: (
		<circle
			className="publishio-status-icon__turning"
			cx="12"
			cy="12"
			r="9.5"
			stroke="#8c8f94"
			strokeWidth="2"
			strokeDasharray="3.4 3.06"
			fill="none"
		/>
	),
	queued: (
		<circle
			cx="12"
			cy="12"
			r="9.5"
			stroke="#c3c4c7"
			strokeWidth="2"
			fill="none"
		/>
	),
};

function defaultLabel( shape: CheckState ): string {
	return {
		pass: __( 'Passed', 'publishio' ),
		fail: __( 'Failed', 'publishio' ),
		warning: __( 'Warning', 'publishio' ),
		skip: __( 'Skipped', 'publishio' ),
		running: __( 'Running', 'publishio' ),
		waiting: __( 'Waiting', 'publishio' ),
		queued: __( 'Not run yet', 'publishio' ),
	}[ shape ];
}

interface StatusIconProps {
	state: IconState;
	size?: number;
	/** What screen readers announce; defaults to the state's name. */
	label?: string;
}

export function StatusIcon( { state, size = 20, label }: StatusIconProps ) {
	const shape = SHAPE[ state ];

	return (
		<span
			className="publishio-status-icon"
			style={ { width: size, height: size } }
		>
			{ 'running' === shape ? (
				<Spinner />
			) : (
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					{ GLYPHS[ shape ] }
				</svg>
			) }
			<span className="screen-reader-text">
				{ label ?? defaultLabel( shape ) }
			</span>
		</span>
	);
}
