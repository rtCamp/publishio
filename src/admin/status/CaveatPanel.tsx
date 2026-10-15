/**
 * WordPress dependencies
 */
import { Card, PanelBody } from '@wordpress/components'; // eslint-disable-line @wordpress/use-recommended-components
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { wellKnownUrl } from '../shared/site-check/checks';
import { CodeBlock } from './TechnicalDetails';

interface CaveatPanelProps {
	mcpUrl: string;
	issuerUrl: string;
}

/**
 * The requests an AI app starts with, as curl commands. Quoted, since a Plain permalinks URL has a `?` that zsh would expand.
 *
 * @param mcpUrl    MCP server URL.
 * @param issuerUrl OAuth issuer URL.
 */
function commands( mcpUrl: string, issuerUrl: string ): string {
	const lines: string[] = [];
	try {
		lines.push(
			`curl -i -X POST "${ mcpUrl }"`,
			`curl -i "${ wellKnownUrl( mcpUrl, 'oauth-protected-resource' ) }"`
		);
	} catch {}
	try {
		lines.push(
			`curl -i "${ wellKnownUrl(
				issuerUrl,
				'oauth-authorization-server'
			) }"`
		);
	} catch {}
	return lines.join( '\n' );
}

export function CaveatPanel( { mcpUrl, issuerUrl }: CaveatPanelProps ) {
	const code = commands( mcpUrl, issuerUrl );

	return (
		<Card className="publishio-card-panel overflow-hidden">
			<PanelBody
				title={ __( "What these checks can't see", 'publishio' ) }
				initialOpen={ false }
			>
				<div className="flex flex-col gap-3">
					<p className="m-0 text-[13px] text-gray-700">
						{ __(
							"Ready means your site works from your browser and your server. A firewall or bot protection that blocks data-center traffic, such as Cloudflare's Bot Fight Mode, can still stop an AI app's servers.",
							'publishio'
						) }{ ' ' }
						{ code &&
							__(
								'To test from outside, run these from another network:',
								'publishio'
							) }
					</p>
					{ code && (
						<CodeBlock
							label={ __( 'Terminal', 'publishio' ) }
							code={ code }
						/>
					) }
				</div>
			</PanelBody>
		</Card>
	);
}
