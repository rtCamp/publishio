import type { RefObject } from 'react';

interface Props {
	previewHtml: string;
	status: string;
	busy: boolean;
	frameRef: RefObject< HTMLIFrameElement >;
	onInsert: () => void;
	onCancel: () => void;
}

export function PreviewView( {
	previewHtml,
	status,
	busy,
	frameRef,
	onInsert,
	onCancel,
}: Props ) {
	return (
		<div id="ready">
			<iframe
				ref={ frameRef }
				id="preview-frame"
				title="Pattern preview"
				srcDoc={ previewHtml }
			/>
			<div className="actions">
				<button
					className="secondary"
					disabled={ busy }
					onClick={ onCancel }
				>
					Discard
				</button>
				<button
					className="primary"
					disabled={ busy }
					onClick={ onInsert }
				>
					Insert pattern
				</button>
			</div>
			{ status && <div id="status">{ status }</div> }
		</div>
	);
}
