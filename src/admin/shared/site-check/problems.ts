/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { AppFailure, AppId, CheckId, Problem, Snippet } from './types';

export const APP_NAMES: Record< AppId, string > = {
	claude: 'Claude',
	claude_code: 'Claude Code',
	chatgpt: 'ChatGPT',
};
export const APP_HOSTS: Record< AppId, string > = {
	claude: 'claude.ai',
	claude_code: 'claude.ai',
	chatgpt: 'chatgpt.com',
};
export const APP_IDS = Object.keys( APP_NAMES ) as AppId[];

const NGINX_DISCOVERY: Snippet = {
	label: 'nginx',
	code: 'location ^~ /.well-known/oauth- {\n    try_files $uri /index.php?$args;\n}',
};

const AUTHORIZATION_HEADER: Snippet[] = [
	{
		label: 'Apache (.htaccess)',
		code: 'RewriteEngine On\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]',
	},
	{
		label: 'nginx',
		code: 'fastcgi_param HTTP_AUTHORIZATION $http_authorization;',
	},
];

const OPEN_PATHS = '`/wp-json/`, `/.well-known/` and `/publishio-oauth/`';

function generic(): Record< string, Problem > {
	return {
		unreachable: {
			message: __(
				"This browser couldn't reach your site's address.",
				'publishio'
			),
			fix: __(
				'Check that the site is online and that its address in Settings → General is right.',
				'publishio'
			),
		},
		redirected: {
			message: __(
				'Requests to your site are redirected to a different address, and AI apps don’t follow redirects here.',
				'publishio'
			),
			fix: __(
				'An administrator can make both addresses in Settings → General match the address your site is served on, including https:// and www.',
				'publishio'
			),
		},
		redirected_away: {
			message: __(
				'Requests to your site are redirected to another site, such as a sign-in page that protects the site, and AI apps can’t get past it.',
				'publishio'
			),
			fix: __(
				'If a sign-in page or single sign-on protects the site, turn it off or let AI apps through. If the site redirects to another domain, such as www, an administrator can make both addresses in Settings → General match the address your site is served on.',
				'publishio'
			),
			cause: __(
				'An access gate in front of the site, such as Cloudflare Access or a host’s staging login, or a redirect to another host or to https://. The browser can’t see where a redirect to another site goes.',
				'publishio'
			),
			technicalFix: sprintf(
				/* translators: %s: list of URL paths */
				__(
					'Exclude %s from the access gate, or serve the site on the address in Settings → General without a redirect.',
					'publishio'
				),
				OPEN_PATHS
			),
		},
		password_protected: {
			message: __(
				"Your site asks for a username and password, and AI apps can't get past it.",
				'publishio'
			),
			fix: __(
				'Turn off password protection, or leave the addresses AI apps use open. Staging sites often have it turned on at the host.',
				'publishio'
			),
			cause: __(
				'HTTP authentication (a password prompt from the web server or the host) in front of the site.',
				'publishio'
			),
			technicalFix: sprintf(
				/* translators: %s: list of URL paths */
				__( 'Turn it off, or exclude %s from it.', 'publishio' ),
				OPEN_PATHS
			),
		},
		server_error: {
			message: __(
				'Your site had an error while answering.',
				'publishio'
			),
			fix: __(
				"Check your site's PHP error log, or send the report to your host.",
				'publishio'
			),
		},
		blocked_before_wordpress: {
			message: __(
				'Your web server, CDN or firewall blocked the request before WordPress saw it.',
				'publishio'
			),
			fix: __(
				'Ask your host or developer to let AI apps reach these addresses. The report shows which request was blocked.',
				'publishio'
			),
		},
		not_found: {
			message: __( 'WordPress didn’t find this address.', 'publishio' ),
			fix: __(
				'An administrator can deactivate and reactivate Publishio. If that doesn’t help, send the report to rtCamp.',
				'publishio'
			),
		},
		rest_blocked: {
			message: __(
				"A security plugin or setting blocks outside requests to your site's API.",
				'publishio'
			),
			fix: __(
				"Allow outside requests to Publishio's addresses in that plugin's settings. The report shows the plugin's message, which usually names it.",
				'publishio'
			),
			technicalFix: sprintf(
				/* translators: %s: list of URL paths */
				__(
					'Allow requests without login cookies to %s.',
					'publishio'
				),
				OPEN_PATHS
			),
		},
		timed_out: {
			message: __( 'Your site took too long to answer.', 'publishio' ),
			fix: __(
				'Check that the site is online and not overloaded. If it is, a firewall or security plugin may be holding requests from outside; send the report to your host.',
				'publishio'
			),
		},
		unexpected: {
			message: __(
				'Your site answered in a way Publishio didn’t expect.',
				'publishio'
			),
			fix: __(
				'Send the report to your developer or to rtCamp.',
				'publishio'
			),
		},
	};
}

