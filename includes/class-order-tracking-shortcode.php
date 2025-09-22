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
		add_action( 'wp_ajax_hezarfen_track_user_order', array( $this, 'ajax_track_user_order' ) );
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

			// Add custom CSS from settings
			$this->add_custom_css();

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
		// Get settings from admin panel, with shortcode attributes as fallback
		$default_title = get_option( 'hezarfen_tracking_page_title', __( 'Track Your Order', 'hezarfen-for-woocommerce' ) );
		$default_description = get_option( 'hezarfen_tracking_page_description', __( 'Enter your order number and email address to track your shipment.', 'hezarfen-for-woocommerce' ) );
		
		$atts = shortcode_atts(
			array(
				'title'       => $default_title,
				'description' => $default_description,
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

				<?php if ( is_user_logged_in() ) : ?>
					<?php echo $this->render_logged_in_user_interface(); ?>
				<?php else : ?>
					<?php echo $this->render_guest_user_interface(); ?>
				<?php endif; ?>

				<div class="hezarfen-tracking-results" id="hezarfen-tracking-results" style="display: none;">
					<!-- Results will be loaded here via AJAX -->
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render interface for logged-in users
	 * 
	 * @return string HTML output
	 */
	private function render_logged_in_user_interface() {
		$current_user = wp_get_current_user();
		$user_orders = $this->get_user_orders( $current_user->ID );

		ob_start();
		?>
		<div class="hezarfen-logged-in-interface">
			<?php if ( ! empty( $user_orders ) ) : ?>
				<div class="hezarfen-user-welcome">
					<p class="hezarfen-welcome-text">
						<?php printf( 
							__( 'Welcome back, %s! Select an order to track:', 'hezarfen-for-woocommerce' ), 
							esc_html( $current_user->display_name ) 
						); ?>
					</p>
				</div>

				<div class="hezarfen-user-orders-grid">
					<div class="hezarfen-orders-label">
						<h3><?php esc_html_e( 'Your Orders', 'hezarfen-for-woocommerce' ); ?></h3>
						<p class="hezarfen-orders-subtitle"><?php esc_html_e( 'Click on any order to track its shipment status', 'hezarfen-for-woocommerce' ); ?></p>
					</div>
					
					<div class="hezarfen-orders-list">
						<?php foreach ( $user_orders as $order ) : ?>
							<div class="hezarfen-order-card" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" onclick="hezarfenTrackUserOrder(<?php echo esc_attr( $order->get_id() ); ?>)">
								<div class="hezarfen-order-card-header">
									<div class="hezarfen-order-number">
										<strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>
									</div>
									<div class="hezarfen-order-status hezarfen-status-<?php echo esc_attr( $order->get_status() ); ?>">
										<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
									</div>
								</div>
								
								<div class="hezarfen-order-card-body">
									<div class="hezarfen-order-meta-grid">
										<div class="hezarfen-order-date">
											<span class="hezarfen-meta-icon">📅</span>
											<?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
										</div>
										
										<?php if ( get_option( 'hezarfen_tracking_page_show_total', 'yes' ) === 'yes' ) : ?>
										<div class="hezarfen-order-total">
											<span class="hezarfen-meta-icon">💰</span>
											<?php 
											$formatted_total = $order->get_formatted_order_total();
											$formatted_total = str_replace( '₺', 'TL', $formatted_total );
											echo wp_kses_post( $formatted_total );
											?>
										</div>
										<?php endif; ?>
									</div>
									
									<?php 
									$item_count = $order->get_item_count();
									if ( $item_count > 0 ) :
									?>
									<div class="hezarfen-order-items">
										<span class="hezarfen-meta-icon">📦</span>
										<?php 
										printf( 
											_n( '%d item', '%d items', $item_count, 'hezarfen-for-woocommerce' ), 
											$item_count 
										);
										?>
									</div>
									<?php endif; ?>
								</div>
								
								<div class="hezarfen-order-card-footer">
									<div class="hezarfen-track-action">
										<span class="hezarfen-track-text"><?php esc_html_e( 'Click to track', 'hezarfen-for-woocommerce' ); ?></span>
										<svg class="hezarfen-track-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<path d="m9 18 6-6-6-6"/>
										</svg>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="hezarfen-guest-option">
					<p class="hezarfen-guest-text">
						<?php esc_html_e( 'Looking for an order from a different account?', 'hezarfen-for-woocommerce' ); ?>
						<button type="button" class="hezarfen-guest-toggle" onclick="hezarfenToggleGuestMode()">
							<?php esc_html_e( 'Track as guest', 'hezarfen-for-woocommerce' ); ?>
						</button>
					</p>
				</div>

			<?php else : ?>
				<div class="hezarfen-no-orders">
					<div class="hezarfen-no-orders-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<circle cx="12" cy="12" r="10"/>
							<path d="m9 12 2 2 4-4"/>
						</svg>
					</div>
					<h3><?php esc_html_e( 'No Orders Found', 'hezarfen-for-woocommerce' ); ?></h3>
					<p><?php esc_html_e( 'You haven\'t placed any orders yet. Start shopping to see your orders here!', 'hezarfen-for-woocommerce' ); ?></p>
					<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="hezarfen-secondary-button">
						<?php esc_html_e( 'Start Shopping', 'hezarfen-for-woocommerce' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<!-- Hidden guest form for toggle functionality -->
			<div class="hezarfen-guest-form" id="hezarfen-guest-form" style="display: none;">
				<?php echo $this->render_guest_user_interface(); ?>
				<div class="hezarfen-back-to-user">
					<button type="button" class="hezarfen-secondary-button" onclick="hezarfenToggleGuestMode(false)">
						<?php esc_html_e( 'Back to My Orders', 'hezarfen-for-woocommerce' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render interface for guest users
	 * 
	 * @return string HTML output
	 */
	private function render_guest_user_interface() {
		ob_start();
		?>
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
		<?php
		return ob_get_clean();
	}

	/**
	 * Get user orders for dropdown
	 * 
	 * @param int $user_id User ID
	 * @return WC_Order[] Array of user orders
	 */
	private function get_user_orders( $user_id ) {
		$orders = wc_get_orders( array(
			'customer' => $user_id,
			'limit'    => 50, // Limit to last 50 orders
			'orderby'  => 'date',
			'order'    => 'DESC',
			'status'   => array_keys( wc_get_order_statuses() ), // All statuses
		) );

		return $orders;
	}

	/**
	 * AJAX handler for logged-in user order tracking
	 */
	public function ajax_track_user_order() {
		try {
			// Verify user is logged in
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( array( 'message' => __( 'You must be logged in to use this feature.', 'hezarfen-for-woocommerce' ) ) );
			}

			// Verify nonce
			if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'hezarfen_track_order' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'hezarfen-for-woocommerce' ) ) );
			}

			// Validate required fields
			if ( empty( $_POST['order_id'] ) ) {
				wp_send_json_error( array( 
					'message' => __( 'Please select an order to track.', 'hezarfen-for-woocommerce' )
				) );
			}

			$order_id = intval( $_POST['order_id'] );
			$current_user_id = get_current_user_id();

			// Get the order
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				wp_send_json_error( array( 
					'message' => __( 'Order not found.', 'hezarfen-for-woocommerce' )
				) );
			}

			// Verify the order belongs to the current user
			if ( $order->get_customer_id() !== $current_user_id ) {
				wp_send_json_error( array( 
					'message' => __( 'You are not authorized to view this order.', 'hezarfen-for-woocommerce' )
				) );
			}

			// Get tracking information
			$tracking_data = $this->get_order_tracking_data( $order );

			wp_send_json_success( array(
				'html' => $this->render_tracking_results( $order, $tracking_data )
			) );

		} catch ( \Exception $e ) {
			// Log the error
			error_log( 'Hezarfen User Order Tracking AJAX Error: ' . $e->getMessage() );
			
			wp_send_json_error( array( 
				'message' => __( 'An error occurred while processing your request. Please try again.', 'hezarfen-for-woocommerce' )
			) );
		}
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
					
					<?php if ( get_option( 'hezarfen_tracking_page_show_date', 'yes' ) === 'yes' ) : ?>
					<div class="hezarfen-meta-item">
						<span class="hezarfen-meta-label"><?php esc_html_e( 'Date:', 'hezarfen-for-woocommerce' ); ?></span>
						<span class="hezarfen-meta-value">
							<?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
						</span>
					</div>
					<?php endif; ?>
					
					<?php if ( get_option( 'hezarfen_tracking_page_show_total', 'yes' ) === 'yes' ) : ?>
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
					<?php endif; ?>
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

	/**
	 * Add custom CSS based on settings
	 */
	private function add_custom_css() {
		// Get customization settings
		$primary_color = get_option( 'hezarfen_tracking_page_primary_color', '#3b82f6' );
		$secondary_color = get_option( 'hezarfen_tracking_page_secondary_color', '#8b5cf6' );
		$success_color = get_option( 'hezarfen_tracking_page_success_color', '#10b981' );
		$border_radius = get_option( 'hezarfen_tracking_page_border_radius', '12' );
		$max_width = get_option( 'hezarfen_tracking_page_max_width', '600' );
		$dark_mode = get_option( 'hezarfen_tracking_page_dark_mode', 'yes' );
		$custom_css = get_option( 'hezarfen_tracking_page_custom_css', '' );

		// Generate CSS variables and customizations
		$css = '<style id="hezarfen-tracking-custom-css">';
		
		// CSS Custom Properties
		$css .= ':root {';
		$css .= '--hezarfen-primary-color: ' . esc_attr( $primary_color ) . ';';
		$css .= '--hezarfen-secondary-color: ' . esc_attr( $secondary_color ) . ';';
		$css .= '--hezarfen-success-color: ' . esc_attr( $success_color ) . ';';
		$css .= '--hezarfen-border-radius: ' . intval( $border_radius ) . 'px;';
		$css .= '--hezarfen-max-width: ' . intval( $max_width ) . 'px;';
		$css .= '}';

		// Apply customizations
		$css .= '.hezarfen-tracking-container {';
		$css .= 'max-width: var(--hezarfen-max-width);';
		$css .= '}';

		$css .= '.hezarfen-tracking-card {';
		$css .= 'border-radius: var(--hezarfen-border-radius);';
		$css .= '}';

		$css .= '.hezarfen-tracking-button {';
		$css .= 'background: linear-gradient(135deg, var(--hezarfen-primary-color) 0%, ' . $this->darken_color( $primary_color, 20 ) . ' 100%);';
		$css .= 'border-radius: calc(var(--hezarfen-border-radius) - 4px);';
		$css .= '}';

		$css .= '.hezarfen-track-button {';
		$css .= 'background: linear-gradient(135deg, var(--hezarfen-secondary-color) 0%, ' . $this->darken_color( $secondary_color, 20 ) . ' 100%);';
		$css .= 'border-radius: calc(var(--hezarfen-border-radius) - 6px);';
		$css .= '}';

		$css .= '.hezarfen-success-icon {';
		$css .= 'background: linear-gradient(135deg, var(--hezarfen-success-color) 0%, ' . $this->darken_color( $success_color, 20 ) . ' 100%);';
		$css .= '}';

		$css .= '.hezarfen-form-input, .hezarfen-tracking-item, .hezarfen-secondary-button {';
		$css .= 'border-radius: calc(var(--hezarfen-border-radius) - 4px);';
		$css .= '}';

		// Disable dark mode if setting is off
		if ( $dark_mode !== 'yes' ) {
			$css .= '@media (prefers-color-scheme: dark) {';
			$css .= '.hezarfen-tracking-card, .hezarfen-tracking-item, .hezarfen-secondary-button { background: #ffffff !important; border-color: #e5e7eb !important; }';
			$css .= '.hezarfen-tracking-title, .hezarfen-meta-value, .hezarfen-courier-name { color: #111827 !important; }';
			$css .= '.hezarfen-tracking-description, .hezarfen-form-label, .hezarfen-meta-label { color: #6b7280 !important; }';
			$css .= '.hezarfen-form-input { background: #ffffff !important; border-color: #e5e7eb !important; color: #111827 !important; }';
			$css .= '}';
		}

		// Add custom CSS from settings
		if ( ! empty( $custom_css ) ) {
			$css .= wp_strip_all_tags( $custom_css );
		}

		$css .= '</style>';

		echo $css;
	}

	/**
	 * Darken a hex color by a percentage
	 * 
	 * @param string $hex_color Hex color code
	 * @param int $percent Percentage to darken (0-100)
	 * @return string Darkened hex color
	 */
	private function darken_color( $hex_color, $percent ) {
		// Remove # if present
		$hex_color = ltrim( $hex_color, '#' );
		
		// Convert to RGB
		$r = hexdec( substr( $hex_color, 0, 2 ) );
		$g = hexdec( substr( $hex_color, 2, 2 ) );
		$b = hexdec( substr( $hex_color, 4, 2 ) );
		
		// Darken
		$r = max( 0, $r - ( $r * $percent / 100 ) );
		$g = max( 0, $g - ( $g * $percent / 100 ) );
		$b = max( 0, $b - ( $b * $percent / 100 ) );
		
		// Convert back to hex
		return '#' . str_pad( dechex( $r ), 2, '0', STR_PAD_LEFT ) . 
				str_pad( dechex( $g ), 2, '0', STR_PAD_LEFT ) . 
				str_pad( dechex( $b ), 2, '0', STR_PAD_LEFT );
	}
}

// Initialize the shortcode
new Order_Tracking_Shortcode();