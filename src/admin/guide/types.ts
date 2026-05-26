export type GuideApp = 'claude' | 'openai' | 'other';

export interface Guide {
	id: string;
	title: string;
	description: string;
	app: GuideApp;
}
