<?php
function register_shipped_order_status() {
    register_post_status( 'wc-hez_shipped', array(
        'label'                     => 'Kargolandı',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Kargolandı <span class="count">(%s)</span>', 'Kargolandı <span class="count">(%s)</span>' )
    ) );
}
add_action( 'init', 'register_shipped_order_status' );


// Add custom status to order status list
function add_hez_shipped_to_order_statuses( $order_statuses ) {
    $new_order_statuses = array();
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
        if ( 'wc-processing' === $key ) {
            $new_order_statuses['wc-hez_shipped'] = 'Kargolandı';
        }
    }
    return $new_order_statuses;
}
add_filter( 'wc_order_statuses', 'add_hez_shipped_to_order_statuses' );

add_action( "add_meta_boxes", "add_meta_boxes" );

function add_meta_boxes()
{

    $order_id=$_GET['post'];
    add_meta_box(
        "woocommerce-order-my-custom",
        __( "Shipping Options" ),
        "hez_order_shipping_options",
        "shop_order",
        "side",
        "default"
    );

}
function hez_order_shipping_options(){
    global $woocommerce,$wpdb;
    $post_id=$_GET['post'];
    // $ship_by_pdb_id = get_post_meta( $post_id, '_ship_by_pdb', true );
    $order_id=$_GET['post'];
    $hez_ship_track_no= get_post_meta( $post_id, 'hez_ship_track_no', true );
    $hez_ship_carrier= get_post_meta( $post_id, 'hez_ship_carrier', true );
    $order = new WC_Order( $order_id );
	$shipping_carriers=[
						'TR_YURTICI' => 
							[
							'name'=>'Yurtiçi Kargo',
							'logo'=>'add a sample url to here',
							'tracking_link'=>'add a sample url to here',
							'is_available'=>true
							],
						'TR_MNG' => [
							'name'=>'MNG Kargo',
							'logo'=>'add a sample url to here',
							'tracking_link'=>'',
							'is_available'=>true
							],
						'TR_ARAS' => [
							'name'=>'Aras Kargo',
							'logo'=>'add a sample url to here',
							'tracking_link'=>'',
							'is_available'=>true
							],
						'TR_UPS' => [
							'name'=>'UPS Kargo',
							'logo'=>'add a sample url to here',
							'tracking_link'=>'',
							'is_available'=>true
							],
						'TR_SURAT' => [
							'name'=>'Sürat Kargo',
							'logo'=>'add a sample url to here',
							'tracking_link'=>'',
							'is_available'=>true
							],
						'TR_HOROZ' => [
							'name'=>'Horoz Loijistik',
							'logo'=>'add a sample url to here',
							'tracking_link'=>'',
							'is_available'=>false
							],
						'TR_KARGO_TURK' => [
							'name'=>'Kargo Türk',
							'logo'=>'add a sample url to here',
							'tracking_link'=>'',
							'is_available'=>false
							],
						'DHL' => [
							'name'=>'DHL',
							'logo'=>'add a sample url to here',
							'tracking_link'=>'',
							'is_available'=>false
							],
						'TNT' => [
							'name'=>'TNT',
							'logo'=>'add a sample url to here',
							'tracking_link'=>'',
							'is_available'=>false
							]
						];

	?>
	<style type="text/css">
		.shipping_fields{
			width: 100%;
		}
		.update_shipping{
			float: right;
		}
		.wp-clearfix{
			clear: both;
			margin-top: 5px;
		}
		.text-right{
			text-align: right;
		}
	</style>
	<form action="" method="post" >
	    <!-- <input type="text" name=""> -->
	    <div class="wp-clearfix">
		    <label>Shipping Carrier</label><br>
		    <select name="hez_ship_carrier" class="shipping_fields hez_ship_carrier">
		    <?php 
		    foreach($shipping_carriers as $key=>$shipping_carrier)
		    {
		    	if($shipping_carrier['is_available'])
		    		echo "<option value='".$key."' ".($hez_ship_carrier==$key?'selected':'').">".$shipping_carrier['name']."</option>";
		    }
		    ?>
		    </select>
		</div>
		
	    <div class="wp-clearfix">
	    	<label>Shipping Tracking Code</label><br>
	    	<input type="text" name="hez_ship_track_no"  value="<?php echo $hez_ship_track_no; ?>" class="shipping_fields hez_ship_track_no">	
	    </div>
	    <br>
	    <div class="text-right">
	    	<button type="button" name="update_shipping" class="button button-primary update-shipping">Update Shipping</button>	
	    </div>
	    
	</form>
	<script>
        jQuery(document).ready(function($) {
            var order_id=<?php echo $post_id; ?>;
            $('.update-shipping').on('click',function(){
                $('.loader').fadeIn();
                $('.update-shipping').attr('disabled','disabled');
                var hez_ship_carrier=$('.hez_ship_carrier').val();
                var hez_ship_track_no=$('.hez_ship_track_no').val();

                $.ajax({
                     type : "POST",
                     dataType : "json",
                     url : "<?php echo home_url();?>/wp-admin/admin-ajax.php",
                     data : {action: "hezarfen_shipping_data_action",id:order_id,hez_ship_carrier:hez_ship_carrier,hez_ship_track_no:hez_ship_track_no},
                     success: function(response) {
                           // 
                           // response=JSON.parse(response);
                           console.log(response);
                           $('.update-shipping').removeAttr('disabled');
                    }
                });

            });
        });
    </script>
	<?php
}

// Ajax handler function
add_action( 'wp_ajax_hezarfen_shipping_data_action', 'hezarfen_shipping_data_action' );
add_action( 'wp_ajax_nopriv_hezarfen_shipping_data_action', 'hezarfen_shipping_data_action' );

function hezarfen_shipping_data_action(){
	global $wpdb;
	$order_id=$_POST['id'];
	update_post_meta( $order_id, 'hez_ship_carrier', $_POST['hez_ship_carrier'] );
	update_post_meta( $order_id, 'hez_ship_track_no', $_POST['hez_ship_track_no'] );
	
	$order = wc_get_order( $order_id );

	do_action( 'hezarfen_order_shipped', $order );
	
	if($order){
	   // $order->update_status( 'hez_shipped', 'hez_shipped', true );
		// $order = wc_get_order($order_id);
		$order->set_status('wc-hez_shipped');
		$order->save();
	}
	echo json_encode('success');
	exit();

}
