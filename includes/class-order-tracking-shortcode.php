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
		
		// Customize title and description based on login status
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$user_orders = $this->get_user_orders( $current_user->ID );
			
			if ( ! empty( $user_orders ) ) {
				$default_title = sprintf( 
					__( 'Welcome back, %s!', 'hezarfen-for-woocommerce' ), 
					$current_user->display_name 
				);
				$default_description = __( 'Select any of your orders below to view detailed tracking information and delivery status.', 'hezarfen-for-woocommerce' );
			} else {
				$default_title = sprintf( 
					__( 'Hello, %s!', 'hezarfen-for-woocommerce' ), 
					$current_user->display_name 
				);
				$default_description = __( 'You haven\'t placed any orders yet. Start shopping to see your orders here!', 'hezarfen-for-woocommerce' );
			}
		}
		
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
				<div class="hezarfen-user-orders-grid">
					<div class="hezarfen-orders-label">
						<?php 
						$order_count = count( $user_orders );
						if ( $order_count > 0 ) {
							printf(
								'<h3>%s</h3>',
								sprintf( 
									_n( 
										'Your Order (%d)', 
										'Your Orders (%d)', 
										$order_count, 
										'hezarfen-for-woocommerce' 
									), 
									$order_count 
								)
							);
						}
						?>
						<p class="hezarfen-orders-subtitle">
							<?php 
							if ( $order_count > 5 ) {
								esc_html_e( 'Showing your recent orders. Click any order to view detailed tracking and delivery progress.', 'hezarfen-for-woocommerce' );
							} else {
								esc_html_e( 'Click any order to view detailed tracking and delivery progress.', 'hezarfen-for-woocommerce' );
							}
							?>
						</p>
					</div>
					
					<div class="hezarfen-orders-list">
						<?php foreach ( $user_orders as $order ) : ?>
							<div class="hezarfen-order-card" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" onclick="hezarfenTrackUserOrder(<?php echo esc_attr( $order->get_id() ); ?>)">
								<!-- Product Images Column -->
								<div class="hezarfen-order-images">
									<?php 
									$items = $order->get_items();
									$image_count = 0;
									foreach ( $items as $item ) {
										if ( $image_count >= 3 ) break; // Max 3 images
										$product = $item->get_product();
										if ( $product ) {
											$image_id = $product->get_image_id();
											if ( $image_id ) {
												$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
												if ( $image_url ) {
													echo '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $product->get_name() ) . '" class="hezarfen-product-image">';
													$image_count++;
												}
											}
										}
									}
									if ( count( $items ) > 3 ) {
										echo '<div class="hezarfen-more-items">+' . ( count( $items ) - 3 ) . '</div>';
									}
									?>
								</div>
								
								<!-- Order Number Column -->
								<div class="hezarfen-order-number">
									<strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>
								</div>
								
								<!-- Status Column -->
								<div class="hezarfen-order-status-col">
									<span class="hezarfen-order-status hezarfen-status-<?php echo esc_attr( $order->get_status() ); ?>">
										<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
									</span>
								</div>
								
								<!-- Amount & Date Column -->
								<div class="hezarfen-order-amount-date">
									<?php if ( get_option( 'hezarfen_tracking_page_show_total', 'yes' ) === 'yes' ) : ?>
									<div class="hezarfen-order-amount">
										<?php 
										$formatted_total = $order->get_formatted_order_total();
										$formatted_total = str_replace( '₺', 'TL', $formatted_total );
										echo wp_kses_post( $formatted_total );
										?>
									</div>
									<?php endif; ?>
									
									<?php if ( get_option( 'hezarfen_tracking_page_show_date', 'yes' ) === 'yes' ) : ?>
									<div class="hezarfen-order-date">
										<?php echo esc_html( $order->get_date_created()->format( 'M d, Y' ) ); ?>
									</div>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>


			<?php else : ?>
				<div class="hezarfen-no-orders">
					<div class="hezarfen-no-orders-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<circle cx="12" cy="12" r="10"/>
							<path d="m9 12 2 2 4-4"/>
						</svg>
					</div>
					<h3><?php esc_html_e( 'Ready to Start Shopping?', 'hezarfen-for-woocommerce' ); ?></h3>
					<p><?php esc_html_e( 'Once you place your first order, you\'ll be able to track its progress right here with real-time updates.', 'hezarfen-for-woocommerce' ); ?></p>
					<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="hezarfen-secondary-button">
						<?php esc_html_e( 'Browse Products', 'hezarfen-for-woocommerce' ); ?>
					</a>
				</div>
			<?php endif; ?>

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
		<div class="hezarfen-tracking-details-panel">
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
			<?php endif; ?>

			<!-- Order Progress Steps -->
			<div class="hezarfen-order-progress">
				<?php echo $this->render_order_progress_steps( $order ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render order progress steps
	 * 
	 * @param WC_Order $order Order object
	 * @return string HTML output
	 */
	private function render_order_progress_steps( $order ) {
		$current_status = $order->get_status();
		$order_date = $order->get_date_created();
		$completed_date = $order->get_date_completed();
		$shipped_date = $this->get_shipped_date( $order );
		
		// Define the order progress steps
		$steps = $this->get_order_progress_steps();
		
		ob_start();
		?>
		<div class="hezarfen-progress-steps">
			<!-- Vertical Progress Track -->
			<div class="hezarfen-progress-track">
				<?php foreach ( $steps as $step_key => $step ) : ?>
					<?php 
					$is_completed = $this->is_step_completed( $step_key, $current_status );
					$is_current = $this->is_current_step( $step_key, $current_status );
					$step_class = $is_completed ? 'completed' : ( $is_current ? 'current' : 'pending' );
					$step_date = $this->get_step_date( $step_key, $order, $order_date, $shipped_date, $completed_date );
					$is_last = $this->is_last_step( $step_key, $steps );
					?>
					<div class="hezarfen-progress-step <?php echo esc_attr( $step_class ); ?>">
						<div class="hezarfen-step-indicator">
							<div class="hezarfen-step-node">
								<?php if ( $is_completed ) : ?>
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
										<path d="m9 12 2 2 4-4"/>
									</svg>
								<?php elseif ( $is_current ) : ?>
									<div class="hezarfen-current-indicator"></div>
								<?php else : ?>
									<div class="hezarfen-pending-dot"></div>
								<?php endif; ?>
							</div>
							<?php if ( ! $is_last ) : ?>
								<?php 
								$connector_class = '';
								// Line should be green only if current step is completed
								if ( $is_completed ) {
									$connector_class = 'completed';
								}
								// Line should be 50% green if current step is active (current)
								elseif ( $is_current ) {
									$connector_class = 'half-complete';
								}
								// Otherwise line is gray (no class)
								?>
								<div class="hezarfen-step-connector <?php echo esc_attr( $connector_class ); ?>"></div>
							<?php endif; ?>
						</div>
						
						<div class="hezarfen-step-content">
							<div class="hezarfen-step-title"><?php echo esc_html( $step['title'] ); ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get order progress steps
	 * 
	 * @return array Progress steps
	 */
	private function get_order_progress_steps() {
		return array(
			'pending' => array(
				'title' => __( 'Order Received', 'hezarfen-for-woocommerce' ),
				'description' => __( 'We have received your order and are processing it', 'hezarfen-for-woocommerce' ),
			),
			'processing' => array(
				'title' => __( 'Order Processing', 'hezarfen-for-woocommerce' ),
				'description' => __( 'Your order is being prepared for shipment', 'hezarfen-for-woocommerce' ),
			),
			'shipped' => array(
				'title' => __( 'Order Shipped', 'hezarfen-for-woocommerce' ),
				'description' => __( 'Your order has been shipped and is on its way', 'hezarfen-for-woocommerce' ),
			),
			'delivered' => array(
				'title' => __( 'Order Delivered', 'hezarfen-for-woocommerce' ),
				'description' => __( 'Your order has been successfully delivered', 'hezarfen-for-woocommerce' ),
			),
		);
	}

	/**
	 * Check if a step is completed
	 * 
	 * @param string $step_key Step key
	 * @param string $current_status Current order status
	 * @return bool
	 */
	private function is_step_completed( $step_key, $current_status ) {
		$status_hierarchy = array(
			'pending' => array( 'processing', 'hezarfen-shipped', 'completed' ),
			'processing' => array( 'hezarfen-shipped', 'completed' ),
			'shipped' => array( 'completed' ),
			'delivered' => array(),
		);

		if ( $step_key === 'shipped' && ( $current_status === 'hezarfen-shipped' || $current_status === 'completed' ) ) {
			return true;
		}

		if ( $step_key === 'delivered' && $current_status === 'completed' ) {
			return true;
		}

		return in_array( $current_status, $status_hierarchy[ $step_key ] ?? array() );
	}

	/**
	 * Check if a step is the current step
	 * 
	 * @param string $step_key Step key
	 * @param string $current_status Current order status
	 * @return bool
	 */
	private function is_current_step( $step_key, $current_status ) {
		$status_mapping = array(
			'pending' => 'pending',
			'processing' => 'processing',
			'shipped' => 'hezarfen-shipped',
			'delivered' => 'completed',
		);

		// Handle special cases
		if ( $step_key === 'pending' && in_array( $current_status, array( 'pending', 'on-hold' ) ) ) {
			return true;
		}

		return ( $status_mapping[ $step_key ] ?? '' ) === $current_status;
	}

	/**
	 * Check if this is the last step
	 * 
	 * @param string $step_key Current step key
	 * @param array $steps All steps
	 * @return bool
	 */
	private function is_last_step( $step_key, $steps ) {
		$step_keys = array_keys( $steps );
		return $step_key === end( $step_keys );
	}

	/**
	 * Check if the next step is completed (for line styling)
	 * 
	 * @param string $current_step_key Current step key
	 * @param string $current_status Current order status
	 * @return bool
	 */
	private function is_next_step_completed( $current_step_key, $current_status ) {
		$step_keys = array( 'pending', 'processing', 'shipped', 'delivered' );
		$current_index = array_search( $current_step_key, $step_keys );
		
		if ( $current_index === false || $current_index >= count( $step_keys ) - 1 ) {
			return false;
		}

		$next_step = $step_keys[ $current_index + 1 ];
		return $this->is_step_completed( $next_step, $current_status );
	}

	/**
	 * Get the date for a specific step
	 * 
	 * @param string $step_key Step key
	 * @param WC_Order $order Order object
	 * @param WC_DateTime $order_date Order date
	 * @param WC_DateTime|null $shipped_date Shipped date
	 * @param WC_DateTime|null $completed_date Completed date
	 * @return WC_DateTime|null
	 */
	private function get_step_date( $step_key, $order, $order_date, $shipped_date, $completed_date ) {
		switch ( $step_key ) {
			case 'pending':
				return $order_date;
			
			case 'processing':
				// Try to get the date when order status changed to processing
				$processing_date = $this->get_status_change_date( $order, 'processing' );
				return $processing_date ?: ( $order->get_status() !== 'pending' ? $order_date : null );
			
			case 'shipped':
				return $shipped_date;
			
			case 'delivered':
				return $completed_date;
			
			default:
				return null;
		}
	}

	/**
	 * Get the shipped date from order meta or status changes
	 * 
	 * @param WC_Order $order Order object
	 * @return WC_DateTime|null
	 */
	private function get_shipped_date( $order ) {
		// Try to get from status change
		$shipped_date = $this->get_status_change_date( $order, 'hezarfen-shipped' );
		if ( $shipped_date ) {
			return $shipped_date;
		}

		// If currently shipped, use modified date as fallback
		if ( $order->get_status() === 'hezarfen-shipped' ) {
			return $order->get_date_modified();
		}

		return null;
	}

	/**
	 * Get the date when order status changed to a specific status
	 * 
	 * @param WC_Order $order Order object
	 * @param string $target_status Target status
	 * @return WC_DateTime|null
	 */
	private function get_status_change_date( $order, $target_status ) {
		// Get order notes to find status change
		$notes = wc_get_order_notes( array(
			'order_id' => $order->get_id(),
			'order_by' => 'date_created',
			'order' => 'ASC',
		) );

		foreach ( $notes as $note ) {
			if ( $note->type === 'system' ) {
				$content = $note->content;
				$status_name = wc_get_order_status_name( $target_status );
				
				// Check if this note is about status change to our target status
				if ( strpos( $content, $status_name ) !== false || 
					 strpos( $content, $target_status ) !== false ) {
					return $note->date_created;
				}
			}
		}

		return null;
	}

	/**
	 * Get SVG icon path for a step
	 * 
	 * @param string $step_key Step key
	 * @return string SVG path
	 */
	private function get_step_icon( $step_key ) {
		$icons = array(
			'pending' => '<path d="M9 12l2 2 4-4"/><path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9c1.66 0 3.22.45 4.56 1.25"/>',
			'processing' => '<circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/>',
			'shipped' => '<path d="M16 3h5v5M21 3l-7 7M4 20h16M4 20v-6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v6"/>',
			'delivered' => '<path d="M20 6L9 17l-5-5"/>',
		);

		return $icons[ $step_key ] ?? $icons['pending'];
	}

	/**
	 * Get current step details for display
	 * 
	 * @param array $steps All steps
	 * @param string $current_status Current order status
	 * @return array Current step details with key
	 */
	private function get_current_step_details( $steps, $current_status ) {
		// Find the current step
		foreach ( $steps as $step_key => $step ) {
			if ( $this->is_current_step( $step_key, $current_status ) ) {
				return array_merge( $step, array( 'key' => $step_key ) );
			}
		}

		// If no current step found, find the most advanced completed step
		$completed_steps = array();
		foreach ( $steps as $step_key => $step ) {
			if ( $this->is_step_completed( $step_key, $current_status ) ) {
				$completed_steps[] = array_merge( $step, array( 'key' => $step_key ) );
			}
		}

		if ( ! empty( $completed_steps ) ) {
			return end( $completed_steps ); // Return the last completed step
		}

		// Fallback to first step
		$first_step_key = array_key_first( $steps );
		return array_merge( $steps[ $first_step_key ], array( 'key' => $first_step_key ) );
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