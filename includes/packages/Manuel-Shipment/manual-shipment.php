<?php

defined('ABSPATH') || exit();

class Hez_Shipping_Tracking
{
	public $shipping_carriers;
	public function __construct()
	{
		// Shipping Carriers Initialization

		$this->shipping_carriers=[
							'TR_YURTICI' => 
								[
								'name'=>'Yurtiçi Kargo',
								'logo'=>'http://placehold.it/120x120&text=image2',
								'tracking_link'=>'http://www.fedex.com/Tracking?tracknumbers=',
								'is_available'=>true
								],
							'TR_MNG' => [
								'name'=>'MNG Kargo',
								'logo'=>'http://placehold.it/120x120&text=image3',
								'tracking_link'=>'http://www.ups.com/WebTracking/track?loc=en_US/',
								'is_available'=>true
								],
							'TR_ARAS' => [
								'name'=>'Aras Kargo',
								'logo'=>'http://placehold.it/120x120&text=image4',
								'tracking_link'=>'http://www.usps.com/shipping/trackandconfirm/',
								'is_available'=>true
								],
							'TR_UPS' => [
								'name'=>'UPS Kargo',
								'logo'=>'http://placehold.it/120x120&text=image1',
								'tracking_link'=>'http://www.usps.com/shipping/trackandconfirm/',
								'is_available'=>true
								],
							'TR_SURAT' => [
								'name'=>'Sürat Kargo',
								'logo'=>'http://placehold.it/120x120&text=image2',
								'tracking_link'=>'http://www.usps.com/shipping/trackandconfirm/',
								'is_available'=>true
								],
							'TR_HOROZ' => [
								'name'=>'Horoz Loijistik',
								'logo'=>'http://placehold.it/120x120&text=image3',
								'tracking_link'=>'http://www.usps.com/shipping/trackandconfirm/',
								'is_available'=>false
								],
							'TR_KARGO_TURK' => [
								'name'=>'Kargo Türk',
								'logo'=>'http://placehold.it/120x120&text=image4',
								'tracking_link'=>'http://www.usps.com/shipping/trackandconfirm/',
								'is_available'=>false
								],
							'DHL' => [
								'name'=>'DHL',
								'logo'=>'http://placehold.it/120x120&text=image1',
								'tracking_link'=>'http://www.usps.com/shipping/trackandconfirm/',
								'is_available'=>false
								],
							'TNT' => [
								'name'=>'TNT',
								'logo'=>'http://placehold.it/120x120&text=image2',
								'tracking_link'=>'http://www.usps.com/shipping/trackandconfirm/',
								'is_available'=>false
								]
							];

		$this->shipping_carriers = $this->get_shipping_providers();

		// Action to register custom order status
		add_action( 'init', [$this,'hez_register_shipped_order_status'] );

		// Add custom status to order status list
		add_filter( 'wc_order_statuses', [$this,'hez_add_shipped_to_order_statuses'] );

		// Add Custom fields for woocommerce order
		add_action( "add_meta_boxes", [$this,"hez_add_meta_boxes"] );
		
		// Ajax handler function
		add_action( 'wp_ajax_hezarfen_shipping_data_action', [$this,'hezarfen_shipping_data_action'] );

		// Add Custom fields for woocommerce order
		add_action( 'wp_ajax_nopriv_hezarfen_shipping_data_action', [$this,'hezarfen_shipping_data_action'] );
		
		// Show Shipping fields in my account
		add_action( 'woocommerce_order_details_after_order_table', [$this,'hezarfen_show_shipping_data'] );
		
	}

	private function get_shipping_providers(){
		return apply_filters( 'hezarfen_supported_shipping_carriers', $this->shipping_carriers );
	}

	// Function for register custom order status
	public function hez_register_shipped_order_status() {
	    register_post_status( 'wc-hez_shipped', array(
	        'label'                     => 'Kargolandı',
	        'public'                    => true,
	        'exclude_from_search'       => false,
	        'show_in_admin_all_list'    => true,
	        'show_in_admin_status_list' => true,
	        'label_count'               => _n_noop( 'Kargolandı <span class="count">(%s)</span>', 'Kargolandı <span class="count">(%s)</span>' )
	    ) );
	}

