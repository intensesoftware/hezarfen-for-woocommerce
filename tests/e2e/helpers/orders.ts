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
	customerId?: string;
} = {} ): string {
	const status = opts.status ?? 'on-hold';
	const email = opts.customerEmail ?? 'e2e-buyer@example.test';
	const customerId = opts.customerId ?? '0';

	const out = wp( [
		'eval',
		`
			$product = get_page_by_path( 'hezarfen-e2e-product', OBJECT, 'product' );
			if ( ! $product ) { echo 'ERR_NO_PRODUCT'; return; }
			$order = wc_create_order( array( 'status' => '${ status }', 'customer_id' => ${ customerId } ) );
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

/**
 * Save manual-shipment-tracking data on an order using the same
 * Helper::new_order_shipment_data API the admin metabox calls. We
 * skip the AJAX layer because the metabox UI is hepsijet-aware and
 * involves several radios + nonces — testing storage + display is
 * the high-value bit.
 */
export function seedShipmentTracking( opts: {
	orderId: string;
	courierId: string;
	trackingNum: string;
} ): void {
	wp( [
		'eval',
		`
			$order = wc_get_order( ${ opts.orderId } );
			if ( ! $order ) { echo 'ERR_NO_ORDER'; return; }
			\\Hezarfen\\ManualShipmentTracking\\Helper::new_order_shipment_data(
				$order,
				null,
				'${ opts.courierId }',
				'${ opts.trackingNum }'
			);
			echo 'OK';
		`,
	] );
}

export function deleteOrder( orderId: string ): void {
	wp( [ 'post', 'delete', orderId, '--force' ], { allowFailure: true } );
}
