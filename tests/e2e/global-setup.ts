import type { FullConfig } from '@playwright/test';
import { wp } from './helpers/wp-cli';

/**
 * Bring the LocalWP site into a known state for the e2e suite:
 *   - Storefront theme active
 *   - Hezarfen + WooCommerce plugins active
 *   - At least one published simple product available
 *   - Cash on Delivery (COD) gateway enabled
 *   - A flat-rate shipping method available for orders shipped to TR
 *   - Hezarfen contracts + TC ID requirement disabled so the checkout flow
 *     under test stays focused on il/ilçe/mahalle + order placement.
 */
export default async function globalSetup( _config: FullConfig ): Promise< void > {
	wp( [ 'theme', 'activate', 'storefront' ] );

	wp( [ 'plugin', 'activate', 'woocommerce', 'hezarfen-for-woocommerce' ] );

	ensureWooCommercePages();
	ensurePrettyPermalinks();
	ensureHezarfenEncryptionKey();
	ensureMssContracts();
	ensureHPOSEnabled();

	wp( [ 'option', 'update', 'woocommerce_default_country', 'TR:TR06' ] );
	wp( [ 'option', 'update', 'woocommerce_currency', 'TRY' ] );
	wp( [ 'option', 'update', 'woocommerce_enable_guest_checkout', 'yes' ] );
	wp( [ 'option', 'update', 'woocommerce_enable_checkout_login_reminder', 'no' ] );
	wp( [ 'option', 'update', 'woocommerce_enable_signup_and_login_from_checkout', 'no' ] );
	// Make sure the storefront is publicly accessible during tests.
	wp( [ 'option', 'update', 'woocommerce_coming_soon', 'no' ] );
	wp( [ 'plugin', 'deactivate', 'coming-soon' ], { allowFailure: true } );

	wp( [ 'option', 'update', 'hezarfen_enable_district_neighborhood_fields', 'yes' ] );
	wp( [ 'option', 'update', 'hezarfen_contracts_enabled', 'no' ] );
	wp( [ 'option', 'update', 'hezarfen_checkout_show_TC_identity_field', 'no' ] );
	wp( [ 'option', 'update', 'hezarfen_checkout_is_TC_identity_number_field_required', 'no' ] );
	wp( [ 'option', 'update', 'hezarfen_show_hezarfen_checkout_tax_fields', 'no' ] );

	ensureCodEnabled();
	ensureFlatRateShipping();
	ensureTestProduct();
	ensureClassicCheckoutPage();
	ensureE2ECustomer();
	ensureE2EAdmin();
}

/**
 * Make sure the WooCommerce-managed pages (Cart, Checkout, My Account,
 * Shop) exist and their IDs are pinned to the matching options. On a
 * fresh wp-env install WC's `WC_Install::install()` won't have run
 * because wp-env activates plugins during DB-less runtime — pages
 * end up missing and downstream wp-cli calls (`option get
 * woocommerce_checkout_page_id`) return 0. Idempotent on existing
 * sites (LocalWP) since `create_pages()` checks before inserting.
 */
function ensureWooCommercePages(): void {
	wp( [
		'eval',
		`if ( class_exists( 'WC_Install' ) ) { WC_Install::create_pages(); }`,
	] );
}

/**
 * Default WP installs use plain "?p=N" permalinks, which return 404 for
 * /checkout/ etc. Test specs hit those slugs directly, so we switch to
 * pretty permalinks and flush rewrites once. Idempotent.
 */
/**
 * Hezarfen's TC Identity field is rendered ONLY when
 * `PostMetaEncryption::health_check()` passes — that wants both
 * the `hezarfen_encryption_key_generated` option set to "yes" AND
 * the `HEZARFEN_ENCRYPTION_KEY` constant defined in wp-config.php.
 * On a real LocalWP install this is one-time admin UI work; for a
 * fresh wp-env we automate it. Idempotent on existing sites.
 */
