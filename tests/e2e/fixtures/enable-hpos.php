<?php
/**
 * One-shot enable HPOS for the e2e wp-env stack. Called via
 * `wp eval-file` from global-setup.ts when running in wp-env mode.
 */
$container = wc_get_container();
$ds = $container->get( \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class );
if ( ! $ds->check_orders_table_exists() ) {
	$ds->create_database_tables();
}

// WC's CustomOrdersTableController guards `pre_update_option_woocommerce_custom_orders_table_enabled`
// against any pending-sync state. There's a documented testing filter
// for this exact scenario — flip it on, write the options, flip off.
add_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );
update_option( 'woocommerce_custom_orders_table_created', 'yes' );
update_option( 'woocommerce_custom_orders_table_enabled', 'yes' );
update_option( 'woocommerce_feature_custom_order_tables_enabled', 'yes' );
update_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' );
remove_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );
echo 'HPOS enabled';
