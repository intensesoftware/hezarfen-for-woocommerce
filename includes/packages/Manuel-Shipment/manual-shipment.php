<?php
function register_shipped_order_status() {
    register_post_status( 'hez_shipped', array(
        'label'                     => 'Kargolandı',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Kargolandı <span class="count">(%s)</span>', 'Kargolandı <span class="count">(%s)</span>' )
    ) );
}
add_action( 'init', 'register_shipped_order_status' );


add_filter( 'wc_order_statuses', 'hez_order_status');
function hez_order_status( $order_statuses ) {
    $order_statuses['hez_shipped'] = _x( 'Kargolandı', 'Order status', 'hezarfen-for-woocommerce' ); 
    return $order_statuses;
}

