import { Spark } from './Icons';

interface Props {
	name: string;
	description?: string | undefined;
}

export function PatternMeta( { name, description }: Props ) {
	return (
		<div className="flex items-center gap-2 mb-2 mcp-card-enter">
			<span className="flex text-(--brand-1)">
				<Spark size={ 16 } />
			</span>
			<div className="flex-1 min-w-0">
				<div className="text-sm font-medium text-(--ink) truncate">
					{ name }
				</div>
				{ description && (
					<div className="text-xs text-(--ink-3) mt-0.5 truncate">
						{ description }
					</div>
				) }
			</div>
		</div>
	);
}
