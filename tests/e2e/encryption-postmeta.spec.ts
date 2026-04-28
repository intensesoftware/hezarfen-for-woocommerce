import { expect, test } from '@playwright/test';
import { deleteOrder, seedTestOrder } from './helpers/orders';
import { wp } from './helpers/wp-cli';

/**
 * `_billing_hez_TC_number` is the TC Identity number stored on the
 * order. The plugin's contract is that it is **encrypted at rest**:
 *   - on write through Hezarfen's checkout filter
 *     ([Checkout.php](../../includes/Checkout.php) `→ encrypt(...)`)
 *   - on save through the meta-save hook in
 *     [class-hezarfen.php](../../includes/class-hezarfen.php)
 *     where it tries to read an existing ciphertext and re-encrypts.
 *
 * If anything regresses around `PostMetaEncryption::encrypt/decrypt`,
 * raw plaintext can leak into `wp_wc_orders_meta`, which is exactly
 * the failure mode the encryption feature exists to prevent. This
 * spec asserts the storage invariant directly: ciphertext in DB !=
 * plaintext, and decrypt(ciphertext) == plaintext.
 */
const PLAINTEXT_TC = '12345678901';
const META_KEY = '_billing_hez_TC_number';

let orderId: string;

test.describe( 'Hezarfen TC kimlik no encryption (postmeta düzeyinde)', () => {
	test.beforeAll( () => {
		orderId = seedTestOrder( { status: 'on-hold' } );
	} );
	test.afterAll( () => {
		deleteOrder( orderId );
	} );

	test( 'TC alanı encrypt edilerek HPOS meta tablosuna yazılıyor', async () => {
		// Mirror what the checkout layer does: hand the value through
		// PostMetaEncryption and update the order meta, then save.
		wp( [
			'eval',
			`
				$order = wc_get_order( ${ orderId } );
				if ( ! $order ) { echo 'ERR_NO_ORDER'; return; }
				$enc = new \\Hezarfen\\Inc\\Data\\PostMetaEncryption();
				$order->update_meta_data( '${ META_KEY }', $enc->encrypt( '${ PLAINTEXT_TC }' ) );
				$order->save();
				echo 'OK';
			`,
		] );

		// Read the raw row out of the HPOS orders meta table so we
		// inspect what's literally stored on disk, not what the WC
		// data store hands back through `get_meta()` (which doesn't
		// auto-decrypt anyway, but going to the table makes the
		// invariant explicit).
		const rawCiphertext = wp( [
			'eval',
			`
				global $wpdb;
				$row = $wpdb->get_var( $wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = %d AND meta_key = %s LIMIT 1",
					${ orderId },
					'${ META_KEY }'
				) );
				echo $row !== null ? $row : 'NULL';
			`,
		] ).trim();

		expect( rawCiphertext ).not.toBe( 'NULL' );
		expect( rawCiphertext ).not.toBe( PLAINTEXT_TC );
		expect( rawCiphertext ).not.toContain( PLAINTEXT_TC );
		// PostMetaEncryption round-trips through `openssl_encrypt` and
		// base64-encodes the IV+ciphertext blob, so we expect a non-
		// empty string clearly longer than the 11-digit input.
		expect( rawCiphertext.length ).toBeGreaterThan( PLAINTEXT_TC.length );

		// And `PostMetaEncryption::decrypt` must roundtrip back to the
		// exact plaintext we wrote in.
		const decrypted = wp( [
			'eval',
			`
				global $wpdb;
				$row = $wpdb->get_var( $wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = %d AND meta_key = %s LIMIT 1",
					${ orderId },
					'${ META_KEY }'
				) );
				echo ( new \\Hezarfen\\Inc\\Data\\PostMetaEncryption() )->decrypt( $row );
			`,
		] ).trim();
		expect( decrypted ).toBe( PLAINTEXT_TC );
	} );

	test( 'encryption key health_check + tester text Istanbul olarak çözülüyor', async () => {
		// global-setup seeds the encryption key + tester text via
		// `seed-encryption-tester.php`. If health_check ever returns
		// false (missing constant in wp-config.php, missing option), or
		// the tester text fails to decrypt, the entire encryption
		// feature is silently disabled — TC fields stop rendering and
		// existing ciphertexts become unreadable. This is the canary.
		const status = wp( [
			'eval',
			`
				$enc = new \\Hezarfen\\Inc\\Data\\PostMetaEncryption();
				echo $enc->health_check() ? 'HEALTHY' : 'BROKEN';
			`,
		] ).trim();
		expect( status ).toBe( 'HEALTHY' );

		const tester = wp( [
			'eval',
			`
				$enc = new \\Hezarfen\\Inc\\Data\\PostMetaEncryption();
				echo $enc->test_the_encryption_key() ? 'OK' : 'FAIL';
			`,
		] ).trim();
		expect( tester ).toBe( 'OK' );
	} );
} );
