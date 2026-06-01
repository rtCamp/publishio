export type GuideApp = 'claude' | 'openai';

export interface Guide {
	id: string;
	title: string;
	description: string;
	app: GuideApp;
}