	// Add custom status to order status list
	public function hez_add_shipped_to_order_statuses( $order_statuses ) {
	    $new_order_statuses = array();
	    foreach ( $order_statuses as $key => $status ) {
	        $new_order_statuses[ $key ] = $status;
	        if ( 'wc-processing' === $key ) {
	            $new_order_statuses['wc-hez_shipped'] = 'Kargolandı';
	        }
	    }
	    return $new_order_statuses;
	}
	// Add Custom fields for woocommerce order
	public function hez_add_meta_boxes()
	{
		if(isset($_GET['post'])){
			$order_id=$_GET['post'];
		    add_meta_box(
		        "woocommerce-order-my-custom",
		        __( "Shipping Options" ),
		        [$this,"hez_order_shipping_options"],
		        "shop_order",
		        "side",
		        "default"
		    );		
		}		
	}
	// Add Custom fields for woocommerce order
	public function hez_order_shipping_options(){
	    global $woocommerce,$wpdb;
	    $shipping_carriers=$this->shipping_carriers;
	    $order_id=$_GET['post'];
	    $hez_ship_track_no= get_post_meta( $order_id, 'hez_ship_track_no', true );
	    $hez_ship_carrier= get_post_meta( $order_id, 'hez_ship_carrier', true );
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
			.error_input{
				border: 1px solid #d63638 !important;
			}
		</style>
		<form action="" method="post" >
		    <div class="wp-clearfix">
			    <label>Shipping Carrier</label><br>
			    <select name="hez_ship_carrier" class="shipping_fields hez_ship_carrier">
			    	<option value="">Select Shippiing Carrier</option>
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
	            var order_id=<?php echo $order_id; ?>;
	            $('.update-shipping').on('click',function(){
	                $('.loader').fadeIn();
	                $('.update-shipping').attr('disabled','disabled');
	                var error=0;
	                var hez_ship_carrier=$('.hez_ship_carrier').val();
	                var hez_ship_track_no=$('.hez_ship_track_no').val();
	                if(hez_ship_carrier==''){
	                	error++;
	                	$('.hez_ship_carrier').addClass('error_input');
	                }
	                if(hez_ship_track_no==''){
	                	error++;
	                	$('.hez_ship_track_no').addClass('error_input');
	                }
	                if(error==0){
		                $.ajax({
		                     type : "POST",
		                     dataType : "json",
		                     url : "<?php echo home_url();?>/wp-admin/admin-ajax.php",
		                     data : {action: "hezarfen_shipping_data_action",id:order_id,hez_ship_carrier:hez_ship_carrier,hez_ship_track_no:hez_ship_track_no},
		                     success: function(response) {
		                           $('.update-shipping').removeAttr('disabled');
		                           $('.hez_ship_track_no').removeClass('error');
		                           $('.hez_ship_carrier').removeClass('error');
		                           location.reload();

		                    }
		                });
		            }else{
		            	$('.update-shipping').removeAttr('disabled');
		            }

	            });
	        });
	    </script>
		<?php
	}

	// Store Custom fields for woocommerce order
	public function hezarfen_shipping_data_action(){
		global $wpdb;
		$order_id=$_POST['id'];
		update_post_meta( $order_id, 'hez_ship_carrier', $_POST['hez_ship_carrier'] );
		update_post_meta( $order_id, 'hez_ship_track_no', $_POST['hez_ship_track_no'] );
		
		$order = wc_get_order( $order_id );

		do_action( 'hezarfen_order_shipped', $order );
		
		if($order){
			$order->set_status('wc-hez_shipped');
			$order->save();
		}
		echo json_encode('success');
		exit();

	}

	// Show Shipping fields in my account
	public function hezarfen_show_shipping_data($order) {		
		$shipping_carriers=$this->shipping_carriers;
		$order_id=$order->get_id();
		$hez_ship_track_no= get_post_meta( $order_id, 'hez_ship_track_no', true );
	    $hez_ship_carrier= get_post_meta( $order_id, 'hez_ship_carrier', true );
	    if($hez_ship_track_no&&$hez_ship_carrier){
		?>
		<style type="text/css">
			.img-logo{
				width: 50px;
			}
			.shipping_carrier_title{
				font-weight: 600;
			}
			a.btn.btn-success {
			    background: #2271b1;
			    border-color: #2271b1;
			    color: #fff;
			    text-decoration: none;
			    display: inline-block;
			    line-height: 2;
			    padding: 0 10px;
			    border-radius: 3px;
			}
		</style>
		<h3>Tracking Details</h3>
		<table class="table">
			<tr>
				<th>Shipping Carrier</th>
				<th>Tracking link</th>
			</tr>
			<tr>
				<td >
					<?php 
					if($shipping_carriers[$hez_ship_carrier]['logo']!=''){?>
						<img src="<?php echo $shipping_carriers[$hez_ship_carrier]['logo']; ?>" class="img-logo">
					<?php 
					}
					 echo '<span class="shipping_carrier_title">'.$shipping_carriers[$hez_ship_carrier]['name'].'</span>'; ?>
				</td>	
				<td>
					<a href="<?php echo $shipping_carriers[$hez_ship_carrier]['tracking_link']."/".$hez_ship_track_no; ?>" class="btn btn-success" target='_blank'>Track</a>
				</td>
			</tr>
			
		</table>
	<?php
		}
	}
}
new Hez_Shipping_Tracking();