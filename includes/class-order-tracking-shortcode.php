<?php
/**
 * Order Tracking Shortcode Class
 * 
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Order Tracking Shortcode Class
 * 
 * Provides an elegant, minimal order tracking page via shortcode [hezarfen_order_tracking]
 */
class Order_Tracking_Shortcode {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_shortcode( 'hezarfen_order_tracking', array( $this, 'render_tracking_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_hezarfen_track_order', array( $this, 'ajax_track_order' ) );
		add_action( 'wp_ajax_nopriv_hezarfen_track_order', array( $this, 'ajax_track_order' ) );
	}

	/**
	 * Enqueue CSS and JS assets
	 */
	public function enqueue_assets() {
		// Only enqueue on pages that contain our shortcode
		if ( $this->has_shortcode_on_page() ) {
			wp_enqueue_style(
				'hezarfen-order-tracking',
				plugins_url( 'assets/css/order-tracking.css', WC_HEZARFEN_FILE ),
				array(),
				WC_HEZARFEN_VERSION
			);

			wp_enqueue_script(
				'hezarfen-order-tracking',
				plugins_url( 'assets/js/order-tracking.js', WC_HEZARFEN_FILE ),
				array( 'jquery' ),
				WC_HEZARFEN_VERSION,
				true
			);

			wp_localize_script(
				'hezarfen-order-tracking',
				'hezarfen_tracking_ajax',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'hezarfen_track_order' ),
					'strings'  => array(
						'searching'       => __( 'Searching for your order...', 'hezarfen-for-woocommerce' ),
						'order_not_found' => __( 'Order not found. Please check your order number and email address.', 'hezarfen-for-woocommerce' ),
						'error'          => __( 'An error occurred. Please try again.', 'hezarfen-for-woocommerce' ),
					)
				)
			);
		}
	}

	/**
	 * Check if the current page has our shortcode
	 */
	private function has_shortcode_on_page() {
		global $post;
		return $post && has_shortcode( $post->post_content, 'hezarfen_order_tracking' );
	}

	/**
	 * Render the tracking page
	 * 
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function render_tracking_page( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title'       => __( 'Track Your Order', 'hezarfen-for-woocommerce' ),
				'description' => __( 'Enter your order number and email address to track your shipment.', 'hezarfen-for-woocommerce' ),
			),
			$atts,
			'hezarfen_order_tracking'
		);

		ob_start();
		?>
		<div class="hezarfen-tracking-container">
			<div class="hezarfen-tracking-card">
				<div class="hezarfen-tracking-header">
					<h2 class="hezarfen-tracking-title"><?php echo esc_html( $atts['title'] ); ?></h2>
					<p class="hezarfen-tracking-description"><?php echo esc_html( $atts['description'] ); ?></p>
				</div>

				<form class="hezarfen-tracking-form" id="hezarfen-tracking-form">
					<div class="hezarfen-form-group">
						<label for="order_number" class="hezarfen-form-label">
							<?php esc_html_e( 'Order Number', 'hezarfen-for-woocommerce' ); ?>
						</label>
						<input 
							type="text" 
							id="order_number" 
							name="order_number" 
							class="hezarfen-form-input" 
							placeholder="<?php esc_attr_e( 'e.g., #12345', 'hezarfen-for-woocommerce' ); ?>"
							required
						>
					</div>

					<div class="hezarfen-form-group">
						<label for="billing_email" class="hezarfen-form-label">
							<?php esc_html_e( 'Email Address', 'hezarfen-for-woocommerce' ); ?>
						</label>
						<input 
							type="email" 
							id="billing_email" 
							name="billing_email" 
							class="hezarfen-form-input" 
							placeholder="<?php esc_attr_e( 'your@email.com', 'hezarfen-for-woocommerce' ); ?>"
							required
						>
					</div>

					<button type="submit" class="hezarfen-tracking-button">
						<span class="hezarfen-button-text"><?php esc_html_e( 'Track Order', 'hezarfen-for-woocommerce' ); ?></span>
						<span class="hezarfen-button-spinner" style="display: none;">
							<svg class="hezarfen-spinner" viewBox="0 0 24 24">
								<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="32" stroke-dashoffset="32">
									<animate attributeName="stroke-dasharray" dur="2s" values="0 32;16 16;0 32;0 32" repeatCount="indefinite"/>
									<animate attributeName="stroke-dashoffset" dur="2s" values="0;-16;-32;-32" repeatCount="indefinite"/>
								</circle>
							</svg>
						</span>
					</button>
				</form>

				<div class="hezarfen-tracking-results" id="hezarfen-tracking-results" style="display: none;">
					<!-- Results will be loaded here via AJAX -->
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX handler for order tracking
	 */
	public function ajax_track_order() {
		try {
			// Verify nonce
			if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'hezarfen_track_order' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'hezarfen-for-woocommerce' ) ) );
			}

			// Validate required fields
			if ( empty( $_POST['order_number'] ) || empty( $_POST['billing_email'] ) ) {
				wp_send_json_error( array( 
					'message' => __( 'Please provide both order number and email address.', 'hezarfen-for-woocommerce' )
				) );
			}

			$order_number = sanitize_text_field( $_POST['order_number'] );
			$billing_email = sanitize_email( $_POST['billing_email'] );

			// Validate email format
			if ( ! is_email( $billing_email ) ) {
				wp_send_json_error( array( 
					'message' => __( 'Please provide a valid email address.', 'hezarfen-for-woocommerce' )
				) );
			}

			// Remove # if present
			$order_number = ltrim( $order_number, '#' );

			// Validate order number format
			if ( empty( $order_number ) || ! is_numeric( $order_number ) ) {
				wp_send_json_error( array( 
					'message' => __( 'Please provide a valid order number.', 'hezarfen-for-woocommerce' )
				) );
			}

			// Find the order
			$order = $this->find_order( $order_number, $billing_email );

			if ( ! $order ) {
				wp_send_json_error( array( 
					'message' => __( 'Order not found. Please check your order number and email address.', 'hezarfen-for-woocommerce' )
				) );
			}

			// Get tracking information
			$tracking_data = $this->get_order_tracking_data( $order );

			wp_send_json_success( array(
				'html' => $this->render_tracking_results( $order, $tracking_data )
			) );

		} catch ( \Exception $e ) {
			// Log the error
			error_log( 'Hezarfen Order Tracking AJAX Error: ' . $e->getMessage() );
			
			wp_send_json_error( array( 
				'message' => __( 'An error occurred while processing your request. Please try again.', 'hezarfen-for-woocommerce' )
			) );
		}
	}

	/**
	 * Find order by order number and email
	 * 
	 * @param string $order_number Order number
	 * @param string $billing_email Email address
	 * @return WC_Order|false Order object or false if not found
	 */
	private function find_order( $order_number, $billing_email ) {
		// Try to get order by ID first
		$order = wc_get_order( $order_number );

		if ( $order && $order->get_billing_email() === $billing_email ) {
			return $order;
		}

		// Search by order number in meta
		$orders = wc_get_orders( array(
			'limit'        => 1,
			'meta_key'     => '_order_number',
			'meta_value'   => $order_number,
			'meta_compare' => '=',
		) );

		foreach ( $orders as $order ) {
			if ( $order->get_billing_email() === $billing_email ) {
				return $order;
			}
		}

		// Search by order key
		$orders = wc_get_orders( array(
			'limit'     => 1,
			'order_key' => $order_number,
		) );

		foreach ( $orders as $order ) {
			if ( $order->get_billing_email() === $billing_email ) {
				return $order;
			}
		}

		return false;
	}

	/**
	 * Get tracking data for an order
	 * 
	 * @param WC_Order $order Order object
	 * @return array Tracking data
	 */
	private function get_order_tracking_data( $order ) {
		$tracking_data = array();

		// Check if manual shipment tracking is enabled
		if ( class_exists( 'Hezarfen\ManualShipmentTracking\Manual_Shipment_Tracking' ) ) {
			// Try multiple methods to get shipment data reliably
			$shipment_data_values = array();
			
			// Method 1: Get single meta value (most common case)
			$single_meta = $order->get_meta( '_hezarfen_mst_shipment_data', true );
			if ( ! empty( $single_meta ) ) {
				$shipment_data_values[] = $single_meta;
			}
			
			// Method 2: Get all meta values (for multiple shipments)
			$all_meta = $order->get_meta( '_hezarfen_mst_shipment_data', false );
			if ( is_array( $all_meta ) && ! empty( $all_meta ) ) {
				foreach ( $all_meta as $meta_item ) {
					// Handle WC_Meta_Data objects
					if ( is_object( $meta_item ) && method_exists( $meta_item, 'get_data' ) ) {
						$meta_data = $meta_item->get_data();
						$value = $meta_data['value'] ?? null;
					} else {
						$value = $meta_item;
					}
					
					if ( ! empty( $value ) && $value !== $single_meta ) {
						$shipment_data_values[] = $value;
					}
				}
			}

			// Process each shipment data value
			foreach ( $shipment_data_values as $shipment_value ) {
				if ( empty( $shipment_value ) ) {
					continue;
				}

				try {
					// Create shipment data object
					$shipment = new \Hezarfen\ManualShipmentTracking\Shipment_Data( $shipment_value );

					// Only add if we have a tracking number
					if ( ! empty( $shipment->tracking_num ) ) {
						$tracking_data[] = array(
							'courier_title' => $shipment->courier_title ?: __( 'Unknown Courier', 'hezarfen-for-woocommerce' ),
							'tracking_num'  => $shipment->tracking_num,
							'tracking_url'  => $shipment->tracking_url ?: '',
						);
					}
				} catch ( \Exception $e ) {
					// Log the error but continue processing other shipments
					error_log( sprintf(
						'Hezarfen Order Tracking: Error processing shipment data for order %d - %s',
						$order->get_id(),
						$e->getMessage()
					) );
				}
			}
		}

		return $tracking_data;
	}

	/**
	 * Render tracking results
	 * 
	 * @param WC_Order $order Order object
	 * @param array $tracking_data Tracking data
	 * @return string HTML output
	 */
	private function render_tracking_results( $order, $tracking_data ) {
		ob_start();
		?>
		<div class="hezarfen-tracking-success">
			<div class="hezarfen-success-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="m9 12 2 2 4-4"/>
					<circle cx="12" cy="12" r="9"/>
				</svg>
			</div>
			
			<div class="hezarfen-order-info">
				<h3 class="hezarfen-order-title">
					<?php printf( __( 'Order #%s', 'hezarfen-for-woocommerce' ), $order->get_order_number() ); ?>
				</h3>
				
				<div class="hezarfen-order-meta">
					<div class="hezarfen-meta-item">
						<span class="hezarfen-meta-label"><?php esc_html_e( 'Status:', 'hezarfen-for-woocommerce' ); ?></span>
						<span class="hezarfen-meta-value hezarfen-status-<?php echo esc_attr( $order->get_status() ); ?>">
							<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
						</span>
					</div>
					
					<div class="hezarfen-meta-item">
						<span class="hezarfen-meta-label"><?php esc_html_e( 'Date:', 'hezarfen-for-woocommerce' ); ?></span>
						<span class="hezarfen-meta-value">
							<?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
						</span>
					</div>
					
					<div class="hezarfen-meta-item">
						<span class="hezarfen-meta-label"><?php esc_html_e( 'Total:', 'hezarfen-for-woocommerce' ); ?></span>
						<span class="hezarfen-meta-value">
							<?php 
							// Format the order total with proper Turkish Lira handling
							$formatted_total = $order->get_formatted_order_total();
							// Convert ₺ to TL for better display compatibility (like in SMS automation)
							$formatted_total = str_replace( '₺', 'TL', $formatted_total );
							echo wp_kses_post( $formatted_total );
							?>
						</span>
					</div>
				</div>
			</div>

			<?php if ( ! empty( $tracking_data ) ) : ?>
				<div class="hezarfen-tracking-info">
					<h4 class="hezarfen-tracking-subtitle">
						<?php esc_html_e( 'Tracking Information', 'hezarfen-for-woocommerce' ); ?>
					</h4>
					
					<div class="hezarfen-tracking-items">
						<?php foreach ( $tracking_data as $tracking ) : ?>
							<div class="hezarfen-tracking-item">
								<div class="hezarfen-tracking-courier">
									<span class="hezarfen-courier-name"><?php echo esc_html( $tracking['courier_title'] ); ?></span>
								</div>
								
								<div class="hezarfen-tracking-details">
									<div class="hezarfen-tracking-number">
										<span class="hezarfen-tracking-label"><?php esc_html_e( 'Tracking Number:', 'hezarfen-for-woocommerce' ); ?></span>
										<code class="hezarfen-tracking-code"><?php echo esc_html( $tracking['tracking_num'] ); ?></code>
									</div>
									
									<?php if ( ! empty( $tracking['tracking_url'] ) ) : ?>
										<a href="<?php echo esc_url( $tracking['tracking_url'] ); ?>" 
										   target="_blank" 
										   rel="noopener noreferrer" 
										   class="hezarfen-track-button">
											<?php esc_html_e( 'Track Package', 'hezarfen-for-woocommerce' ); ?>
											<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
												<path d="m9 18 6-6-6-6"/>
											</svg>
										</a>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php else : ?>
				<div class="hezarfen-no-tracking">
					<p><?php esc_html_e( 'No tracking information available yet. We\'ll update you once your order ships.', 'hezarfen-for-woocommerce' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="hezarfen-order-actions">
				<button type="button" class="hezarfen-secondary-button" onclick="resetHezarfenTracking()">
					<?php esc_html_e( 'Track Another Order', 'hezarfen-for-woocommerce' ); ?>
				</button>
				
				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>" class="hezarfen-secondary-button">
						<?php esc_html_e( 'View All Orders', 'hezarfen-for-woocommerce' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

// Initialize the shortcode
new Order_Tracking_Shortcode();