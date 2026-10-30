import { ExternalIcon, LeafIcon } from './Icons';

interface Props {
	message: string;
	canOpen: boolean;
	onRetry: () => void;
	onOpen: () => void;
}

/** Nothing was lost when only the preview fails, so the error stays calm: no red. */
export function ErrorView( { message, canOpen, onRetry, onOpen }: Props ) {
	return (
		<div className="er" role="alert">
			<span className="er-ico">
				<LeafIcon />
			</span>
			<p className="er-t">Couldn&apos;t show the preview</p>
			<p className="er-d">{ message }</p>
			<div className="er-b">
				<button type="button" className="btn btn-p" onClick={ onRetry }>
					Try again
				</button>
				{ canOpen && (
					<button
						type="button"
						className="btn btn-s"
						onClick={ onOpen }
					>
						Open in editor
						<ExternalIcon />
					</button>
				) }
			</div>
		</div>
	);
}
