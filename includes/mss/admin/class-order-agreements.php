<?php
/**
 * Order Agreements Display for Admin
 * 
 * @package Hezarfen\Inc\MSS
 */

namespace Hezarfen\Inc\MSS\Admin;

defined( 'ABSPATH' ) || exit();

/**
 * Order_Agreements class
 */
class Order_Agreements {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_agreements_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
	}
	
	
	/**
	 * Add meta box for agreements
	 *
	 * @param string $post_type Post type.
	 * @param mixed  $post_or_order Post or order object.
	 */
	public function add_agreements_meta_box( $post_type, $post_or_order ) {
		$screen = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
			
		if ( $post_type === 'shop_order' || ( is_object( $post_or_order ) && is_a( $post_or_order, 'WC_Order' ) ) ) {
			add_meta_box(
				'hezarfen-customer-agreements',
				__( 'Customer Agreements', 'hezarfen-for-woocommerce' ),
				array( $this, 'render_agreements_meta_box' ),
				$screen,
				'normal',
				'default'
			);
		}
	}
	
	/**
	 * Render agreements meta box
	 *
	 * @param mixed $post_or_order Post or order object.
	 */
	public function render_agreements_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		
		if ( ! $order ) {
			return;
		}
		
		$order_id = $order->get_id();
		$agreements = $this->get_saved_agreements( $order_id );
		
		if ( empty( $agreements ) ) {
			?>
			<div style="text-align: center; padding: 20px; color: #666;">
				<p><?php esc_html_e( 'No customer agreements found for this order.', 'hezarfen-for-woocommerce' ); ?></p>
				<p style="font-size: 11px; margin-top: 15px;">
					<em><?php esc_html_e( 'Powered by', 'hezarfen-for-woocommerce' ); ?> <strong>Hezarfen for WooCommerce</strong></em>
				</p>
			</div>
			<?php
			return;
		}
		
		?>
		<div class="hezarfen-agreements-meta-box">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Agreement Name', 'hezarfen-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Date Accepted', 'hezarfen-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'IP Address', 'hezarfen-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'hezarfen-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $agreements as $agreement ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $agreement->contract_name ); ?></strong></td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $agreement->created_at ) ) ); ?></td>
							<td><?php echo esc_html( $agreement->ip_address ); ?></td>
							<td>
								<button type="button" class="button button-small view-agreement-modal" data-agreement-id="<?php echo esc_attr( $agreement->id ); ?>" data-agreement-name="<?php echo esc_attr( $agreement->contract_name ); ?>">
									<?php esc_html_e( 'View', 'hezarfen-for-woocommerce' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<div style="text-align: right; margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
				<small style="color: #666; font-style: italic;">
					<?php esc_html_e( 'Powered by', 'hezarfen-for-woocommerce' ); ?> <strong>Hezarfen for WooCommerce</strong>
				</small>
			</div>
		</div>
		
		<!-- Single Modal with Tabs for all agreements -->
		<div id="hezarfen-agreements-modal" class="hezarfen-modal" style="display: none;">
			<div class="hezarfen-modal-overlay"></div>
			<div class="hezarfen-modal-content" style="max-width: 900px; max-height: 85vh;">
				<div class="hezarfen-modal-header">
					<h2><?php esc_html_e( 'Customer Agreements', 'hezarfen-for-woocommerce' ); ?></h2>
					<button type="button" class="hezarfen-modal-close">&times;</button>
				</div>
				<div class="hezarfen-modal-body">
					<div class="hezarfen-agreement-tabs">
						<div class="hezarfen-tab-nav">
							<?php foreach ( $agreements as $index => $agreement ) : ?>
								<button type="button" class="hezarfen-tab-button <?php echo $index === 0 ? 'active' : ''; ?>" data-tab="tab-<?php echo esc_attr( $agreement->id ); ?>">
									<?php echo esc_html( $agreement->contract_name ); ?>
								</button>
							<?php endforeach; ?>
						</div>
						<div class="hezarfen-tab-content">
							<?php foreach ( $agreements as $index => $agreement ) : ?>
								<div class="hezarfen-tab-pane <?php echo $index === 0 ? 'active' : ''; ?>" id="tab-<?php echo esc_attr( $agreement->id ); ?>">
									<div class="agreement-meta" style="background: #f9f9f9; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
										<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
											<div>
												<strong><?php esc_html_e( 'Accepted:', 'hezarfen-for-woocommerce' ); ?></strong> 
												<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $agreement->created_at ) ) ); ?>
											</div>
											<div>
												<strong><?php esc_html_e( 'IP Address:', 'hezarfen-for-woocommerce' ); ?></strong> 
												<?php echo esc_html( $agreement->ip_address ); ?>
											</div>
										</div>
										<?php if ( ! empty( $agreement->user_agent ) ) : ?>
											<div style="margin-top: 8px; font-size: 11px; color: #666;">
												<strong><?php esc_html_e( 'User Agent:', 'hezarfen-for-woocommerce' ); ?></strong> 
												<?php echo esc_html( $agreement->user_agent ); ?>
											</div>
										<?php endif; ?>
									</div>
									<div class="agreement-content" style="max-height: 400px; overflow-y: auto; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
										<?php echo wp_kses_post( $agreement->contract_content ); ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			var agreements = <?php echo wp_json_encode( $agreements ); ?>;
			
			// Open modal when any View button is clicked
			$('.view-agreement-modal').on('click', function() {
				var agreementId = $(this).data('agreement-id');
				
				// Show the modal
				$('#hezarfen-agreements-modal').show();
				
				// Switch to the correct tab if not already active
				if (!$('#tab-' + agreementId).hasClass('active')) {
					$('.hezarfen-tab-pane').removeClass('active');
					$('.hezarfen-tab-button').removeClass('active');
					
					$('#tab-' + agreementId).addClass('active');
					$('.hezarfen-tab-button[data-tab="tab-' + agreementId + '"]').addClass('active');
				}
			});
			
			// Tab switching functionality
			$('.hezarfen-tab-button').on('click', function() {
				var targetTab = $(this).data('tab');
				
				// Remove active class from all tabs and buttons
				$('.hezarfen-tab-pane').removeClass('active');
				$('.hezarfen-tab-button').removeClass('active');
				
				// Add active class to clicked button and corresponding tab
				$(this).addClass('active');
				$('#' + targetTab).addClass('active');
			});
			
			// Close modal
			$('.hezarfen-modal-close, .hezarfen-modal-overlay').on('click', function() {
				$('#hezarfen-agreements-modal').hide();
			});
			
			// ESC key to close modal
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape') {
					$('#hezarfen-agreements-modal').hide();
				}
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Get saved agreements for an order
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	private function get_saved_agreements( $order_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'hezarfen_contracts';
		
		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
			return array();
		}
		
		$agreements = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE order_id = %d ORDER BY created_at DESC",
			$order_id
		) );
		
		return $agreements ? $agreements : array();
	}
	
	/**
	 * Enqueue admin styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_styles( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'woocommerce_page_wc-orders' ) ) ) {
			return;
		}
		
		// Add inline styles for the modal and tabs
		wp_add_inline_style( 'wp-admin', '
			.hezarfen-modal {
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				z-index: 100000;
			}
			.hezarfen-modal-overlay {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				background: rgba(0, 0, 0, 0.7);
			}
			.hezarfen-modal-content {
				position: absolute;
				top: 50%;
				left: 50%;
				transform: translate(-50%, -50%);
				background: #fff;
				border-radius: 4px;
				box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
				overflow: hidden;
			}
			.hezarfen-modal-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 20px;
				border-bottom: 1px solid #ddd;
				background: #f9f9f9;
			}
			.hezarfen-modal-header h2 {
				margin: 0;
			}
			.hezarfen-modal-close {
				background: none;
				border: none;
				font-size: 24px;
				cursor: pointer;
				padding: 0;
				width: 30px;
				height: 30px;
				display: flex;
				align-items: center;
				justify-content: center;
			}
			.hezarfen-modal-close:hover {
				background: #ddd;
				border-radius: 50%;
			}
			.hezarfen-modal-body {
				padding: 0;
			}
			.hezarfen-agreement-tabs {
				display: flex;
				flex-direction: column;
				height: 100%;
			}
			.hezarfen-tab-nav {
				display: flex;
				background: #f1f1f1;
				border-bottom: 1px solid #ddd;
				overflow-x: auto;
				flex-shrink: 0;
			}
			.hezarfen-tab-button {
				background: none;
				border: none;
				padding: 12px 20px;
				cursor: pointer;
				white-space: nowrap;
				border-bottom: 3px solid transparent;
				font-size: 13px;
				color: #555;
				transition: all 0.2s ease;
			}
			.hezarfen-tab-button:hover {
				background: #e0e0e0;
				color: #333;
			}
			.hezarfen-tab-button.active {
				background: #fff;
				color: #0073aa;
				border-bottom-color: #0073aa;
				font-weight: 600;
			}
			.hezarfen-tab-content {
				flex: 1;
				overflow: hidden;
			}
			.hezarfen-tab-pane {
				display: none;
				padding: 20px;
				height: 100%;
				overflow-y: auto;
			}
			.hezarfen-tab-pane.active {
				display: block;
			}
			.hezarfen-tab-pane .agreement-meta {
				margin-bottom: 15px;
			}
			.hezarfen-tab-pane .agreement-content {
				line-height: 1.6;
			}
			@media (max-width: 768px) {
				.hezarfen-modal-content {
					width: 95%;
					height: 90%;
					max-width: none;
					max-height: none;
				}
				.hezarfen-tab-nav {
					flex-wrap: wrap;
				}
				.hezarfen-tab-button {
					flex: 1;
					min-width: auto;
				}
			}
		' );
	}
}