import { Logo } from './Logo';

export function LoadingPreview() {
	return (
		<div className="aspect-video relative rounded-md overflow-hidden border border-(--rule-soft) loading-surface">
			<div className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-60 h-60 rounded-full pointer-events-none loading-halo animate-mcp-halo" />
			<div className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 size-20 rounded-xl overflow-hidden bg-white ring-1 ring-black/5 animate-mcp-float">
				<Logo className="block size-full" />
			</div>
			<div className="absolute left-6 right-6 bottom-6 h-0.5 rounded overflow-hidden bg-(--rule-soft)">
				<div className="absolute inset-y-0 w-1/2 sweep-fill animate-mcp-sweep" />
			</div>
		</div>
	);
}
