<?php
/**
 * E2E-only: route wp_mail through Mailpit when running under wp-env in CI.
 *
 * Mounted into the wp-env container as a must-use plugin via the
 * `mappings` block in `.wp-env.json`. The CI workflow connects a
 * `axllent/mailpit` container to wp-env's docker network and then
 * defines `HEZARFEN_E2E_MAILPIT_HOST` via `wp config set`. This file
 * checks for that constant and short-circuits otherwise — so on a
 * developer's LocalWP install (where the constant is not defined) it
 * is a no-op and the rest of the email tests keep using the cheap
 * order-note + direct-render approach.
 *
 * @package Hezarfen\E2E
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'HEZARFEN_E2E_MAILPIT_HOST' ) || ! HEZARFEN_E2E_MAILPIT_HOST ) {
	return;
}

add_action(
	'phpmailer_init',
	static function ( $phpmailer ) {
		$phpmailer->isSMTP();
		$phpmailer->Host        = HEZARFEN_E2E_MAILPIT_HOST;
		$phpmailer->Port        = defined( 'HEZARFEN_E2E_MAILPIT_PORT' )
			? (int) HEZARFEN_E2E_MAILPIT_PORT
			: 1025;
		$phpmailer->SMTPAuth    = false;
		$phpmailer->SMTPAutoTLS = false;
		$phpmailer->SMTPSecure  = '';
		// PHPMailer in WP defaults `From` to `wordpress@<site_url_host>`,
		// which on wp-env resolves to `wordpress@localhost` and trips
		// some MTAs' sender validation. Pin a stable test sender so the
		// spec's "from" assertions are deterministic.
		$phpmailer->From     = 'wordpress@hezarfen-e2e.test';
		$phpmailer->FromName = 'Hezarfen E2E';
	}
);
