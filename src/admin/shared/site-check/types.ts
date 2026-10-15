export type CheckId = 'address' | 'mcp' | 'details' | 'apps' | 'access';

export type CheckStatus = 'pass' | 'warning' | 'fail' | 'skip';

export type CheckState = CheckStatus | 'queued' | 'waiting' | 'running';

export type AppId = 'claude' | 'claude_code' | 'chatgpt';

/** An app that failed App verification, and why. */
export interface AppFailure {
	id: AppId;
	code: string;
}

/** One request a check sent, and what came back. */
export interface Exchange {
	step?: string;
	request: string;
	expected: string;
	got: string;
}

export interface CheckResult {
	status: CheckStatus;
	code: string;
	/** Short line shown next to the check's name. */
	summary: string;
	/** Apps that failed, for the app verification check. */
	apps?: AppFailure[];
	exchanges?: Exchange[];
	/** Extra technical lines for the report. */
	notes?: string[];
	/** Values later checks read, such as addresses found in a response. */
	output?: Record< string, string >;
}

export interface SiteFacts {
	publishioVersion: string;
	wpVersion: string;
	phpVersion: string;
	multisite: boolean;
	homeUrl: string;
	siteUrl: string;
	mcpUrl: string;
	permalinks: 'plain' | 'pretty';
	mcpAdapter: { version: string; bundled: boolean; source: string } | null;
	mcpServer: boolean;
	tools: number;
}

export interface AppCheck {
	id: AppId;
	url: string;
	ok: boolean;
	code: string;
	ms?: number;
	status?: number;
	server?: string;
	error?: string;
	detail?: string;
}

export interface AppsResult {
	apps: AppCheck[];
	transport?: {
		curl: string | null;
		ssl: string | null;
		blockExternal: boolean;
		proxy: boolean;
	};
}

export interface CheckContext {
	mcpUrl: string;
	tokenUrl: string;
	/** Origin of the admin page the checks run from. */
	origin: string;
	facts: () => Promise< SiteFacts | null >;
	apps: () => Promise< AppsResult >;
	/** Output of a check this one needs. */
	output: ( id: CheckId ) => Record< string, string >;
}

export interface CheckDefinition {
	id: CheckId;
	name: string;
	description: string;
	needs: CheckId[];
	runningLabel: string;
	run: ( context: CheckContext ) => Promise< CheckResult >;
}

export interface RunItem {
	state: CheckState;
	result?: CheckResult;
	startedAt?: number;
	finishedAt?: number;
}

export type RunItems = Record< CheckId, RunItem >;

export interface Snippet {
	label: string;
	code: string;
}

/** Plain-language explanation of a failure code. */
export interface Problem {
	message: string;
	fix: string;
	cause?: string;
	technicalFix?: string;
	snippets?: Snippet[];
}
