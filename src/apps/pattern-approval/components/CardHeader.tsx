import { Logo } from './Logo';
import { ExternalLink } from './Icons';

export function CardHeader() {
	const siteName = window.__rtpwai?.siteName;
	const siteUrl = window.__rtpwai?.siteUrl;

	return (
		<div className="flex items-center gap-2 px-3 py-2 border-b border-(--rule-soft)">
			<div className="rounded-md overflow-hidden shrink-0 bg-white ring-1 ring-black/5">
				<Logo className="block size-8" />
			</div>
			<div className="flex flex-col min-w-0 flex-1">
				<span className="text-sm font-medium text-(--ink)">
					Publish with AI
				</span>
				{ siteName && siteUrl && (
					<a
						href={ siteUrl }
						target="_blank"
						rel="noopener noreferrer"
						className="text-xs text-(--ink-3) flex items-center gap-1 no-underline"
					>
						<span>{ siteName }</span>
						<ExternalLink />
					</a>
				) }
				{ siteName && ! siteUrl && (
					<span className="text-xs text-(--ink-3)">{ siteName }</span>
				) }
			</div>
		</div>
	);
}
