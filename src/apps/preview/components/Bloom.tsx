/* eslint-disable no-restricted-syntax */
import type { CSSProperties } from 'react';

import { LEAF } from './Icons';

const PETALS = [ 1, 2, 3, 4, 5 ];

/**
 * Five copies of the logo's leaf fan out from behind it, turn one full
 * circle, then fold back into one leaf.
 */
export function Bloom() {
	return (
		<div className="ld" role="status" aria-label="Rendering the preview">
			<svg
				className="bloom"
				viewBox="-940 -940 1880 1880"
				aria-hidden="true"
			>
				<defs>
					<radialGradient
						id="bloom-leaf"
						cx="0"
						cy="0"
						r="1"
						gradientUnits="userSpaceOnUse"
						gradientTransform="translate(255 713.5) rotate(-62.3589) scale(500.073 449.395)"
					>
						<stop stopColor="#DA2C43" />
						<stop offset="1" stopColor="#FA5D38" />
					</radialGradient>
				</defs>
				<g className="spin">
					{ PETALS.map( ( i ) => (
						<g
							key={ i }
							className="petal"
							style={
								{
									'--i': i,
									'--o': ( 0.92 - i * 0.13 ).toFixed( 2 ),
								} as CSSProperties
							}
						>
							<path d={ LEAF } transform="translate(-148 -835)" />
						</g>
					) ) }
					<path
						d={ LEAF }
						transform="translate(-148 -835)"
						fill="url(#bloom-leaf)"
					/>
				</g>
			</svg>
		</div>
	);
}
