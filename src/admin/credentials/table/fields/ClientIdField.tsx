/**
 * Internal dependencies
 */
import type { OAuthCredential } from '../../types';

interface Props {
	item: OAuthCredential;
}

export function ClientIdField( { item }: Props ) {
	return (
		<code className="text-xs text-gray-600 font-mono break-all">
			{ item.client_id }
		</code>
	);
}
