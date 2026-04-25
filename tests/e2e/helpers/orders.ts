import { wp } from './wp-cli';

/**
 * Seed a WooCommerce order with the e2e product, a TR billing address,
 * and a chosen status. Returns the order id. Uses `wp eval` rather than
 * `wp wc shop_order create` because the latter doesn't expose
 * status/meta/line-item flags consistently across versions.
 */
export function seedTestOrder( opts: {
	status?: string;
	customerEmail?: string;
} = {} ): string {
	const status = opts.status ?? 'on-hold';
	const email = opts.customerEmail ?? 'e2e-buyer@example.test';

	const out = wp( [
		'eval',
		`
			$product = get_page_by_path( 'hezarfen-e2e-product', OBJECT, 'product' );
			if ( ! $product ) { echo 'ERR_NO_PRODUCT'; return; }
			$order = wc_create_order( array( 'status' => '${ status }' ) );
			$order->add_product( wc_get_product( $product->ID ), 1 );
			$order->set_billing_first_name( 'Ada' );
			$order->set_billing_last_name( 'Lovelace' );
			$order->set_billing_email( '${ email }' );
			$order->set_billing_phone( '5551112233' );
			$order->set_billing_country( 'TR' );
			$order->set_billing_state( 'TR06' );
			$order->set_billing_city( 'Çankaya' );
			$order->set_billing_address_1( '100.Yıl Mah' );
			$order->set_billing_address_2( 'Ada Sk. No:1 D:2' );
			$order->set_billing_postcode( '06520' );
			$order->set_payment_method( 'cod' );
			$order->set_payment_method_title( 'Cash on delivery (e2e)' );
			$order->calculate_totals();
			$order->save();
			echo $order->get_id();
		`,
	] ).trim();

	if ( ! out || out.startsWith( 'ERR_' ) || ! /^\d+$/.test( out ) ) {
		throw new Error( `seedTestOrder failed: ${ out }` );
	}
	return out;
}

export function deleteOrder( orderId: string ): void {
	wp( [ 'post', 'delete', orderId, '--force' ], { allowFailure: true } );
}
