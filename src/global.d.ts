declare module '*.scss';
declare module '*.css';

interface Window {
	__pwai?: {
		siteUrl: string;
		siteName: string;
	};
	rtPublishWithAIAdmin?: {
		pluginVersion: string;
		logoUrl: string;
		appLogos: Record< string, string >;
		mcpServerUrl: string;
		guideImages: {
			claude: {
				connectorMenu: string;
				connectorForm: string;
				clickConnect: string;
				consent: string;
			};
		};
	};
}

interface Document {
	startViewTransition?: ( callback: () => void | Promise< void > ) => {
		ready: Promise< void >;
		finished: Promise< void >;
		updateCallbackDone: Promise< void >;
	};
}