function specific( check: CheckId ): Record< string, Problem > {
	switch ( check ) {
		case 'address':
			return {
				this_computer: {
					message: __(
						'This address only works on this computer. Claude Code running here can connect; Claude.ai, Claude Desktop and ChatGPT can’t.',
						'publishio'
					),
					fix: __(
						'To use Claude.ai or ChatGPT, put the site on a public https:// address, such as a staging site.',
						'publishio'
					),
				},
				private_network: {
					message: __(
						'This address only works on your local network. Claude Code on a computer that can reach it can connect; Claude.ai, Claude Desktop and ChatGPT can’t.',
						'publishio'
					),
					fix: __(
						'To use Claude.ai or ChatGPT, put the site on a public https:// address, such as a staging site.',
						'publishio'
					),
				},
				no_https: {
					message: __(
						'Your site uses http://. AI apps only connect over a secure connection.',
						'publishio'
					),
					fix: __(
						'Ask your host to set up an SSL certificate, then an administrator can change both addresses in Settings → General to https://.',
						'publishio'
					),
				},
			};
		case 'mcp':
			return {
				other_origin: {
					message: __(
						'This page and your MCP server are on different addresses, usually because the Site Address and WordPress Address in Settings → General are on different hosts. The browser can’t check the MCP server from here.',
						'publishio'
					),
					fix: __(
						'If the two addresses should be the same, an administrator can make them match in Settings → General. Otherwise, check the MCP server with the commands under “What these checks can’t see” on the Status page.',
						'publishio'
					),
				},
				no_challenge: {
					message: __(
						'Something between the app and WordPress removed the request to connect.',
						'publishio'
					),
					fix: __(
						'Ask your host or developer whether a proxy or CDN strips the WWW-Authenticate header.',
						'publishio'
					),
				},
				not_found: {
					message: __(
						"Publishio's MCP server isn't running.",
						'publishio'
					),
					fix: __(
						'An administrator can deactivate and reactivate Publishio. If that doesn’t help, another plugin may be interfering; the report shows which MCP Adapter loaded.',
						'publishio'
					),
				},
			};
		case 'details':
			return {
				blocked_before_wordpress: {
					message: __(
						'Your web server blocked the connection details before WordPress saw them.',
						'publishio'
					),
					fix: __(
						'Ask your host or developer to let addresses that start with /.well-known/oauth- reach WordPress. If your site is behind a CDN or firewall, allow those addresses there too.',
						'publishio'
					),
					cause: __(
						"A web server rule that blocks addresses starting with a dot (EasyEngine's nginx default), or a CDN or firewall rule on `/.well-known/`.",
						'publishio'
					),
					technicalFix: __(
						'Let `/.well-known/oauth-` addresses reach WordPress. For nginx:',
						'publishio'
					),
					snippets: [ NGINX_DISCOVERY ],
				},
				plain_permalinks: {
					message: __(
						'Your permalinks are set to Plain, so AI apps can’t find the connection details.',
						'publishio'
					),
					fix: __(
						'An administrator can choose any setting except Plain in Settings → Permalinks.',
						'publishio'
					),
				},
				not_found_in_wordpress: {
					message: __(
						'WordPress got the request but didn’t recognise the address.',
						'publishio'
					),
					fix: __(
						'An administrator can open Settings → Permalinks and click Save Changes. If that doesn’t help, send the report to rtCamp.',
						'publishio'
					),
				},
				answered_by_other: {
					message: __(
						'A caching or security plugin answered instead of Publishio.',
						'publishio'
					),
					fix: __(
						'Exclude addresses that start with /.well-known/ from that plugin’s caching or rules.',
						'publishio'
					),
				},
				wrong_details: {
					message: __(
						'The connection details point to a different address.',
						'publishio'
					),
					fix: __(
						'An administrator can check that both addresses in Settings → General match and use https://.',
						'publishio'
					),
				},
			};
		case 'access':
			return {
				key_dropped: {
					message: __(
						'Your server drops the access key before WordPress sees it, so apps connect but every action fails.',
						'publishio'
					),
					fix: __(
						"An administrator can go to Settings → Permalinks and click Save Changes, which rewrites WordPress's rules for the web server. If it still fails, send the report to your host.",
						'publishio'
					),
					cause: __(
						"PHP runs as CGI or FastCGI, and the web server doesn't pass the `Authorization` header to it.",
						'publishio'
					),
					technicalFix: __(
						"For Apache, save Settings → Permalinks: WordPress rewrites its block in .htaccess, which passes the key on. If WordPress can't write .htaccess, add these lines above `# BEGIN WordPress`. For nginx, add the line to the PHP location block.",
						'publishio'
					),
					snippets: AUTHORIZATION_HEADER,
				},
				blocked_earlier: {
					message: __(
						'Another plugin rejects requests before Publishio checks the access key, so connected apps are rejected too.',
						'publishio'
					),
					fix: __(
						"Allow requests with an access key in that plugin's settings. The report shows the plugin's error code.",
						'publishio'
					),
				},
			};
		default:
			return {};
	}
}

