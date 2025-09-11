<?php
/**
 * Clean Checkout Field Editor Class - React Version Only
 *
 * @package Hezarfen_For_WooCommerce
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit;

/**
 * Checkout Field Editor - React Implementation
 */
class Checkout_Field_Editor {

	/**
	 * Field types
	 *
	 * @var array
	 */
	private $field_types = array();

	/**
	 * Field sections
	 *
	 * @var array
	 */
	private $field_sections = array();

	/**
	 * Column width options
	 *
	 * @var array
	 */
	private $column_widths = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize field types
		$this->field_types = array(
			'text'     => __( 'Text', 'hezarfen-for-woocommerce' ),
			'email'    => __( 'Email', 'hezarfen-for-woocommerce' ),
			'tel'      => __( 'Phone', 'hezarfen-for-woocommerce' ),
			'number'   => __( 'Number', 'hezarfen-for-woocommerce' ),
			'textarea' => __( 'Textarea', 'hezarfen-for-woocommerce' ),
			'select'   => __( 'Select', 'hezarfen-for-woocommerce' ),
			'radio'    => __( 'Radio', 'hezarfen-for-woocommerce' ),
			'checkbox' => __( 'Checkbox', 'hezarfen-for-woocommerce' ),
			'date'     => __( 'Date', 'hezarfen-for-woocommerce' ),
		);

		// Initialize field sections
		$this->field_sections = array(
			'billing'  => __( 'Billing', 'hezarfen-for-woocommerce' ),
			'shipping' => __( 'Shipping', 'hezarfen-for-woocommerce' ),
			'order'    => __( 'Order', 'hezarfen-for-woocommerce' ),
		);

		// Initialize column width options
		$this->column_widths = array(
			'full' => __( 'Full Width (1 column)', 'hezarfen-for-woocommerce' ),
			'half' => __( 'Half Width (1/2 column)', 'hezarfen-for-woocommerce' ),
		);

