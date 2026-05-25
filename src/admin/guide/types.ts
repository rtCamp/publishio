export type GuideProvider = 'claude' | 'openai' | 'other';

export interface Guide {
	id: string;
	title: string;
	description: string;
	provider: GuideProvider;
}