const APP_CAUSES: Record< string, ( hosts: string ) => Problem > = {
	not_allowed: ( hosts ) => ( {
		message: sprintf(
			/* translators: %s: host names */
			__(
				"This site only lets some apps connect, and it doesn't allow apps from %s.",
				'publishio'
			),
			hosts
		),
		fix: sprintf(
			/* translators: %s: host names */
			__(
				'If you want to use these apps, a developer can add %s to the publishio_cimd_allowed_hosts filter.',
				'publishio'
			),
			hosts
		),
	} ),
	blocked_by_wordpress: ( hosts ) => ( {
		message: __(
			'WordPress is set to block outgoing connections.',
			'publishio'
		),
		fix: sprintf(
			/* translators: %s: host names */
			__(
				'Add %s to WP_ACCESSIBLE_HOSTS in wp-config.php.',
				'publishio'
			),
			hosts
		),
	} ),
	no_curl: () => ( {
		message: __(
			'Your server is missing what WordPress needs for secure connections.',
			'publishio'
		),
		fix: __(
			"Ask your host to enable PHP's cURL extension with SSL.",
			'publishio'
		),
	} ),
	ssl_verify_off: () => ( {
		message: __(
			'A plugin or code snippet turned off certificate checks, and Publishio won’t verify apps without them.',
			'publishio'
		),
		fix: __(
			'Remove the code that sets https_ssl_verify to false.',
			'publishio'
		),
	} ),
	dns: ( hosts ) => ( {
		message: sprintf(
			/* translators: %s: host names */
			__( "Your server couldn't look up %s.", 'publishio' ),
			hosts
		),
		fix: __(
			"Ask your host to check the server's DNS, or whether a proxy is in the way.",
			'publishio'
		),
	} ),
	connection_failed: ( hosts ) => ( {
		message: sprintf(
			/* translators: %s: host names */
			__(
				"Your server couldn't connect to %s, probably because a firewall blocks outgoing connections.",
				'publishio'
			),
			hosts
		),
		fix: sprintf(
			/* translators: %s: host names */
			__(
				'Ask your host to allow outgoing HTTPS connections to %s.',
				'publishio'
			),
			hosts
		),
	} ),
	bad_status: ( hosts ) => ( {
		/* translators: %s: host names */
		message: sprintf( __( '%s refused your server.', 'publishio' ), hosts ),
		fix: sprintf(
			/* translators: %s: host names */
			__(
				"A firewall at %s may be blocking your server's IP address. Send the report to your host and ask whether outgoing requests leave from a shared or flagged IP address.",
				'publishio'
			),
			hosts
		),
	} ),
	not_json: ( hosts ) => ( {
		message: sprintf(
			/* translators: %s: host names */
			__(
				"Your server got a web page from %s instead of the app's sign-in details. A proxy at your host, or a firewall or bot check in front of the app, answered instead.",
				'publishio'
			),
			hosts
		),
		fix: __(
			'Send the report to your host and ask whether outgoing requests go through a proxy, or leave from a shared or flagged IP address.',
			'publishio'
		),
	} ),
	request_intercepted: ( hosts ) => ( {
		message: sprintf(
			/* translators: %s: host names */
			__(
				"WordPress didn't send the request to %s itself. Another plugin answered it, or changed how WordPress sends requests, so Publishio couldn't check the real app.",
				'publishio'
			),
			hosts
		),
		fix: sprintf(
			/* translators: %s: host names */
			__(
				'Look for a plugin that blocks, fakes or reroutes outgoing requests, and let requests to %s through.',
				'publishio'
			),
			hosts
		),
		cause: __(
			'A `pre_http_request` filter that answers requests itself, or an `http_api_transports` filter that moves WordPress off cURL. Publishio only trusts a request it sent through cURL.',
			'publishio'
		),
	} ),
	invalid_document: ( hosts ) => ( {
		message: sprintf(
			/* translators: %s: host names */
			__(
				'The sign-in details at %s are in a form this version of Publishio doesn’t accept.',
				'publishio'
			),
			hosts
		),
		fix: __(
			'Update Publishio. If you already have the latest version, send the report to rtCamp.',
			'publishio'
		),
	} ),
	check_failed: () => ( {
		message: __(
			"Publishio couldn't run this check on your server.",
			'publishio'
		),
		fix: __(
			'Reload the page and check again. If it keeps failing, send the report to rtCamp.',
			'publishio'
		),
	} ),
};

