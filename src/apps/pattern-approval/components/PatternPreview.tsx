import { useFrameAutosize } from '../hooks/useFrameAutosize';

interface Props {
	html: string;
}

export function PatternPreview( { html }: Props ) {
	const ref = useFrameAutosize();

	return (
		<div className="rounded-md overflow-hidden border border-(--rule-soft)">
			<iframe
				ref={ ref }
				title="Pattern preview"
				srcDoc={ html }
				className="w-full block border-none min-h-40"
			/>
		</div>
	);
}
