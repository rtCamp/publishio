import { useState } from 'react';

import { Header } from './Header';
import { Bloom } from './Bloom';
import { ErrorView } from './ErrorView';
import { PreviewFrame } from './PreviewFrame';
import { SearchResult, SocialCard } from './SeoViews';
import type { BodyState, CardText, ViewKind } from '../types';

const TABS = [ 'Page', 'Search', 'Social' ] as const;

type Tab = ( typeof TABS )[ number ];

interface Props {
	kind: ViewKind;
	card: CardText;
	body: BodyState;
	onRetry: () => void;
	onOpen: () => void;
}

export function Card( { kind, card, body, onRetry, onOpen }: Props ) {
	const [ tab, setTab ] = useState< Tab >( 'Page' );
	const ready = body.status === 'ready' ? body.body : null;
	const tabs = kind === 'page' && ready !== null;

	return (
		<article className="pc" data-state={ body.status }>
			<Header
				kind={ kind }
				card={ card }
				button={ body.status !== 'error' }
				onOpen={ onOpen }
			/>

			{ tabs && (
				<div className="tabs" role="tablist" aria-label="Preview">
					{ TABS.map( ( name ) => (
						<button
							key={ name }
							type="button"
							role="tab"
							aria-selected={ tab === name }
							onClick={ () => setTab( name ) }
						>
							{ name }
						</button>
					) ) }
				</div>
			) }

			{ body.status === 'loading' && (
				<div className="pc-body fixed">
					<Bloom />
				</div>
			) }

			{ body.status === 'error' && (
				<div className="pc-body fixed">
					<ErrorView
						message={ card.error }
						canOpen={ card.editUrl !== null }
						onRetry={ onRetry }
						onOpen={ onOpen }
					/>
				</div>
			) }

			{ ready && (
				<PreviewFrame
					html={ ready.html }
					title={ `Preview of ${ card.title }` }
					hidden={ tabs && tab !== 'Page' }
				/>
			) }

			{ tabs && tab === 'Search' && ready.search && (
				<div className="pc-body pad">
					<SearchResult search={ ready.search } />
				</div>
			) }

			{ tabs && tab === 'Social' && ready.social && (
				<div className="pc-body pad">
					<SocialCard social={ ready.social } />
				</div>
			) }
		</article>
	);
}