/**
 * Names as a phrase: "A", "A and B", or "A, B and C".
 *
 * @param names Names to list.
 */
export function listNames( names: string[] ): string {
	if ( names.length < 2 ) {
		return names[ 0 ] ?? '';
	}
	const head = names.slice( 0, -1 ).reduce( ( list, name ) =>
		sprintf(
			/* translators: 1: names so far, 2: next name */
			__( '%1$s, %2$s', 'publishio' ),
			list,
			name
		)
	);
	return sprintf(
		/* translators: 1: all names but the last, 2: the last name */
		__( '%1$s and %2$s', 'publishio' ),
		head,
		names.at( -1 ) ?? ''
	);
}

/**
 * Apps' names as a phrase.
 *
 * @param apps App ids.
 */
export function appNames( apps: AppId[] ): string {
	return listNames( apps.map( ( id ) => APP_NAMES[ id ] ) );
}

function appsProblem( code: string, apps: AppFailure[] ): Problem {
	const failures = apps.length
		? apps
		: APP_IDS.map( ( id ) => ( { id, code } ) );
	const causes = new Map< string, AppId[] >();
	for ( const app of failures ) {
		causes.set( app.code, [ ...( causes.get( app.code ) ?? [] ), app.id ] );
	}
	const problems = [ ...causes ].map( ( [ cause, ids ] ) =>
		( APP_CAUSES[ cause ] ?? APP_CAUSES[ 'check_failed' ] )!(
			listNames( [ ...new Set( ids.map( ( id ) => APP_HOSTS[ id ] ) ) ] )
		)
	);
	const consequence =
		failures.length < APP_IDS.length
			? sprintf(
					/* translators: %s: names of the apps that can't connect */
					__(
						'The approval page will show an error for %s until this is fixed.',
						'publishio'
					),
					appNames( failures.map( ( app ) => app.id ) )
			  )
			: __(
					'Approval pages for AI apps will show an error until this is fixed.',
					'publishio'
			  );

	return {
		...( 1 === problems.length ? problems[ 0 ] : {} ),
		message: [
			...problems.map( ( problem ) => problem.message ),
			consequence,
		].join( ' ' ),
		fix: [ ...new Set( problems.map( ( problem ) => problem.fix ) ) ].join(
			' '
		),
	};
}

/**
 * Plain-language explanation and fix for a check's failure code.
 *
 * @param check The check.
 * @param code  Its failure code.
 * @param apps  Apps that failed and why, for the app verification check.
 */
export function problemFor(
	check: CheckId,
	code: string,
	apps: AppFailure[] = []
): Problem {
	if ( 'apps' === check ) {
		return appsProblem( code, apps );
	}

	return (
		specific( check )[ code ] ??
		generic()[ code ] ??
		generic()[ 'unexpected' ]!
	);
}

/**
 * Every code a check can finish with, so a stored result can be checked before it's shown.
 *
 * @param check The check.
 */
export function knownCodes( check: CheckId ): string[] {
	const codes =
		'apps' === check
			? Object.keys( APP_CAUSES )
			: [
					...Object.keys( specific( check ) ),
					...Object.keys( generic() ),
			  ];
	return [ 'ok', 'needs', 'other_origin', ...codes ];
}
