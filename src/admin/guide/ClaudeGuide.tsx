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

interface ClaudeImages {
	connectorMenu: string;
	connectorForm: string;
	clickConnect: string;
	consent: string;
}

function getSteps( mcpUrl: string, images: ClaudeImages ): GuideStep[] {
	return [
		{
			content: (
				<>
					{ __( 'Go to', 'rtcamp-publish-with-ai' ) }{ ' ' }
					<a
						href="https://claude.ai/customize/connectors"
						target="_blank"
						rel="noreferrer"
						className="text-blue-600 underline hover:text-blue-800"
					>
						Claude › Customize › Connectors
					</a>{ ' ' }
					{ __(
						'and click on "Add Connector"',
						'rtcamp-publish-with-ai'
					) }
				</>
			),
		},
		{
			content: __(
				'Choose "Add Custom Connector"',
				'rtcamp-publish-with-ai'
			),
			image: images.connectorMenu,
		},
		{
			content: __( 'In the name field, use', 'rtcamp-publish-with-ai' ),
			image: images.connectorForm,
			extra: (
				<div className="mt-3">
					<CopyField
						label={ __(
							'Connector name',
							'rtcamp-publish-with-ai'
						) }
						value="Publish With AI — rtCamp"
					/>
				</div>
			),
		},
		{
			content: __( 'Copy this MCP Server URL', 'rtcamp-publish-with-ai' ),
			extra: (
				<div className="mt-3">
					<CopyField
						label={ __(
							'MCP Server URL',
							'rtcamp-publish-with-ai'
						) }
						value={ mcpUrl }
					/>
				</div>
			),
		},
		{
			content: __(
				'Paste it in the "Remote MCP server URL" field',
				'rtcamp-publish-with-ai'
			),
		},
		{
			content: __( 'Click on "Add"', 'rtcamp-publish-with-ai' ),
		},
		{
			content: __(
				'Choose the connector and click on "Connect"',
				'rtcamp-publish-with-ai'
			),
			image: images.clickConnect,
		},
		{
			content: __(
				'Claude will redirect you to your website for authorization. Check the details and click "Allow"',
				'rtcamp-publish-with-ai'
			),
			image: images.consent,
		},
		{
			content: __(
				'You are all set! You can now use Claude to generate content for your WordPress site.',
				'rtcamp-publish-with-ai'
			),
		},
	];
}

export function ClaudeGuide() {
	const mcpUrl = window.rtPublishWithAIAdmin?.mcpServerUrl ?? '';
	const images = window.rtPublishWithAIAdmin?.guideImages.claude ?? {
		connectorMenu: '',
		connectorForm: '',
		clickConnect: '',
		consent: '',
	};
	const steps = getSteps( mcpUrl, images );

	return (
		<div className="p-6 my-10 max-w-2xl mx-auto w-full">
			<GuideSteps steps={ steps } />
		</div>
	);
}
