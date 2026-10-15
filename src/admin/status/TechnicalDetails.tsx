/**
 * WordPress dependencies
 */
import { Fragment } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { check, copy } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { useCopied } from '../shared/useCopied';
import type {
	CheckResult,
	Exchange,
	Problem,
} from '../shared/site-check/types';

/**
 * Text with `code` spans, as the checks write it.
 *
 * @param props      Component props.
 * @param props.text Text to render.
 */
export function InlineCode( { text }: { text: string } ) {
	return (
		<>
			{ text.split( '`' ).map( ( part, index ) =>
				index % 2 ? (
					<code
						key={ index }
						className="rounded-[3px] px-1 py-px text-xs [box-decoration-break:clone]"
					>
						{ part }
					</code>
				) : (
					<Fragment key={ index }>{ part }</Fragment>
				)
			) }
		</>
	);
}

export function CodeBlock( { label, code }: { label: string; code: string } ) {
	const { copied, copyText } = useCopied();

	return (
		<div className="overflow-hidden rounded border border-gray-200">
			<div className="flex items-center justify-between border-b border-gray-200 bg-gray-50 py-0.5 pe-1 ps-2.5 text-xs text-gray-500">
				<span>{ label }</span>
				<Button
					size="small"
					icon={ copied ? check : copy }
					label={
						copied
							? __( 'Copied!', 'publishio' )
							: sprintf(
									/* translators: %s: name of the code, such as nginx */
									__( 'Copy the %s lines', 'publishio' ),
									label
							  )
					}
					onClick={ () => copyText( code ) }
				/>
			</div>
			<pre className="m-0 overflow-x-auto bg-gray-50 px-3 py-2.5 font-mono text-xs leading-relaxed">
				{ code }
			</pre>
		</div>
	);
}

function ExchangeList( { exchanges }: { exchanges: Exchange[] } ) {
	const fields: [ string, 'request' | 'expected' | 'got' ][] = [
		[ __( 'Request', 'publishio' ), 'request' ],
		[ __( 'Expected', 'publishio' ), 'expected' ],
		[ __( 'Got', 'publishio' ), 'got' ],
	];

	return (
		<ul className="m-0 list-none divide-y divide-gray-100 rounded border border-gray-200 p-0 text-xs @xl:hidden">
			{ exchanges.map( ( exchange, index ) => (
				<li key={ index } className="m-0 flex flex-col gap-1.5 p-2.5">
					{ exchange.step && (
						<strong className="text-gray-900">
							{ exchange.step }
						</strong>
					) }
					<dl className="m-0 flex flex-col gap-1">
						{ fields
							.filter( ( [ , key ] ) => '–' !== exchange[ key ] )
							.map( ( [ label, key ] ) => (
								<div key={ key }>
									<dt className="text-gray-500">{ label }</dt>
									<dd className="m-0 break-words">
										<InlineCode text={ exchange[ key ] } />
									</dd>
								</div>
							) ) }
					</dl>
				</li>
			) ) }
		</ul>
	);
}

interface TechnicalDetailsProps {
	result: CheckResult;
	problem: Problem;
}

export function TechnicalDetails( { result, problem }: TechnicalDetailsProps ) {
	const exchanges = result.exchanges ?? [];
	const withSteps = exchanges.some( ( exchange ) => exchange.step );

	return (
		<div className="@container flex flex-col gap-2.5 text-[13px] leading-relaxed text-gray-700">
			{ exchanges.length > 0 && <ExchangeList exchanges={ exchanges } /> }
			{ exchanges.length > 0 && (
				<div className="hidden overflow-x-auto rounded border border-gray-200 @xl:block">
					<table className="w-full min-w-[560px] table-fixed border-collapse text-xs leading-relaxed">
						<thead>
							<tr className="bg-gray-50 text-left text-gray-500">
								{ withSteps && (
									<th className="w-[13%] px-2.5 py-2 font-semibold">
										{ __( 'Step', 'publishio' ) }
									</th>
								) }
								<th
									className={ `${
										withSteps ? 'w-[27%]' : 'w-[30%]'
									} px-2.5 py-2 font-semibold` }
								>
									{ __( 'Request', 'publishio' ) }
								</th>
								<th
									className={ `${
										withSteps ? 'w-[20%]' : 'w-[22%]'
									} px-2.5 py-2 font-semibold` }
								>
									{ __( 'Expected', 'publishio' ) }
								</th>
								<th className="px-2.5 py-2 font-semibold">
									{ __( 'Got', 'publishio' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ exchanges.map( ( exchange, index ) => (
								<tr
									key={ index }
									className="border-t border-gray-100 align-top"
								>
									{ withSteps && (
										<td className="px-2.5 py-2">
											{ exchange.step }
										</td>
									) }
									<td className="px-2.5 py-2 break-words">
										<InlineCode text={ exchange.request } />
									</td>
									<td className="px-2.5 py-2 break-words">
										<InlineCode
											text={ exchange.expected }
										/>
									</td>
									<td className="px-2.5 py-2 break-words">
										<InlineCode text={ exchange.got } />
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) }
			{ result.notes?.map( ( note ) => (
				<p key={ note } className="m-0">
					<InlineCode text={ note } />
				</p>
			) ) }
			{ problem.cause && (
				<p className="m-0">
					<strong>{ __( 'Likely cause:', 'publishio' ) }</strong>{ ' ' }
					<InlineCode text={ problem.cause } />
				</p>
			) }
			{ problem.technicalFix && (
				<p className="m-0">
					<strong>{ __( 'Fix:', 'publishio' ) }</strong>{ ' ' }
					<InlineCode text={ problem.technicalFix } />
				</p>
			) }
			{ problem.snippets?.map( ( snippet ) => (
				<CodeBlock
					key={ snippet.label }
					label={ snippet.label }
					code={ snippet.code }
				/>
			) ) }
		</div>
	);
}
