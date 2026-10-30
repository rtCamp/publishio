/** Which card the app shows: inserted blocks, a saved pattern or a whole page. */
export type ViewKind = 'blocks' | 'pattern' | 'page';

/** The status word's color, from WordPress admin's own. */
export type Tone = 'ok' | 'info' | 'warn' | 'muted';

/** Tool arguments or a tool's structured result. */
export type Args = Record< string, unknown >;

/** What the app asks `render-preview` to show, built from the tool call it follows. */
export interface PreviewRequest {
	view: ViewKind;
	post_id?: number;
	paths?: string[];
	placement?: string;
	targeted?: boolean;
	pattern?: string;
	updated?: boolean;
}

export interface CardText {
	title: string;
	status: { word: string; tone: Tone } | null;
	subtitle: string;
	editUrl: string | null;
	error: string;
}

export interface SearchDetails {
	address: string;
	title: string;
	description: string | null;
	hidden: boolean;
}

export interface SocialDetails {
	image: string | null;
	site: string;
	title: string;
	description: string | null;
}

export interface PreviewBody {
	html: string;
	search?: SearchDetails;
	social?: SocialDetails;
}

export type BodyState =
	| { status: 'loading' }
	| { status: 'ready'; body: PreviewBody }
	| { status: 'error' };