		$this->init();
	}

	/**
	 * Initialize
	 */
	public function init() {
		// Admin hooks
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
			
			// AJAX handlers
			add_action( 'wp_ajax_hezarfen_get_checkout_fields', array( $this, 'ajax_get_fields' ) );
			add_action( 'wp_ajax_hezarfen_save_checkout_field', array( $this, 'ajax_save_field' ) );
			add_action( 'wp_ajax_hezarfen_delete_checkout_field', array( $this, 'ajax_delete_field' ) );
			add_action( 'wp_ajax_hezarfen_reset_checkout_field', array( $this, 'ajax_reset_field' ) );
			add_action( 'wp_ajax_hezarfen_reorder_checkout_fields', array( $this, 'ajax_reorder_fields' ) );
			add_action( 'wp_ajax_hezarfen_export_checkout_fields', array( $this, 'ajax_export_fields' ) );
			add_action( 'wp_ajax_hezarfen_import_checkout_fields', array( $this, 'ajax_import_fields' ) );
		}

		// Frontend hooks
		add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_custom_fields' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_custom_fields' ) );

		// Display custom fields in admin
			add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_custom_fields_in_admin' ) );
			add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_custom_fields_in_admin' ) );
	}

	/**
	 * Enqueue admin scripts
	 */
	public function admin_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		if ( ! isset( $_GET['tab'] ) || 'hezarfen-checkout-fields' !== $_GET['tab'] ) {
			return;
		}

		// Enqueue React CSS
		wp_enqueue_style(
			'hezarfen-checkout-field-editor-react',
			WC_HEZARFEN_UYGULAMA_URL . 'assets/css/admin/checkout-field-editor-react.css',
			array(),
			WC_HEZARFEN_VERSION
		);

		// Enqueue React libraries
		wp_enqueue_script(
			'react',
			'https://unpkg.com/react@18/umd/react.development.js',
			array(),
			'18.0.0',
			true
		);

		wp_enqueue_script(
			'react-dom',
			'https://unpkg.com/react-dom@18/umd/react-dom.development.js',
			array( 'react' ),
			'18.0.0',
			true
		);

		wp_enqueue_script(
			'react-beautiful-dnd',
			'https://unpkg.com/react-beautiful-dnd@13.1.1/dist/react-beautiful-dnd.min.js',
			array( 'react', 'react-dom' ),
			'13.1.1',
			true
		);

		// Enqueue React component
		wp_enqueue_script(
			'hezarfen-checkout-field-editor-react',
			WC_HEZARFEN_UYGULAMA_URL . 'assets/js/admin/checkout-field-editor-react.js',
			array( 'react', 'react-dom', 'react-beautiful-dnd' ),
			WC_HEZARFEN_VERSION,
			true
		);

		// Localize script data
		wp_localize_script(
			'hezarfen-checkout-field-editor-react',
			'hezarfen_checkout_field_editor',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'hezarfen_checkout_field_editor' ),
				'field_types'    => $this->field_types,
				'sections'       => $this->field_sections,
				'column_widths'  => $this->column_widths,
				'custom_fields_data' => $this->get_custom_fields(),
				'default_fields_data' => $this->get_default_fields(),
			)
		);
	}

	/**
	 * Render admin interface - React Only
	 */
	public function render_admin_interface() {
		?>
		<div id="hezarfen-checkout-field-editor-react-root">
			<div class="loading" style="text-align: center; padding: 40px; font-size: 16px; color: #666;">
				Loading React Checkout Field Editor...
					</div>
					</div>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				if (typeof React !== 'undefined' && typeof ReactDOM !== 'undefined' && typeof CheckoutFieldEditor !== 'undefined') {
					const root = ReactDOM.createRoot(document.getElementById('hezarfen-checkout-field-editor-react-root'));
					root.render(React.createElement(CheckoutFieldEditor));
				} else {
					console.error('React dependencies not loaded properly');
				}
			});
		</script>
		<?php
	}

	/**
	 * Get custom fields
	 */
	public function get_custom_fields() {
		$fields = get_option( 'hezarfen_checkout_custom_fields', array() );
		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Get default WooCommerce fields
	 */
	public function get_default_fields() {
		$fields = array();
		
		// Get WooCommerce default billing fields
		$billing_fields = WC()->countries->get_address_fields( WC()->countries->get_base_country(), 'billing_' );
		foreach ( $billing_fields as $key => $field ) {
			$fields[ $key ] = array_merge( $field, array(
				'section' => 'billing',
				'enabled' => true,
				'priority' => $field['priority'] ?? 10,
				'column_width' => 'full'
			));
		}
		
		// Get WooCommerce default shipping fields  
		$shipping_fields = WC()->countries->get_address_fields( WC()->countries->get_base_country(), 'shipping_' );
		foreach ( $shipping_fields as $key => $field ) {
			$fields[ $key ] = array_merge( $field, array(
				'section' => 'shipping',
				'enabled' => true,
				'priority' => $field['priority'] ?? 10,
				'column_width' => 'full'
			));
		}
		
		// Add order fields
		$fields['order_comments'] = array(
				'label' => __( 'Order notes', 'woocommerce' ),
				'type' => 'textarea',
				'section' => 'order',
			'enabled' => true,
			'priority' => 10,
			'column_width' => 'full',
			'placeholder' => __( 'Notes about your order, e.g. special notes for delivery.', 'woocommerce' )
		);
		
		return $fields;
	}

	/**
	 * AJAX get fields for React
	 */
	public function ajax_get_fields() {
		check_ajax_referer( 'hezarfen_checkout_field_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$fields = array(
			'billing' => array(),
			'shipping' => array(),
			'order' => array()
		);

		// Get custom fields
		$custom_fields = $this->get_custom_fields();
		foreach ( $custom_fields as $field_id => $field_data ) {
			$section = $field_data['section'] ?? 'billing';
			$fields[ $section ][] = array_merge( $field_data, array(
				'id' => $field_id,
				'is_default' => false
			));
		}

		// Get default fields
		$default_fields = $this->get_default_fields();
		foreach ( $default_fields as $field_id => $field_data ) {
			$section = $field_data['section'] ?? 'billing';
			$fields[ $section ][] = array_merge( $field_data, array(
				'id' => $field_id,
				'is_default' => true
			));
		}

		wp_send_json_success( $fields );
	}

	/**
	 * AJAX save field
	 */
	public function ajax_save_field() {
		check_ajax_referer( 'hezarfen_checkout_field_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$field_data = json_decode( stripslashes( $_POST['field_data'] ), true );
		
		if ( ! $field_data ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field data.', 'hezarfen-for-woocommerce' ) ) );
		}

		// Validate required fields
		if ( empty( $field_data['name'] ) || empty( $field_data['label'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Field name and label are required.', 'hezarfen-for-woocommerce' ) ) );
		}

		// Sanitize field data
		$field = array(
			'name' => sanitize_key( $field_data['name'] ),
			'label' => sanitize_text_field( $field_data['label'] ),
			'type' => sanitize_text_field( $field_data['type'] ?? 'text' ),
			'section' => sanitize_text_field( $field_data['section'] ?? 'billing' ),
			'placeholder' => sanitize_text_field( $field_data['placeholder'] ?? '' ),
			'required' => ! empty( $field_data['required'] ),
			'enabled' => ! empty( $field_data['enabled'] ),
			'column_width' => sanitize_text_field( $field_data['column_width'] ?? 'full' ),
			'priority' => absint( $field_data['priority'] ?? 10 ),
			'options' => sanitize_textarea_field( $field_data['options'] ?? '' ),
		);

		// Save field
		$custom_fields = $this->get_custom_fields();
		$custom_fields[ $field['name'] ] = $field;
		update_option( 'hezarfen_checkout_custom_fields', $custom_fields );

		wp_send_json_success( array( 'message' => __( 'Field saved successfully.', 'hezarfen-for-woocommerce' ) ) );
	}

	/**
	 * AJAX delete field
	 */
	public function ajax_delete_field() {
		check_ajax_referer( 'hezarfen_checkout_field_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$field_id = sanitize_key( $_POST['field_id'] );
		
		if ( empty( $field_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Field ID is required.', 'hezarfen-for-woocommerce' ) ) );
		}

		$custom_fields = $this->get_custom_fields();
		if ( isset( $custom_fields[ $field_id ] ) ) {
			unset( $custom_fields[ $field_id ] );
			update_option( 'hezarfen_checkout_custom_fields', $custom_fields );
		}

		wp_send_json_success( array( 'message' => __( 'Field deleted successfully.', 'hezarfen-for-woocommerce' ) ) );
	}

	/**
	 * AJAX reset field
	 */
	public function ajax_reset_field() {
		check_ajax_referer( 'hezarfen_checkout_field_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		wp_send_json_success( array( 'message' => __( 'Field reset successfully.', 'hezarfen-for-woocommerce' ) ) );
	}

	/**
	 * AJAX reorder fields
	 */
	public function ajax_reorder_fields() {
		check_ajax_referer( 'hezarfen_checkout_field_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$fields_data = json_decode( stripslashes( $_POST['fields'] ), true );
		
		if ( ! $fields_data ) {
			wp_send_json_error( array( 'message' => __( 'Invalid fields data.', 'hezarfen-for-woocommerce' ) ) );
		}

		// Update field priorities based on new order
		$custom_fields = $this->get_custom_fields();
		$priority = 10;
		
		foreach ( $fields_data as $section => $section_fields ) {
			foreach ( $section_fields as $field ) {
				if ( ! $field['is_default'] && isset( $custom_fields[ $field['id'] ] ) ) {
					$custom_fields[ $field['id'] ]['priority'] = $priority;
					$custom_fields[ $field['id'] ]['section'] = $section;
					$priority += 10;
				}
			}
		}
		
		update_option( 'hezarfen_checkout_custom_fields', $custom_fields );

		wp_send_json_success( array( 'message' => __( 'Fields reordered successfully.', 'hezarfen-for-woocommerce' ) ) );
	}

	/**
	 * AJAX export fields
	 */
	public function ajax_export_fields() {
		check_ajax_referer( 'hezarfen_checkout_field_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$export_data = array(
			'custom_fields' => $this->get_custom_fields(),
			'export_date' => current_time( 'mysql' ),
			'version' => WC_HEZARFEN_VERSION
		);

		wp_send_json_success( $export_data );
	}

	/**
	 * AJAX import fields
	 */
	public function ajax_import_fields() {
		check_ajax_referer( 'hezarfen_checkout_field_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$import_data = json_decode( stripslashes( $_POST['import_data'] ), true );
		
		if ( ! $import_data ) {
			wp_send_json_error( array( 'message' => __( 'Invalid import data.', 'hezarfen-for-woocommerce' ) ) );
		}

		// Import custom fields
		if ( isset( $import_data['custom_fields'] ) ) {
			update_option( 'hezarfen_checkout_custom_fields', $import_data['custom_fields'] );
		}

		wp_send_json_success( array( 'message' => __( 'Fields imported successfully.', 'hezarfen-for-woocommerce' ) ) );
	}

	/**
	 * Customize checkout fields
	 */
	public function customize_checkout_fields( $fields ) {
		$custom_fields = $this->get_custom_fields();

		// Add custom fields with proper column width classes
		foreach ( $custom_fields as $field_id => $field_data ) {
			if ( ! $field_data['enabled'] ) {
				continue;
			}

			$section = $field_data['section'];
			if ( ! isset( $fields[ $section ] ) ) {
				$fields[ $section ] = array();
			}

				// Determine column width class
				$column_width = $field_data['column_width'] ?? 'full';
			$width_classes = $column_width === 'half' ? array( 'form-row', 'form-row-first' ) : array( 'form-row-wide' );
				
				$field_config = array(
					'label'       => $field_data['label'],
					'type'        => $field_data['type'],
					'required'    => $field_data['required'],
					'priority'    => $field_data['priority'],
					'class'       => $width_classes,
				);

				if ( ! empty( $field_data['placeholder'] ) ) {
					$field_config['placeholder'] = $field_data['placeholder'];
				}

				// Handle select and radio field options
				if ( in_array( $field_data['type'], array( 'select', 'radio' ) ) && ! empty( $field_data['options'] ) ) {
					$options = array( '' => __( 'Select an option', 'hezarfen-for-woocommerce' ) );
					$lines = explode( "\n", $field_data['options'] );
					foreach ( $lines as $line ) {
						$line = trim( $line );
						if ( ! empty( $line ) ) {
							if ( strpos( $line, '|' ) !== false ) {
								$parts = explode( '|', $line, 2 );
								$key = trim( $parts[0] );
								$value = trim( $parts[1] );
								$options[ $key ] = $value;
							} else {
								$options[ $line ] = $line;
							}
						}
					}
					$field_config['options'] = $options;
				}

				$fields[ $section ][ $field_id ] = $field_config;
		}

		return $fields;
	}

	/**
	 * Validate custom fields
	 */
	public function validate_custom_fields() {
		$custom_fields = $this->get_custom_fields();

		foreach ( $custom_fields as $field_id => $field_data ) {
			if ( ! $field_data['enabled'] || ! $field_data['required'] ) {
				continue;
			}

			$value = isset( $_POST[ $field_id ] ) ? sanitize_text_field( $_POST[ $field_id ] ) : '';

			if ( empty( $value ) ) {
				wc_add_notice( sprintf( __( '%s is a required field.', 'hezarfen-for-woocommerce' ), $field_data['label'] ), 'error' );
			}
		}
	}

	/**
	 * Save custom fields
	 */
	public function save_custom_fields( $order_id ) {
		$custom_fields = $this->get_custom_fields();

		foreach ( $custom_fields as $field_id => $field_data ) {
			if ( ! $field_data['enabled'] ) {
				continue;
			}

			if ( isset( $_POST[ $field_id ] ) ) {
				$value = sanitize_text_field( $_POST[ $field_id ] );
			if ( ! empty( $value ) ) {
					update_post_meta( $order_id, $field_id, $value );
			}
		}
		}
	}

	/**
	 * Display custom fields in admin
	 */
	public function display_custom_fields_in_admin( $order ) {
		$custom_fields = $this->get_custom_fields();

		foreach ( $custom_fields as $field_id => $field_data ) {
			if ( ! $field_data['enabled'] ) {
				continue;
			}

			$value = get_post_meta( $order->get_id(), $field_id, true );
			if ( ! empty( $value ) ) {
				echo '<p><strong>' . esc_html( $field_data['label'] ) . ':</strong> ' . esc_html( $value ) . '</p>';
			}
		}
	}
}