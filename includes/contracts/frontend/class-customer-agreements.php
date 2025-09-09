<?php
/**
 * Customer Agreements Frontend Display
 * 
 * Handles agreement display on thank you page and my account order details
 * 
 * @package Hezarfen\Inc\Contracts\Frontend
 */

namespace Hezarfen\Inc\Contracts\Frontend;

defined( 'ABSPATH' ) || exit();

/**
 * Customer_Agreements class
 */
class Customer_Agreements {
	
	/**
	 * Initialize frontend agreement display hooks
	 */
	public static function init() {
		// Thank you page integration
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'display_on_thankyou_page' ), 20 );
		
		// My Account order details integration (only on view-order endpoint, not thank you page)
		add_action( 'woocommerce_order_details_after_customer_details', array( __CLASS__, 'display_on_order_details' ), 10 );
		
		// Enqueue scripts and styles for frontend
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
	}
	
	/**
	 * Display agreements button on thank you page
	 *
	 * @param int $order_id Order ID.
	 */
	public static function display_on_thankyou_page( $order_id ) {
		if ( ! $order_id ) {
			return;
		}
		
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		
		$agreements = self::get_order_agreements( $order_id );
		if ( empty( $agreements ) ) {
			return;
		}
		
		?>
		<div class="woocommerce-customer-details">
			<section class="woocommerce-customer-agreements">
				<h2 class="woocommerce-column__title"><?php esc_html_e( 'Your Agreements', 'hezarfen-for-woocommerce' ); ?></h2>
				
				<div class="hezarfen-agreements-summary">
					<p><?php esc_html_e( 'You accepted the following agreements for this order:', 'hezarfen-for-woocommerce' ); ?></p>
					
					<ul class="agreements-list" style="list-style: none; padding: 0; margin: 15px 0;">
						<?php foreach ( $agreements as $agreement ) : ?>
							<li style="padding: 8px 0; border-bottom: 1px solid #eee;">
								<div style="display: flex; justify-content: space-between; align-items: center;">
									<span class="agreement-name" style="font-weight: 500;"><?php echo esc_html( $agreement->contract_name ); ?></span>
									<small style="color: #666;">
										<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $agreement->created_at ) ) ); ?>
									</small>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
					
					<p>
						<button type="button" class="button hezarfen-view-agreements-btn" data-order-id="<?php echo esc_attr( $order_id ); ?>">
							<?php esc_html_e( 'View Agreements', 'hezarfen-for-woocommerce' ); ?>
						</button>
					</p>
				</div>
			</section>
		</div>
		<?php
		
		// Add the modal and scripts
		self::render_agreements_modal( $agreements );
	}
	
	/**
	 * Display agreements button on my account order details
	 *
	 * @param \WC_Order $order Order object.
	 */
	public static function display_on_order_details( $order ) {
		// Only display on view-order endpoint, not on thank you page
		if ( ! is_wc_endpoint_url( 'view-order' ) ) {
			return;
		}
		
		$order_id = $order->get_id();
		$agreements = self::get_order_agreements( $order_id );
		
		if ( empty( $agreements ) ) {
			?>
			<div class="woocommerce-customer-details">
				<section class="woocommerce-customer-agreements">
					<h2 class="woocommerce-column__title"><?php esc_html_e( 'Agreements', 'hezarfen-for-woocommerce' ); ?></h2>
					<p style="color: #666; font-style: italic;">
						<?php esc_html_e( 'No agreements were saved for this order.', 'hezarfen-for-woocommerce' ); ?>
					</p>
				</section>
			</div>
			<?php
			return;
		}
		
		?>
		<div class="woocommerce-customer-details">
			<section class="woocommerce-customer-agreements">
				<h2 class="woocommerce-column__title"><?php esc_html_e( 'Your Agreements', 'hezarfen-for-woocommerce' ); ?></h2>
				
				<div class="hezarfen-agreements-summary">
					<p><?php esc_html_e( 'You accepted the following agreements for this order:', 'hezarfen-for-woocommerce' ); ?></p>
					
					<ul class="agreements-list" style="list-style: none; padding: 0; margin: 15px 0;">
						<?php foreach ( $agreements as $agreement ) : ?>
							<li style="padding: 8px 0; border-bottom: 1px solid #eee;">
								<div style="display: flex; justify-content: space-between; align-items: center;">
									<span class="agreement-name" style="font-weight: 500;"><?php echo esc_html( $agreement->contract_name ); ?></span>
									<small style="color: #666;">
										<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $agreement->created_at ) ) ); ?>
									</small>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
					
					<p>
						<button type="button" class="button hezarfen-view-agreements-btn" data-order-id="<?php echo esc_attr( $order_id ); ?>">
							<?php esc_html_e( 'View Agreements', 'hezarfen-for-woocommerce' ); ?>
						</button>
					</p>
				</div>
			</section>
		</div>
		<?php
		
		// Add the modal and scripts
		self::render_agreements_modal( $agreements );
	}
	
	/**
	 * Render agreements modal with tabs
	 *
	 * @param array $agreements Order agreements.
	 */
	private static function render_agreements_modal( $agreements ) {
		if ( empty( $agreements ) ) {
			return;
		}
		?>
		
		<!-- Customer Agreements Modal -->
		<div id="hezarfen-customer-agreements-modal" class="hezarfen-customer-modal" style="display: none;">
			<div class="hezarfen-modal-overlay"></div>
			<div class="hezarfen-modal-content">
				<div class="hezarfen-modal-header">
					<h2><?php esc_html_e( 'Your Agreements', 'hezarfen-for-woocommerce' ); ?></h2>
					<button type="button" class="hezarfen-modal-close">&times;</button>
				</div>
				<div class="hezarfen-modal-body">
					<div class="hezarfen-agreement-tabs">
						<div class="hezarfen-tab-nav">
							<?php foreach ( $agreements as $index => $agreement ) : ?>
								<button type="button" class="hezarfen-tab-button <?php echo $index === 0 ? 'active' : ''; ?>" data-tab="customer-tab-<?php echo esc_attr( $agreement->id ); ?>">
									<?php echo esc_html( $agreement->contract_name ); ?>
								</button>
							<?php endforeach; ?>
						</div>
						<div class="hezarfen-tab-content">
							<?php foreach ( $agreements as $index => $agreement ) : ?>
								<div class="hezarfen-tab-pane <?php echo $index === 0 ? 'active' : ''; ?>" id="customer-tab-<?php echo esc_attr( $agreement->id ); ?>">
									<div class="agreement-meta" style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
										<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
											<div>
												<strong><?php esc_html_e( 'Agreement Accepted:', 'hezarfen-for-woocommerce' ); ?></strong><br>
												<span style="color: #666;"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $agreement->created_at ) ) ); ?></span>
											</div>
											<div>
												<strong><?php esc_html_e( 'IP Address:', 'hezarfen-for-woocommerce' ); ?></strong><br>
												<span style="color: #666; font-family: monospace;"><?php echo esc_html( $agreement->ip_address ); ?></span>
											</div>
										</div>
									</div>
									<div class="agreement-content" style="line-height: 1.6; max-height: 400px; overflow-y: auto; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
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
			// Open modal when View Agreements button is clicked
			$('.hezarfen-view-agreements-btn').on('click', function() {
				var modal = $('#hezarfen-customer-agreements-modal');
				modal.show().addClass('show');
				$('body').addClass('hezarfen-modal-open');
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
			
			// Close modal function
			function closeModal() {
				var modal = $('#hezarfen-customer-agreements-modal');
				modal.removeClass('show');
				setTimeout(function() {
					modal.hide();
				}, 200);
				$('body').removeClass('hezarfen-modal-open');
			}
			
			// Close modal events
			$('.hezarfen-modal-close, .hezarfen-modal-overlay').on('click', closeModal);
			
			// ESC key to close modal
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape') {
					closeModal();
				}
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Get agreements for an order
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	private static function get_order_agreements( $order_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'hezarfen_contracts';
		
		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
			return array();
		}
		
		$agreements = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE order_id = %d ORDER BY created_at ASC",
			$order_id
		) );
		
		return $agreements ? $agreements : array();
	}
	
	/**
	 * Enqueue frontend assets
	 */
	public static function enqueue_frontend_assets() {
		// Only enqueue on relevant pages
		if ( ! ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'view-order' ) || is_account_page() || is_checkout() ) ) {
			return;
		}
		
		// Enqueue the CSS file
		wp_enqueue_style(
			'hezarfen-customer-agreements',
			plugins_url( 'assets/contracts/css/customer-agreements.css', WC_HEZARFEN_FILE ),
			array(),
			WC_HEZARFEN_VERSION
		);
	}
}