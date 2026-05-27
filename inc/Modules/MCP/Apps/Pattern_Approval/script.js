let _id = 1;

function sendRequest( method, params ) {
	const id = _id++;
	window.parent.postMessage( { jsonrpc: '2.0', id, method, params }, '*' );
	return new Promise( ( resolve, reject ) => {
		window.addEventListener( 'message', function h( e ) {
			if ( e.data?.id !== id ) {
				return;
			}
			window.removeEventListener( 'message', h );
			if ( e.data.error ) {
				reject( new Error( e.data.error?.message ) );
			} else {
				resolve( e.data.result ?? {} );
			}
		} );
	} );
}

function onNotification( method, fn ) {
	window.addEventListener( 'message', ( e ) => {
		if ( e.data?.method === method && ! e.data.id ) {
			fn( e.data.params ?? {} );
		}
	} );
}

let pending = null;

// 1. Handshake
sendRequest( 'ui/initialize', {
	appCapabilities: { availableDisplayModes: [ 'inline' ] },
	clientInfo: { name: 'rtpwai-pattern-approval', version: '1.0.0' },
	protocolVersion: '2026-01-26',
} )
	.catch( () => {} )
	.finally( () => {
		window.parent.postMessage(
			{
				jsonrpc: '2.0',
				method: 'ui/notifications/initialized',
				params: {},
			},
			'*'
		);
	} );

// Shared handler: store pending data, call render-pattern, load iframe.
async function handlePreviewData( d ) {
	if ( ! d.pattern_name ) {
		return;
	}

	pending = {
		post_id: d.post_id,
		position: d.position,
		pattern_name: d.pattern_name,
		schema: d.schema,
	};

	try {
		const res = await sendRequest( 'tools/call', {
			name: 'rtpwai-render-pattern',
			arguments: { pattern_name: d.pattern_name, schema: d.schema },
		} );
		if ( res.isError ) {
			throw new Error( res.content?.[ 0 ]?.text ?? 'Render failed' );
		}

		document.getElementById( 'preview-frame' ).srcdoc =
			res.structuredContent?.preview_html ?? '';
		document.getElementById( 'loading' ).classList.add( 'hidden' );
		document.getElementById( 'ready' ).classList.remove( 'hidden' );
	} catch ( e ) {
		document.getElementById( 'loading' ).textContent =
			'Preview failed: ' + e.message;
	}
}

// 2. Receive insertion params from tool-result, then fetch full HTML preview.
onNotification( 'ui/notifications/tool-result', ( p ) => {
	if ( p.isError ) {
		document.getElementById( 'loading' ).textContent =
			'Preview failed: ' + ( p.content?.[ 0 ]?.text ?? 'Unknown error' );
		return;
	}
	handlePreviewData( p.structuredContent ?? {} );
} );

// 3. Insert — calls app-only tool directly, then notifies Claude.
document.getElementById( 'btn-insert' ).addEventListener( 'click', async () => {
	if ( ! pending ) {
		return;
	}
	const btn = document.getElementById( 'btn-insert' );
	const status = document.getElementById( 'status' );
	btn.disabled = true;
	document.getElementById( 'btn-cancel' ).disabled = true;
	status.textContent = 'Inserting…';
	try {
		await sendRequest( 'tools/call', {
			name: 'rtpwai-insert-pattern-confirmed',
			arguments: pending,
		} );
		status.textContent = 'Inserted ✓';
		await sendRequest( 'ui/message', {
			role: 'user',
			content: [
				{
					type: 'text',
					text: 'Pattern inserted successfully. Please continue.',
				},
			],
		} );
	} catch ( e ) {
		status.textContent = 'Error: ' + e.message;
		btn.disabled = false;
		document.getElementById( 'btn-cancel' ).disabled = false;
	}
} );

// 4. Cancel
document.getElementById( 'btn-cancel' ).addEventListener( 'click', async () => {
	const status = document.getElementById( 'status' );
	document.getElementById( 'btn-insert' ).disabled = true;
	document.getElementById( 'btn-cancel' ).disabled = true;
	status.textContent = 'Cancelling…';
	try {
		await sendRequest( 'ui/message', {
			role: 'user',
			content: [
				{
					type: 'text',
					text: 'User cancelled the pattern insertion. Do not insert this pattern.',
				},
			],
		} );
		status.textContent = 'Cancelled';
	} catch ( e ) {
		status.textContent = e.message;
		document.getElementById( 'btn-insert' ).disabled = false;
		document.getElementById( 'btn-cancel' ).disabled = false;
	}
} );
