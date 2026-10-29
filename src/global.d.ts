declare module '*.scss';
declare module '*.css';

interface Window {
	__publishio?: {
		siteUrl: string;
		siteName: string;
	};
	publishioAdmin?: {
		pluginVersion: string;
		logoUrl: string;
		appLogos: Record< string, string >;
		appHosts: Record< string, string[] >;
		mcpServerUrl: string;
		issuerUrl: string;
		tokenUrl: string;
		statusUrl: string;
		autoRunChecks: boolean;
		/** The content guide, on the Guide page only. */
		skillFile?: string;
		guideImages: {
			claude: {
				connectorMenu: string;
				connectorForm: string;
				clickConnect: string;
				consent: string;
			};
			openai: {
				developerMode: string;
				pluginForm: string;
				signIn: string;
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