function ensureHezarfenEncryptionKey(): void {
	const generated = wp(
		[ 'option', 'get', 'hezarfen_encryption_key_generated' ],
		{ allowFailure: true }
	).trim();
	if ( generated === 'yes' ) return;

	// Generate a key with the same routine the plugin uses internally.
	const key = wp( [
		'eval',
		`echo base64_encode( openssl_random_pseudo_bytes( 64 ) );`,
	] ).trim();

	// Pin it as a constant in wp-config.php so future requests can
	// read it. wp config set defaults to a quoted string constant
	// when no --type is given.
	wp( [ 'config', 'set', 'HEZARFEN_ENCRYPTION_KEY', key ] );

	// Now mark the option + write a tester ciphertext. The constant
	// is now in wp-config.php so the next wp-cli request will see it.
	wp( [
		'eval-file',
		'wp-content/plugins/hezarfen-for-woocommerce/tests/e2e/fixtures/seed-encryption-tester.php',
	] );
}

/**
 * Seed the MSS / OBF contracts settings so the contracts spec finds
 * a checkbox to validate. We hand it the same shape Hezarfen's
 * settings UI would persist: two contracts, both rendered in the
 * combined-checkbox layout.
 */
function ensureMssContracts(): void {
	const existing = wp(
		[ 'option', 'get', 'hezarfen_mss_settings' ],
		{ allowFailure: true }
	).trim();
	if ( existing && existing.includes( 'mesafeli' ) ) return;

	// Use a "Sample Page" or any published page as the contract
	// template — the renderer just needs a valid post id with content.
	const templateId = wp( [
		'post',
		'list',
		'--post_type=page',
		'--post_status=publish',
		'--field=ID',
		'--posts_per_page=1',
	] )
		.trim()
		.split( '\n' )[ 0 ];

	wp( [
		'eval',
		`
			update_option( 'hezarfen_mss_settings', array(
				'agreement_creation_timing' => 'processing',
				'odeme_sayfasinda_sozlesme_gosterim_tipi' => 'modal',
				'contracts' => array(
					array(
						'name' => 'Mesafeli Satış Sözleşmesi',
						'template_id' => '${ templateId }',
						'enabled' => '1',
						'show_in_checkbox' => '1',
						'id' => 'mss',
					),
					array(
						'name' => 'Ön Bilgilendirme Formu',
						'template_id' => '${ templateId }',
						'enabled' => '1',
						'show_in_checkbox' => '1',
						'id' => 'obf',
					),
				),
			) );
		`,
	] );
}

/**
 * Make sure HPOS (High-Performance Order Storage) is on. The
 * admin-order-edit specs hit /wp-admin/admin.php?page=wc-orders which
 * only exists when HPOS is enabled, and on a fresh wp-env install WC
 * defaults to legacy CPT order storage.
 */
function ensureHPOSEnabled(): void {
	const current = wp(
		[ 'option', 'get', 'woocommerce_custom_orders_table_enabled' ],
		{ allowFailure: true }
	).trim();
	if ( current === 'yes' ) return;
	// Use string-form FQCNs ('Foo\\Bar\\Baz') and let `class_exists`
	// resolve them — that avoids escaping a backslash through TS →
	// argv → PHP eval, which is brittle.
	// Backslash-heavy PHP code is brittle to escape through TS → argv
	// → wp-cli eval. Hand it off to `wp eval-file` with a real PHP
	// file on disk. Path is relative to the WP root, which works in
	// both LocalWP (real fs) and wp-env (container fs) since the
	// plugin sits under wp-content/plugins/ in either case.
	wp( [
		'eval-file',
		'wp-content/plugins/hezarfen-for-woocommerce/tests/e2e/fixtures/enable-hpos.php',
	] );
}

