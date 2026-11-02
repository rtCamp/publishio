import { Logo } from './Logo';
import { ExternalIcon, KindIcon } from './Icons';
import type { CardText, ViewKind } from '../types';

const KIND_LABEL: Record< ViewKind, string > = {
	blocks: 'Blocks',
	pattern: 'Pattern',
	page: 'Page',
};

interface Props {
	kind: ViewKind;
	card: CardText;
	/** Whether to show the header's Open in editor button. */
	button: boolean;
	onOpen: () => void;
}

export function Header( { kind, card, button, onOpen }: Props ) {
	return (
		<header className="pc-head">
			<span className="logo-wrap">
				<span className="pc-logo">
					<Logo width="100%" height="100%" />
				</span>
				<span className="kind-badge" title={ KIND_LABEL[ kind ] }>
					<KindIcon kind={ kind } />
				</span>
			</span>
			<div className="pc-titles">
				<p className="pc-title">
					<span className="kind-tag">{ KIND_LABEL[ kind ] }</span>
					{ card.title }
				</p>
				{ ( card.status || card.subtitle ) && (
					<p className="pc-sub">
						{ card.status && (
							<span
								className={ `sub-st tone-${ card.status.tone }` }
							>
								{ card.status.word }
								{ card.subtitle && (
									<span className="sub-sep"> · </span>
								) }
							</span>
						) }
						{ card.subtitle }
					</p>
				) }
			</div>
			{ button && card.editUrl && (
				<button type="button" className="btn btn-p" onClick={ onOpen }>
					Open in editor
					<ExternalIcon />
				</button>
			) }
		</header>
	);
}
