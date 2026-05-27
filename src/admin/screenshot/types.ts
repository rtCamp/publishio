export interface Provider {
	id: string;
	label: string;
	requires_key: boolean;
	key_label: string;
}

export interface ScreenshotSettings {
	enabled: boolean;
	provider: string;
	has_api_key: boolean;
	providers: Provider[];
}

export interface UpdateScreenshotPayload {
	enabled: boolean;
	provider: string;
	api_key: string;
}