function ensurePrettyPermalinks(): void {
	wp( [ 'rewrite', 'structure', '/%postname%/', '--hard' ] );
	wp( [ 'rewrite', 'flush', '--hard' ] );
	// On wp-env the cli container runs as a different unix user than
	// the wordpress container, so `wp rewrite flush --hard` silently
	// fails to write /var/www/html/.htaccess. Drop the canonical
	// WP-generated .htaccess directly via PHP inside the WP runtime.
	wp( [
		'eval',
		`
			$path = ABSPATH . '.htaccess';
			$rules = "# BEGIN WordPress\\n" .
				"<IfModule mod_rewrite.c>\\n" .
				"RewriteEngine On\\n" .
				"RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\\n" .
				"RewriteBase /\\n" .
				"RewriteRule ^index\\\\.php$ - [L]\\n" .
				"RewriteCond %{REQUEST_FILENAME} !-f\\n" .
				"RewriteCond %{REQUEST_FILENAME} !-d\\n" .
				"RewriteRule . /index.php [L]\\n" .
				"</IfModule>\\n" .
				"# END WordPress\\n";
			file_put_contents( $path, $rules );
		`,
	] );
}

/**
 * Force the /checkout/ page to use the classic shortcode. The plugin
 * does not (yet) support the WooCommerce Cart/Checkout blocks, so a
 * site that drifted onto the new block-based checkout would silently
 * break every test. We rewrite to the classic shortcode here so the
 * suite is self-healing.
 */
function ensureClassicCheckoutPage(): void {
	const checkoutId = wp( [
		'option',
		'get',
		'woocommerce_checkout_page_id',
	] ).trim();
	if ( ! checkoutId ) return;

	const content = wp( [
		'post',
		'get',
		checkoutId,
		'--field=post_content',
	] );
	if (
		content.includes( 'wp:woocommerce/classic-shortcode' ) &&
		content.includes( 'checkout' )
	) {
		return;
	}
	wp( [
		'post',
		'update',
		checkoutId,
		'--post_content=<!-- wp:woocommerce/classic-shortcode {"shortcode":"checkout","className":"wc-block-checkout"} /-->',
	] );
}

const E2E_CUSTOMER_USERNAME = 'hezarfen-e2e-customer';
const E2E_CUSTOMER_EMAIL = 'hezarfen-e2e-customer@example.test';
const E2E_CUSTOMER_PASSWORD = 'hezarfen-e2e-pass-1234';

function ensureE2ECustomer(): void {
	const existing = wp(
		[ 'user', 'get', E2E_CUSTOMER_USERNAME, '--field=ID' ],
		{ allowFailure: true }
	).trim();
	if ( existing ) {
		// Reset the password each run so the value the test uses always
		// matches what the WP user has stored.
		wp( [
			'user',
			'update',
			existing,
			`--user_pass=${ E2E_CUSTOMER_PASSWORD }`,
		] );
		return;
	}
	wp( [
		'user',
		'create',
		E2E_CUSTOMER_USERNAME,
		E2E_CUSTOMER_EMAIL,
		'--role=customer',
		`--user_pass=${ E2E_CUSTOMER_PASSWORD }`,
		'--first_name=Ada',
		'--last_name=Lovelace',
	] );
}

export const E2E_CUSTOMER = {
	username: E2E_CUSTOMER_USERNAME,
	email: E2E_CUSTOMER_EMAIL,
	password: E2E_CUSTOMER_PASSWORD,
};

const E2E_ADMIN_USERNAME = 'hezarfen-e2e-admin';
const E2E_ADMIN_EMAIL = 'hezarfen-e2e-admin@example.test';
const E2E_ADMIN_PASSWORD = 'hezarfen-e2e-admin-pass-1234';

