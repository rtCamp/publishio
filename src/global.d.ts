declare module '*.scss';
declare module '*.css';

interface Window {
	rtPublishWithAIAdmin?: {
		pluginVersion: string;
		logoUrl: string;
		providerLogos: Record< string, string >;
	};
}

interface Document {
	startViewTransition?: ( callback: () => void | Promise< void > ) => {
		ready: Promise< void >;
		finished: Promise< void >;
		updateCallbackDone: Promise< void >;
	};
}
