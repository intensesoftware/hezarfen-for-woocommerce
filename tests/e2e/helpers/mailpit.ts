/**
 * Thin wrapper over Mailpit's HTTP API.
 *
 * Mailpit binds an SMTP listener on :1025 and an HTTP API on :8025.
 * In CI the workflow connects the Mailpit container to wp-env's
 * docker network and tells WP via a `wp config set` constant; the
 * mu-plugin in `tests/e2e/fixtures/mailpit-mu-plugin.php` then
 * routes `wp_mail` through SMTP. From the runner host (where
 * Playwright runs) the HTTP API is reachable on `localhost:8025` —
 * we read it via this helper.
 *
 * Locally, neither Mailpit nor the constant are present and the
 * matching spec is `test.skip`'d via `HEZARFEN_E2E_MAILPIT_API`.
 *
 * Mailpit API reference:
 *   GET    /api/v1/messages      → { total, count, messages: [...] }
 *   GET    /api/v1/message/{id}  → { HTML, Text, Headers, From, To, Cc, Bcc, ... }
 *   DELETE /api/v1/messages      → purge all
 */

const API_BASE =
	process.env.HEZARFEN_E2E_MAILPIT_API || 'http://localhost:8025';

export interface MailpitAddress {
	Name: string;
	Address: string;
}

export interface MailpitSummary {
	ID: string;
	From: MailpitAddress;
	To: MailpitAddress[];
	Cc?: MailpitAddress[];
	Bcc?: MailpitAddress[];
	Subject: string;
	Created: string;
	Snippet: string;
}

export interface MailpitFullMessage {
	ID: string;
	From: MailpitAddress;
	To: MailpitAddress[];
	Cc?: MailpitAddress[];
	Bcc?: MailpitAddress[];
	Subject: string;
	HTML: string;
	Text: string;
	Headers: Record< string, string[] >;
}

interface ListResponse {
	total: number;
	count: number;
	messages: MailpitSummary[];
}

export async function clearMailpit(): Promise< void > {
	const res = await fetch( `${ API_BASE }/api/v1/messages`, {
		method: 'DELETE',
	} );
	if ( ! res.ok ) {
		throw new Error(
			`mailpit DELETE /messages failed: ${ res.status } ${ res.statusText }`
		);
	}
}

export async function listMailpitMessages(): Promise< MailpitSummary[] > {
	const res = await fetch( `${ API_BASE }/api/v1/messages` );
	if ( ! res.ok ) {
		throw new Error(
			`mailpit GET /messages failed: ${ res.status } ${ res.statusText }`
		);
	}
	const data = ( await res.json() ) as ListResponse;
	return data.messages || [];
}

export async function getMailpitMessage(
	id: string
): Promise< MailpitFullMessage > {
	const res = await fetch( `${ API_BASE }/api/v1/message/${ id }` );
	if ( ! res.ok ) {
		throw new Error(
			`mailpit GET /message/${ id } failed: ${ res.status } ${ res.statusText }`
		);
	}
	return ( await res.json() ) as MailpitFullMessage;
}

/**
 * Poll Mailpit until a message matching `predicate` shows up. The
 * mailer chain on the WP side (do_action → WC_Email::trigger →
 * wp_mail → PHPMailer → SMTP → Mailpit) is async-ish across the
 * docker boundary, so a short tail-loop is more reliable than a
 * single `listMailpitMessages` snapshot.
 */
export async function waitForMailpitMessage(
	predicate: ( m: MailpitSummary ) => boolean,
	timeoutMs = 15_000
): Promise< MailpitSummary > {
	const start = Date.now();
	let last: MailpitSummary[] = [];
	while ( Date.now() - start < timeoutMs ) {
		last = await listMailpitMessages();
		const hit = last.find( predicate );
		if ( hit ) return hit;
		await new Promise( ( r ) => setTimeout( r, 250 ) );
	}
	const seen = last.map( ( m ) => ( {
		id: m.ID,
		to: m.To.map( ( t ) => t.Address ),
		subject: m.Subject,
	} ) );
	throw new Error(
		`mailpit: no message matched within ${ timeoutMs }ms. Saw: ${ JSON.stringify(
			seen
		) }`
	);
}