function ensureE2EAdmin(): void {
	const existing = wp(
		[ 'user', 'get', E2E_ADMIN_USERNAME, '--field=ID' ],
		{ allowFailure: true }
	).trim();
	if ( existing ) {
		wp( [
			'user',
			'update',
			existing,
			`--user_pass=${ E2E_ADMIN_PASSWORD }`,
		] );
		return;
	}
	wp( [
		'user',
		'create',
		E2E_ADMIN_USERNAME,
		E2E_ADMIN_EMAIL,
		'--role=administrator',
		`--user_pass=${ E2E_ADMIN_PASSWORD }`,
	] );
}

export const E2E_ADMIN = {
	username: E2E_ADMIN_USERNAME,
	email: E2E_ADMIN_EMAIL,
	password: E2E_ADMIN_PASSWORD,
};

function ensureCodEnabled(): void {
	// Disable the other built-in offline gateways so COD is the sole
	// option on the checkout — that keeps the test deterministic and
	// avoids racing with whichever gateway WooCommerce decides to
	// pre-select.
	for ( const gw of [ 'bacs', 'cheque', 'paypal' ] ) {
		wp(
			[
				'wc',
				'payment_gateway',
				'update',
				gw,
				'--user=1',
				'--enabled=false',
			],
			{ allowFailure: true }
		);
	}
	wp( [
		'wc',
		'payment_gateway',
		'update',
		'cod',
		'--user=1',
		'--enabled=true',
		'--title=Cash on delivery (e2e)',
	] );
}

function ensureFlatRateShipping(): void {
	// One-shot setup: create / reuse an "E2E Türkiye" zone, attach country=TR,
	// add a flat_rate method, and write its settings. Doing this in a single
	// eval avoids parsing wc-cli output formats that vary across versions.
	wp( [
		'eval',
		`
			$zone = null;
			foreach ( WC_Shipping_Zones::get_zones() as $z ) {
				if ( $z['zone_name'] === 'E2E Türkiye' ) {
					$zone = new WC_Shipping_Zone( $z['zone_id'] );
					break;
				}
			}
			if ( ! $zone ) {
				$zone = new WC_Shipping_Zone();
				$zone->set_zone_name( 'E2E Türkiye' );
				$zone->set_zone_order( 1 );
				$zone->save();
			}
			$zone->set_locations( array( array( 'code' => 'TR', 'type' => 'country' ) ) );
			$zone->save();

			$instance_id = null;
			foreach ( $zone->get_shipping_methods( false, 'json' ) as $m ) {
				if ( $m->id === 'flat_rate' ) { $instance_id = $m->instance_id; break; }
			}
			if ( ! $instance_id ) {
				$instance_id = $zone->add_shipping_method( 'flat_rate' );
			}

			update_option(
				'woocommerce_flat_rate_' . $instance_id . '_settings',
				array( 'title' => 'Standart Kargo', 'cost' => '10', 'tax_status' => 'none' )
			);
			echo 'zone_id=' . $zone->get_id() . ' instance_id=' . $instance_id;
		`,
	] );
}

function ensureTestProduct(): void {
	const existing = wp( [
		'post',
		'list',
		'--post_type=product',
		'--name=hezarfen-e2e-product',
		'--field=ID',
		'--posts_per_page=1',
	] ).trim();
	if ( existing ) return;

	const productId = wp( [
		'post',
		'create',
		'--post_type=product',
		'--post_status=publish',
		'--post_title=Hezarfen E2E Product',
		'--post_name=hezarfen-e2e-product',
		'--porcelain',
	] ).trim();

	if ( ! productId ) {
		throw new Error( 'Failed to create e2e test product' );
	}

	wp( [ 'post', 'meta', 'update', productId, '_regular_price', '50' ] );
	wp( [ 'post', 'meta', 'update', productId, '_price', '50' ] );
	wp( [ 'post', 'meta', 'update', productId, '_stock_status', 'instock' ] );
	wp( [ 'post', 'meta', 'update', productId, '_virtual', 'no' ] );
	wp( [ 'post', 'meta', 'update', productId, '_downloadable', 'no' ] );
	wp( [ 'post', 'term', 'set', productId, 'product_type', 'simple' ] );
}
