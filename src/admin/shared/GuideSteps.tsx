/**
 * External dependencies
 */
import type { ReactNode } from 'react';

export interface GuideStep {
	content: ReactNode;
	image?: string;
	extra?: ReactNode;
}

interface GuideStepsProps {
	steps: GuideStep[];
}

export function GuideSteps( { steps }: GuideStepsProps ) {
	return (
		<ol className="flex flex-col list-none m-0 p-0">
			{ steps.map( ( step, i ) => (
				<li key={ i } className="flex gap-4">
					<div className="flex flex-col items-center">
						<div className="flex items-center justify-center w-7 h-7 rounded-full bg-[var(--wp-admin-theme-color)] text-white text-xs font-bold shrink-0 leading-none">
							{ i + 1 }
						</div>
						{ i < steps.length - 1 && (
							<div className="w-px flex-1 min-h-3 bg-gray-200 my-1.5" />
						) }
					</div>
					<div className="pb-5 pt-0.5 flex-1 min-w-0">
						<p className="text-sm text-gray-700 m-0 leading-relaxed">
							{ step.content }
						</p>
						{ step.extra }
						{ step.image && (
							<img
								src={ step.image }
								alt=""
								aria-hidden="true"
								className="mt-3 rounded-lg border border-gray-200 shadow-sm w-full max-w-lg"
							/>
						) }
					</div>
				</li>
			) ) }
		</ol>
	);
}
