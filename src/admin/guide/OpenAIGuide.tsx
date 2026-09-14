/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { CopyField } from '../shared/CopyField';
import { GuideSteps } from '../shared/GuideSteps';
import type { GuideStep } from '../shared/GuideSteps';

interface OpenAIImages {
	developerMode: string;
	pluginForm: string;
	signIn: string;
	consent: string;
}

function getSteps( mcpUrl: string, images: OpenAIImages ): GuideStep[] {
	return [
		{
			content: (
				<>
					{ __( 'Go to', 'publishio' ) }{ ' ' }
					<a
						href="https://chatgpt.com/plugins#settings/Security"
						target="_blank"
						rel="noreferrer"
						className="text-blue-600 underline hover:text-blue-800"
					>
						ChatGPT › Settings › Security and login › Developer mode
					</a>{ ' ' }
					{ __( 'and turn on "Developer mode"', 'publishio' ) }
				</>
			),
			image: images.developerMode,
		},
		{
			content: (
				<>
					{ __( 'Go to', 'publishio' ) }{ ' ' }
					<a
						href="https://chatgpt.com/plugins#settings/Connectors?create-connector=true"
						target="_blank"
						rel="noreferrer"
						className="text-blue-600 underline hover:text-blue-800"
					>
						ChatGPT › Settings › Plugins
					</a>{ ' ' }
					{ __(
						'and click the + (add) icon to add a new plugin',
						'publishio'
					) }
				</>
			),
			image: images.pluginForm,
		},
		{
			content: __( 'In the name field, use', 'publishio' ),
			extra: (
				<div className="mt-3">
					<CopyField
						label={ __( 'Plugin name', 'publishio' ) }
						value="Publishio — rtCamp"
					/>
				</div>
			),
		},
		{
			content: __(
				'Paste this MCP Server URL in the "Server URL" field',
				'publishio'
			),
			extra: (
				<div className="mt-3">
					<CopyField
						label={ __( 'MCP Server URL', 'publishio' ) }
						value={ mcpUrl }
					/>
				</div>
			),
		},
		{
			content: __( 'Click on "Create"', 'publishio' ),
		},
		{
			content: __(
				'In the popup, click "Sign in with Publishio — rtCamp"',
				'publishio'
			),
			image: images.signIn,
		},
		{
			content: __(
				'ChatGPT will redirect you to your website for authorization. Check the details and click "Allow Access"',
				'publishio'
			),
			image: images.consent,
		},
		{
			content: __(
				'You are all set! You can now use ChatGPT to generate content for your WordPress site.',
				'publishio'
			),
		},
	];
}

export function OpenAIGuide() {
	const mcpUrl = window.publishioAdmin?.mcpServerUrl ?? '';
	const images = window.publishioAdmin?.guideImages.openai ?? {
		developerMode: '',
		pluginForm: '',
		signIn: '',
		consent: '',
	};
	const steps = getSteps( mcpUrl, images );

	return (
		<div className="p-6 my-10 max-w-2xl mx-auto w-full">
			<GuideSteps steps={ steps } />
		</div>
	);
}
