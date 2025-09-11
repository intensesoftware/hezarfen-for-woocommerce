<?php
/**
 * Checkout Field Editor Class
 *
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Checkout Field Editor
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
	 * Track if AJAX handlers are registered
	 *
	 * @var bool
	 */
	private static $ajax_handlers_registered = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize field types
		$this->field_types = array(
			'text'     => __( 'Text', 'hezarfen-for-woocommerce' ),
			'textarea' => __( 'Textarea', 'hezarfen-for-woocommerce' ),
			'select'   => __( 'Select', 'hezarfen-for-woocommerce' ),
			'radio'    => __( 'Radio', 'hezarfen-for-woocommerce' ),
			'checkbox' => __( 'Checkbox', 'hezarfen-for-woocommerce' ),
			'number'   => __( 'Number', 'hezarfen-for-woocommerce' ),
			'email'    => __( 'Email', 'hezarfen-for-woocommerce' ),
			'tel'      => __( 'Phone', 'hezarfen-for-woocommerce' ),
			'date'     => __( 'Date', 'hezarfen-for-woocommerce' ),
		);

		// Initialize field sections
		$this->field_sections = array(
			'billing'  => __( 'Billing', 'hezarfen-for-woocommerce' ),
			'shipping' => __( 'Shipping', 'hezarfen-for-woocommerce' ),
			'order'    => __( 'Order', 'hezarfen-for-woocommerce' ),
		);

		// Initialize immediately instead of waiting for init action
		$this->init();
		
		// Register AJAX handlers only once to avoid conflicts with multiple instances
		if ( ! self::$ajax_handlers_registered ) {
			add_action( 'wp_ajax_hezarfen_save_checkout_field', array( $this, 'ajax_save_field' ) );
			add_action( 'wp_ajax_hezarfen_delete_checkout_field', array( $this, 'ajax_delete_field' ) );
			add_action( 'wp_ajax_hezarfen_reset_checkout_field', array( $this, 'ajax_reset_field' ) );
			add_action( 'wp_ajax_hezarfen_reorder_checkout_fields', array( $this, 'ajax_reorder_fields' ) );
			add_action( 'wp_ajax_hezarfen_export_checkout_fields', array( $this, 'ajax_export_fields' ) );
			add_action( 'wp_ajax_hezarfen_import_checkout_fields', array( $this, 'ajax_import_fields' ) );
			self::$ajax_handlers_registered = true;
		}
	}

	/**
	 * Initialize
	 */
	public function init() {
		// Always add the checkout field filter (not just for non-admin)
		// This ensures fields are available in all contexts including AJAX calls
		// Use priority 150 to run after other Hezarfen checkout modifications
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_custom_fields' ), 150 );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_custom_fields' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_custom_fields_to_order' ) );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
			add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_custom_fields_in_admin' ) );
			add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_custom_fields_in_admin' ) );
		}
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

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script(
			'hezarfen-checkout-field-editor',
			WC_HEZARFEN_UYGULAMA_URL . 'assets/js/admin/checkout-field-editor.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			WC_HEZARFEN_VERSION,
			true
		);

		wp_localize_script(
			'hezarfen-checkout-field-editor',
			'hezarfen_checkout_field_editor',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'hezarfen_checkout_field_editor' ),
				'field_types'    => $this->field_types,
				'sections'       => $this->field_sections,
				'custom_fields_data' => $this->get_custom_fields(),
				'default_fields_data' => $this->get_default_fields(),
				'confirm_delete' => __( 'Are you sure you want to delete this field?', 'hezarfen-for-woocommerce' ),
				'confirm_reset'  => __( 'Are you sure you want to reset this field to default settings?', 'hezarfen-for-woocommerce' ),
				'add_field_title' => __( 'Add New Field', 'hezarfen-for-woocommerce' ),
				'edit_field_title' => __( 'Edit Field', 'hezarfen-for-woocommerce' ),
				'saving'         => __( 'Saving...', 'hezarfen-for-woocommerce' ),
				'save_field'     => __( 'Save Field', 'hezarfen-for-woocommerce' ),
			)
		);

		wp_enqueue_style(
			'hezarfen-checkout-field-editor',
			WC_HEZARFEN_UYGULAMA_URL . 'assets/css/admin/checkout-field-editor.css',
			array(),
			WC_HEZARFEN_VERSION
		);
	}

	/**
	 * Render admin interface
	 */
	public function render_admin_interface() {
		$custom_fields = $this->get_custom_fields();
		$default_fields = $this->get_default_fields();
		?>
		<div id="hezarfen-checkout-field-editor" class="hezarfen-modern-ui">
			<!-- Header Section -->
			<div class="hezarfen-field-editor-header">
				<div class="hezarfen-header-title">
					<div class="hezarfen-header-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M19 3H5C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M9 9H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M9 13H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M9 17H13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
					<div>
						<h2><?php esc_html_e( 'Checkout Fields Manager', 'hezarfen-for-woocommerce' ); ?></h2>
						<p class="hezarfen-subtitle"><?php esc_html_e( 'Customize your checkout experience with ease', 'hezarfen-for-woocommerce' ); ?></p>
					</div>
				</div>
				<div class="hezarfen-header-actions">
					<button type="button" class="hezarfen-button hezarfen-button-secondary" id="export-fields">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M7 10L12 15L17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M12 15V3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<?php esc_html_e( 'Export', 'hezarfen-for-woocommerce' ); ?>
					</button>
					<button type="button" class="hezarfen-button hezarfen-button-secondary" id="import-fields">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M17 8L12 3L7 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<?php esc_html_e( 'Import', 'hezarfen-for-woocommerce' ); ?>
					</button>
					<button type="button" class="hezarfen-button hezarfen-button-primary" id="add-new-field">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M12 5V19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<?php esc_html_e( 'Add New Field', 'hezarfen-for-woocommerce' ); ?>
					</button>
				</div>
			</div>

			<!-- Stats Overview -->
			<div class="hezarfen-stats-overview">
				<div class="hezarfen-stat-card">
					<div class="hezarfen-stat-icon hezarfen-stat-default">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
					<div class="hezarfen-stat-info">
						<div class="hezarfen-stat-number"><?php echo count( $default_fields ); ?></div>
						<div class="hezarfen-stat-label"><?php esc_html_e( 'Default Fields', 'hezarfen-for-woocommerce' ); ?></div>
					</div>
				</div>
				<div class="hezarfen-stat-card">
					<div class="hezarfen-stat-icon hezarfen-stat-custom">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M12 2L15.09 8.26L22 9L17 14L18.18 21L12 17.77L5.82 21L7 14L2 9L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
					<div class="hezarfen-stat-info">
						<div class="hezarfen-stat-number"><?php echo count( $custom_fields ); ?></div>
						<div class="hezarfen-stat-label"><?php esc_html_e( 'Custom Fields', 'hezarfen-for-woocommerce' ); ?></div>
					</div>
				</div>
				<div class="hezarfen-stat-card">
					<div class="hezarfen-stat-icon hezarfen-stat-total">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M13 2L3 14H12L11 22L21 10H12L13 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
					<div class="hezarfen-stat-info">
						<div class="hezarfen-stat-number"><?php echo count( $default_fields ) + count( $custom_fields ); ?></div>
						<div class="hezarfen-stat-label"><?php esc_html_e( 'Total Fields', 'hezarfen-for-woocommerce' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Unified Fields View -->
			<div class="hezarfen-fields-container">
				<div class="hezarfen-fields-sidebar">
					<div class="hezarfen-sidebar-section">
						<h4><?php esc_html_e( 'Field Sections', 'hezarfen-for-woocommerce' ); ?></h4>
						<div class="hezarfen-section-filters">
							<button type="button" class="hezarfen-section-filter active" data-section="all">
								<span class="hezarfen-filter-icon">🏷️</span>
								<?php esc_html_e( 'All Sections', 'hezarfen-for-woocommerce' ); ?>
							</button>
							<button type="button" class="hezarfen-section-filter" data-section="billing">
								<span class="hezarfen-filter-icon">💳</span>
								<?php esc_html_e( 'Billing', 'hezarfen-for-woocommerce' ); ?>
							</button>
							<button type="button" class="hezarfen-section-filter" data-section="shipping">
								<span class="hezarfen-filter-icon">📦</span>
								<?php esc_html_e( 'Shipping', 'hezarfen-for-woocommerce' ); ?>
							</button>
							<button type="button" class="hezarfen-section-filter" data-section="order">
								<span class="hezarfen-filter-icon">📝</span>
								<?php esc_html_e( 'Order', 'hezarfen-for-woocommerce' ); ?>
							</button>
						</div>
					</div>

					<div class="hezarfen-sidebar-section">
						<h4><?php esc_html_e( 'Field Types', 'hezarfen-for-woocommerce' ); ?></h4>
						<div class="hezarfen-type-filters">
							<button type="button" class="hezarfen-type-filter active" data-type="all">
								<?php esc_html_e( 'All Types', 'hezarfen-for-woocommerce' ); ?>
							</button>
							<button type="button" class="hezarfen-type-filter" data-type="default">
								<?php esc_html_e( 'Default', 'hezarfen-for-woocommerce' ); ?>
							</button>
							<button type="button" class="hezarfen-type-filter" data-type="custom">
								<?php esc_html_e( 'Custom', 'hezarfen-for-woocommerce' ); ?>
							</button>
						</div>
					</div>
				</div>

				<div class="hezarfen-fields-main">
					<div class="hezarfen-fields-header-main">
						<div class="hezarfen-search-container">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
								<path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<input type="text" id="hezarfen-field-search" placeholder="<?php esc_attr_e( 'Search fields...', 'hezarfen-for-woocommerce' ); ?>">
						</div>
						<div class="hezarfen-view-options">
							<button type="button" class="hezarfen-view-btn active" data-view="grid" title="<?php esc_attr_e( 'Grid View', 'hezarfen-for-woocommerce' ); ?>">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<rect x="3" y="3" width="7" height="7" stroke="currentColor" stroke-width="2"/>
									<rect x="14" y="3" width="7" height="7" stroke="currentColor" stroke-width="2"/>
									<rect x="14" y="14" width="7" height="7" stroke="currentColor" stroke-width="2"/>
									<rect x="3" y="14" width="7" height="7" stroke="currentColor" stroke-width="2"/>
								</svg>
							</button>
							<button type="button" class="hezarfen-view-btn" data-view="list" title="<?php esc_attr_e( 'List View', 'hezarfen-for-woocommerce' ); ?>">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<line x1="8" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<line x1="8" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<line x1="8" y1="18" x2="21" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<line x1="3" y1="6" x2="3.01" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<line x1="3" y1="12" x2="3.01" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<line x1="3" y1="18" x2="3.01" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</button>
						</div>
					</div>

					<div class="hezarfen-fields-list hezarfen-grid-view" id="hezarfen-all-fields">
						<?php $this->render_unified_fields_view( $default_fields, $custom_fields ); ?>
					</div>
				</div>
			</div>

			<!-- Enhanced Field Editor Modal -->
			<div id="field-editor-modal" class="hezarfen-modal hezarfen-modal-enhanced" style="display: none;">
				<div class="hezarfen-modal-content">
					<div class="hezarfen-modal-header">
						<div class="hezarfen-modal-title">
							<div class="hezarfen-modal-icon">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<path d="M18.5 2.49998C18.8978 2.10216 19.4374 1.87866 20 1.87866C20.5626 1.87866 21.1022 2.10216 21.5 2.49998C21.8978 2.89781 22.1213 3.43737 22.1213 3.99998C22.1213 4.56259 21.8978 5.10216 21.5 5.49998L12 15L8 16L9 12L18.5 2.49998Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</div>
							<h3 id="modal-title"><?php esc_html_e( 'Add New Field', 'hezarfen-for-woocommerce' ); ?></h3>
						</div>
						<button type="button" class="hezarfen-modal-close">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>
					</div>
					
					<div class="hezarfen-modal-body">
						<form id="field-editor-form" class="hezarfen-enhanced-form">
							<input type="hidden" id="field-id" name="field_id" value="">
							<input type="hidden" id="is-default-field" name="is_default" value="0">
							
							<!-- Basic Information -->
							<div class="hezarfen-form-section">
								<h4 class="hezarfen-form-section-title">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
										<path d="M19.4 15C19.2669 15.3016 19.2272 15.6362 19.286 15.9606C19.3448 16.285 19.4995 16.5843 19.73 16.82L19.79 16.88C19.976 17.0657 20.1235 17.2863 20.2241 17.5291C20.3248 17.7719 20.3766 18.0322 20.3766 18.295C20.3766 18.5578 20.3248 18.8181 20.2241 19.0609C20.1235 19.3037 19.976 19.5243 19.79 19.71C19.6043 19.896 19.3837 20.0435 19.1409 20.1441C18.8981 20.2448 18.6378 20.2966 18.375 20.2966C18.1122 20.2966 17.8519 20.2448 17.6091 20.1441C17.3663 20.0435 17.1457 19.896 16.96 19.71L16.9 19.65C16.6643 19.4195 16.365 19.2648 16.0406 19.206C15.7162 19.1472 15.3816 19.1869 15.08 19.32C14.7842 19.4468 14.532 19.6572 14.3543 19.9255C14.1766 20.1938 14.0813 20.5082 14.08 20.83V21C14.08 21.5304 13.8693 22.0391 13.4942 22.4142C13.1191 22.7893 12.6104 23 12.08 23C11.5496 23 11.0409 22.7893 10.6658 22.4142C10.2907 22.0391 10.08 21.5304 10.08 21V20.91C10.0723 20.579 9.96512 20.258 9.77251 19.9887C9.5799 19.7194 9.31074 19.5143 9 19.4C8.69838 19.2669 8.36381 19.2272 8.03941 19.286C7.71502 19.3448 7.41568 19.4995 7.18 19.73L7.12 19.79C6.93425 19.976 6.71368 20.1235 6.47088 20.2241C6.22808 20.3248 5.96783 20.3766 5.705 20.3766C5.44217 20.3766 5.18192 20.3248 4.93912 20.2241C4.69632 20.1235 4.47575 19.976 4.29 19.79C4.10405 19.6043 3.95653 19.3837 3.85588 19.1409C3.75523 18.8981 3.70343 18.6378 3.70343 18.375C3.70343 18.1122 3.75523 17.8519 3.85588 17.6091C3.95653 17.3663 4.10405 17.1457 4.29 16.96L4.35 16.9C4.58054 16.6643 4.73519 16.365 4.794 16.0406C4.85282 15.7162 4.81312 15.3816 4.68 15.08C4.55324 14.7842 4.34276 14.532 4.07447 14.3543C3.80618 14.1766 3.49179 14.0813 3.17 14.08H3C2.46957 14.08 1.96086 13.8693 1.58579 13.4942C1.21071 13.1191 1 12.6104 1 12.08C1 11.5496 1.21071 11.0409 1.58579 10.6658C1.96086 10.2907 2.46957 10.08 3 10.08H3.09C3.42099 10.0723 3.742 9.96512 4.0113 9.77251C4.28059 9.5799 4.48572 9.31074 4.6 9C4.73312 8.69838 4.77282 8.36381 4.714 8.03941C4.65519 7.71502 4.50054 7.41568 4.27 7.18L4.21 7.12C4.02405 6.93425 3.87653 6.71368 3.77588 6.47088C3.67523 6.22808 3.62343 5.96783 3.62343 5.705C3.62343 5.44217 3.67523 5.18192 3.77588 4.93912C3.87653 4.69632 4.02405 4.47575 4.21 4.29C4.39575 4.10405 4.61632 3.95653 4.85912 3.85588C5.10192 3.75523 5.36217 3.70343 5.625 3.70343C5.88783 3.70343 6.14808 3.75523 6.39088 3.85588C6.63368 3.95653 6.85425 4.10405 7.04 4.29L7.1 4.35C7.33568 4.58054 7.63502 4.73519 7.95941 4.794C8.28381 4.85282 8.61838 4.81312 8.92 4.68H9C9.29577 4.55324 9.54802 4.34276 9.72569 4.07447C9.90337 3.80618 9.99872 3.49179 10 3.17V3C10 2.46957 10.2107 1.96086 10.5858 1.58579C10.9609 1.21071 11.4696 1 12 1C12.5304 1 13.0391 1.21071 13.4142 1.58579C13.7893 1.96086 14 2.46957 14 3V3.09C14.0013 3.41179 14.0966 3.72618 14.2743 3.99447C14.452 4.26276 14.7042 4.47324 15 4.6C15.3016 4.73312 15.6362 4.77282 15.9606 4.714C16.285 4.65519 16.5843 4.50054 16.82 4.27L16.88 4.21C17.0657 4.02405 17.2863 3.87653 17.5291 3.77588C17.7719 3.67523 18.0322 3.62343 18.295 3.62343C18.5578 3.62343 18.8181 3.67523 19.0609 3.77588C19.3037 3.87653 19.5243 4.02405 19.71 4.21C19.896 4.39575 20.0435 4.61632 20.1441 4.85912C20.2448 5.10192 20.2966 5.36217 20.2966 5.625C20.2966 5.88783 20.2448 6.14808 20.1441 6.39088C20.0435 6.63368 19.896 6.85425 19.71 7.04L19.65 7.1C19.4195 7.33568 19.2648 7.63502 19.206 7.95941C19.1472 8.28381 19.1869 8.61838 19.32 8.92V9C19.4468 9.29577 19.6572 9.54802 19.9255 9.72569C20.1938 9.90337 20.5082 9.99872 20.83 10H21C21.5304 10 22.0391 10.2107 22.4142 10.5858C22.7893 10.9609 23 11.4696 23 12C23 12.5304 22.7893 13.0391 22.4142 13.4142C22.0391 13.7893 21.5304 14 21 14H20.91C20.5882 14.0013 20.2738 14.0966 20.0055 14.2743C19.7372 14.452 19.5268 14.7042 19.4 15V15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
									<?php esc_html_e( 'Basic Information', 'hezarfen-for-woocommerce' ); ?>
								</h4>
								
								<div class="hezarfen-form-grid">
									<div class="hezarfen-form-field">
										<label for="field-name" class="hezarfen-form-label">
											<?php esc_html_e( 'Field Name', 'hezarfen-for-woocommerce' ); ?>
											<span class="hezarfen-required">*</span>
										</label>
										<input type="text" id="field-name" name="field_name" class="hezarfen-form-input" required>
										<span class="hezarfen-form-help"><?php esc_html_e( 'Used internally (e.g., custom_company_size)', 'hezarfen-for-woocommerce' ); ?></span>
									</div>

									<div class="hezarfen-form-field">
										<label for="field-label" class="hezarfen-form-label">
											<?php esc_html_e( 'Field Label', 'hezarfen-for-woocommerce' ); ?>
											<span class="hezarfen-required">*</span>
										</label>
										<input type="text" id="field-label" name="field_label" class="hezarfen-form-input" required>
										<span class="hezarfen-form-help"><?php esc_html_e( 'Displayed to customers on checkout', 'hezarfen-for-woocommerce' ); ?></span>
									</div>
								</div>

								<div class="hezarfen-form-grid">
									<div class="hezarfen-form-field">
										<label for="field-type" class="hezarfen-form-label">
											<?php esc_html_e( 'Field Type', 'hezarfen-for-woocommerce' ); ?>
											<span class="hezarfen-required">*</span>
										</label>
										<select id="field-type" name="field_type" class="hezarfen-form-select" required>
											<option value=""><?php esc_html_e( 'Select field type', 'hezarfen-for-woocommerce' ); ?></option>
											<?php foreach ( $this->field_types as $type => $label ) : ?>
												<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>

									<div class="hezarfen-form-field">
										<label for="field-section" class="hezarfen-form-label">
											<?php esc_html_e( 'Section', 'hezarfen-for-woocommerce' ); ?>
											<span class="hezarfen-required">*</span>
										</label>
										<select id="field-section" name="field_section" class="hezarfen-form-select" required>
											<option value=""><?php esc_html_e( 'Select section', 'hezarfen-for-woocommerce' ); ?></option>
											<?php foreach ( $this->field_sections as $section => $label ) : ?>
												<option value="<?php echo esc_attr( $section ); ?>"><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>

								<div class="hezarfen-form-field">
									<label for="field-placeholder" class="hezarfen-form-label"><?php esc_html_e( 'Placeholder Text', 'hezarfen-for-woocommerce' ); ?></label>
									<input type="text" id="field-placeholder" name="field_placeholder" class="hezarfen-form-input">
									<span class="hezarfen-form-help"><?php esc_html_e( 'Hint text shown inside the field', 'hezarfen-for-woocommerce' ); ?></span>
								</div>
							</div>

							<!-- Field Options -->
							<div class="hezarfen-form-section" id="field-options-section" style="display: none;">
								<h4 class="hezarfen-form-section-title">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										<path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
									<?php esc_html_e( 'Field Options', 'hezarfen-for-woocommerce' ); ?>
								</h4>
								<div class="hezarfen-form-field">
									<label for="field-options" class="hezarfen-form-label"><?php esc_html_e( 'Options', 'hezarfen-for-woocommerce' ); ?></label>
									<textarea id="field-options" name="field_options" rows="4" class="hezarfen-form-textarea" placeholder="<?php esc_attr_e( 'Enter each option on a new line, or use key|value format', 'hezarfen-for-woocommerce' ); ?>"></textarea>
									<span class="hezarfen-form-help"><?php esc_html_e( 'One option per line. Use "key|label" format for custom values.', 'hezarfen-for-woocommerce' ); ?></span>
								</div>
							</div>

							<!-- Field Settings -->
							<div class="hezarfen-form-section">
								<h4 class="hezarfen-form-section-title">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M12 15L12.01 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										<path d="M12 12V9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										<path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
									<?php esc_html_e( 'Field Settings', 'hezarfen-for-woocommerce' ); ?>
								</h4>
								
								<div class="hezarfen-form-grid">
									<div class="hezarfen-form-field">
										<label for="field-priority" class="hezarfen-form-label"><?php esc_html_e( 'Priority', 'hezarfen-for-woocommerce' ); ?></label>
										<input type="number" id="field-priority" name="field_priority" value="10" min="1" max="100" class="hezarfen-form-input">
										<span class="hezarfen-form-help"><?php esc_html_e( 'Display order (1 = first, 100 = last)', 'hezarfen-for-woocommerce' ); ?></span>
									</div>
								</div>

								<div class="hezarfen-form-checkboxes">
									<label class="hezarfen-checkbox-label">
										<input type="checkbox" id="field-required" name="field_required" value="1" class="hezarfen-form-checkbox">
										<span class="hezarfen-checkbox-custom"></span>
										<span class="hezarfen-checkbox-text">
											<?php esc_html_e( 'Required field', 'hezarfen-for-woocommerce' ); ?>
											<small><?php esc_html_e( 'Customer must fill this field', 'hezarfen-for-woocommerce' ); ?></small>
										</span>
									</label>

									<label class="hezarfen-checkbox-label">
										<input type="checkbox" id="field-enabled" name="field_enabled" value="1" checked class="hezarfen-form-checkbox">
										<span class="hezarfen-checkbox-custom"></span>
										<span class="hezarfen-checkbox-text">
											<?php esc_html_e( 'Enable field', 'hezarfen-for-woocommerce' ); ?>
											<small><?php esc_html_e( 'Show this field on checkout', 'hezarfen-for-woocommerce' ); ?></small>
										</span>
									</label>
								</div>
							</div>

							<!-- Advanced Settings -->
							<div class="hezarfen-form-section hezarfen-form-section-collapsible" data-collapsed="true">
								<h4 class="hezarfen-form-section-title hezarfen-form-section-toggle">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M12 2L15.09 8.26L22 9L17 14L18.18 21L12 17.77L5.82 21L7 14L2 9L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
									<?php esc_html_e( 'Advanced Settings', 'hezarfen-for-woocommerce' ); ?>
									<svg class="hezarfen-section-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</h4>
								<div class="hezarfen-form-section-content">
									<div class="hezarfen-form-field">
										<label for="field-show-for-countries" class="hezarfen-form-label"><?php esc_html_e( 'Show only for countries', 'hezarfen-for-woocommerce' ); ?></label>
										<select id="field-show-for-countries" name="field_show_for_countries" multiple class="hezarfen-form-select-multiple">
											<?php
											$countries = WC()->countries->get_countries();
											foreach ( $countries as $code => $name ) {
												echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $name ) . '</option>';
											}
											?>
										</select>
										<span class="hezarfen-form-help"><?php esc_html_e( 'Leave empty to show for all countries. Hold Ctrl/Cmd to select multiple.', 'hezarfen-for-woocommerce' ); ?></span>
									</div>
								</div>
							</div>
						</form>
					</div>
					
					<div class="hezarfen-modal-footer">
						<button type="button" class="hezarfen-button hezarfen-button-secondary" id="cancel-field">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<?php esc_html_e( 'Cancel', 'hezarfen-for-woocommerce' ); ?>
						</button>
						<button type="button" class="hezarfen-button hezarfen-button-primary" id="save-field">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H16L21 8V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M17 21V13H7V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M7 3V8H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<?php esc_html_e( 'Save Field', 'hezarfen-for-woocommerce' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Import Modal -->
			<div id="import-modal" class="hezarfen-modal" style="display: none;">
				<div class="hezarfen-modal-content">
					<div class="hezarfen-modal-header">
						<h3><?php esc_html_e( 'Import Fields', 'hezarfen-for-woocommerce' ); ?></h3>
						<span class="hezarfen-modal-close">&times;</span>
					</div>
					<div class="hezarfen-modal-body">
						<form id="import-form" enctype="multipart/form-data">
							<div class="hezarfen-form-row">
								<label for="import-file"><?php esc_html_e( 'Select JSON file to import', 'hezarfen-for-woocommerce' ); ?></label>
								<input type="file" id="import-file" name="import_file" accept=".json" required>
								<p class="description"><?php esc_html_e( 'Select a JSON file exported from the checkout field editor.', 'hezarfen-for-woocommerce' ); ?></p>
							</div>
							<div class="hezarfen-form-row">
								<label for="import-mode">
									<input type="checkbox" id="import-mode" name="import_mode" value="replace">
									<?php esc_html_e( 'Replace existing fields (will delete all current fields)', 'hezarfen-for-woocommerce' ); ?>
								</label>
							</div>
						</form>
					</div>
					<div class="hezarfen-modal-footer">
						<button type="button" class="button" id="cancel-import"><?php esc_html_e( 'Cancel', 'hezarfen-for-woocommerce' ); ?></button>
						<button type="button" class="button button-primary" id="process-import"><?php esc_html_e( 'Import', 'hezarfen-for-woocommerce' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		
		<script type="text/javascript">
		// Localize script data
		var hezarfen_checkout_field_editor = <?php echo wp_json_encode( array(
			'ajax_url'       => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'hezarfen_checkout_field_editor' ),
			'field_types'    => $this->field_types,
			'sections'       => $this->field_sections,
			'custom_fields_data' => $this->get_custom_fields(),
			'default_fields_data' => $this->get_default_fields(),
			'confirm_delete' => __( 'Are you sure you want to delete this field?', 'hezarfen-for-woocommerce' ),
			'confirm_reset'  => __( 'Are you sure you want to reset this field to default settings?', 'hezarfen-for-woocommerce' ),
			'add_field_title' => __( 'Add New Field', 'hezarfen-for-woocommerce' ),
			'edit_field_title' => __( 'Edit Field', 'hezarfen-for-woocommerce' ),
			'saving'         => __( 'Saving...', 'hezarfen-for-woocommerce' ),
			'save_field'     => __( 'Save Field', 'hezarfen-for-woocommerce' ),
		) ); ?>;
		</script>
		
		<script src="<?php echo WC_HEZARFEN_UYGULAMA_URL; ?>assets/js/admin/checkout-field-editor.js"></script>
		<link rel="stylesheet" href="<?php echo WC_HEZARFEN_UYGULAMA_URL; ?>assets/css/admin/checkout-field-editor.css">
		<?php
	}

	/**
	 * Render unified fields view
	 */
	private function render_unified_fields_view( $default_fields, $custom_fields ) {
		// Combine and organize all fields
		$all_fields = array();
		
		// Add default fields
		foreach ( $default_fields as $field_id => $field ) {
			$field['is_default'] = true;
			$field['field_id'] = $field_id;
			$all_fields[] = $field;
		}
		
		// Add custom fields
		foreach ( $custom_fields as $field_id => $field ) {
			$field['is_default'] = false;
			$field['field_id'] = $field_id;
			$all_fields[] = $field;
		}
		
		if ( empty( $all_fields ) ) {
			?>
			<div class="hezarfen-no-fields-modern">
				<div class="hezarfen-no-fields-icon">
					<svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						<path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
				<h3><?php esc_html_e( 'No Fields Found', 'hezarfen-for-woocommerce' ); ?></h3>
				<p><?php esc_html_e( 'Start by adding your first custom field or customizing existing ones.', 'hezarfen-for-woocommerce' ); ?></p>
				<button type="button" class="hezarfen-button hezarfen-button-primary" id="add-first-field">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 5V19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						<path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
					<?php esc_html_e( 'Add Your First Field', 'hezarfen-for-woocommerce' ); ?>
				</button>
			</div>
			<?php
			return;
		}
		
		// Group fields by section for better organization
		$sections = array( 'billing' => array(), 'shipping' => array(), 'order' => array() );
		foreach ( $all_fields as $field ) {
			$section = $field['section'] ?? 'billing';
			$sections[$section][] = $field;
		}
		
		foreach ( $sections as $section_name => $section_fields ) {
			if ( empty( $section_fields ) ) {
				continue;
			}
			?>
			<div class="hezarfen-section-group" data-section="<?php echo esc_attr( $section_name ); ?>">
				<div class="hezarfen-section-header">
					<div class="hezarfen-section-info">
						<span class="hezarfen-section-icon">
							<?php
							switch ( $section_name ) {
								case 'billing':
									echo '💳';
									break;
								case 'shipping':
									echo '📦';
									break;
								case 'order':
									echo '📝';
									break;
								default:
									echo '🏷️';
							}
							?>
						</span>
						<h3><?php echo esc_html( ucfirst( $section_name ) . ' Fields' ); ?></h3>
						<span class="hezarfen-field-count"><?php echo count( $section_fields ); ?> <?php esc_html_e( 'fields', 'hezarfen-for-woocommerce' ); ?></span>
					</div>
					<button type="button" class="hezarfen-section-toggle" data-section="<?php echo esc_attr( $section_name ); ?>">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</button>
				</div>
				<div class="hezarfen-section-fields" id="sortable-<?php echo esc_attr( $section_name ); ?>-fields">
					<?php foreach ( $section_fields as $field ) : ?>
						<?php $this->render_modern_field_card( $field ); ?>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Render modern field card
	 */
	private function render_modern_field_card( $field ) {
		$field_id = $field['field_id'];
		$is_default = $field['is_default'];
		$field_type = $field['type'] ?? 'text';
		$field_type_label = isset( $this->field_types[ $field_type ] ) ? $this->field_types[ $field_type ] : $field_type;
		$section_label = isset( $this->field_sections[ $field['section'] ] ) ? $this->field_sections[ $field['section'] ] : $field['section'];
		?>
		<div class="hezarfen-field-card <?php echo $is_default ? 'is-default' : 'is-custom'; ?>" 
			 data-field-id="<?php echo esc_attr( $field_id ); ?>" 
			 data-is-default="<?php echo $is_default ? '1' : '0'; ?>"
			 data-section="<?php echo esc_attr( $field['section'] ?? 'billing' ); ?>"
			 data-type="<?php echo $is_default ? 'default' : 'custom'; ?>"
			 data-field-type="<?php echo esc_attr( $field_type ); ?>">
			
			<?php if ( ! $is_default ) : ?>
				<div class="hezarfen-field-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'hezarfen-for-woocommerce' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<circle cx="9" cy="12" r="1" fill="currentColor"/>
						<circle cx="9" cy="5" r="1" fill="currentColor"/>
						<circle cx="9" cy="19" r="1" fill="currentColor"/>
						<circle cx="15" cy="12" r="1" fill="currentColor"/>
						<circle cx="15" cy="5" r="1" fill="currentColor"/>
						<circle cx="15" cy="19" r="1" fill="currentColor"/>
					</svg>
				</div>
			<?php endif; ?>

			<div class="hezarfen-field-header">
				<div class="hezarfen-field-icon">
					<?php $this->render_field_type_icon( $field_type ); ?>
				</div>
				<div class="hezarfen-field-title">
					<h4><?php echo esc_html( $field['label'] ); ?></h4>
					<span class="hezarfen-field-name"><?php echo esc_html( $field['name'] ?? $field_id ); ?></span>
				</div>
				<div class="hezarfen-field-status">
					<?php if ( $is_default ) : ?>
						<span class="hezarfen-badge hezarfen-badge-default"><?php esc_html_e( 'Default', 'hezarfen-for-woocommerce' ); ?></span>
					<?php else : ?>
						<span class="hezarfen-badge hezarfen-badge-custom"><?php esc_html_e( 'Custom', 'hezarfen-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<div class="hezarfen-field-meta">
				<div class="hezarfen-field-meta-item">
					<span class="hezarfen-meta-label"><?php esc_html_e( 'Type:', 'hezarfen-for-woocommerce' ); ?></span>
					<span class="hezarfen-meta-value"><?php echo esc_html( $field_type_label ); ?></span>
				</div>
				<?php if ( isset( $field['priority'] ) ) : ?>
					<div class="hezarfen-field-meta-item">
						<span class="hezarfen-meta-label"><?php esc_html_e( 'Priority:', 'hezarfen-for-woocommerce' ); ?></span>
						<span class="hezarfen-meta-value"><?php echo esc_html( $field['priority'] ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( isset( $field['required'] ) && $field['required'] ) : ?>
					<div class="hezarfen-field-meta-item">
						<span class="hezarfen-badge hezarfen-badge-required"><?php esc_html_e( 'Required', 'hezarfen-for-woocommerce' ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( isset( $field['enabled'] ) && ! $field['enabled'] ) : ?>
					<div class="hezarfen-field-meta-item">
						<span class="hezarfen-badge hezarfen-badge-disabled"><?php esc_html_e( 'Disabled', 'hezarfen-for-woocommerce' ); ?></span>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $field['placeholder'] ) ) : ?>
				<div class="hezarfen-field-preview">
					<span class="hezarfen-preview-label"><?php esc_html_e( 'Placeholder:', 'hezarfen-for-woocommerce' ); ?></span>
					<span class="hezarfen-preview-text"><?php echo esc_html( $field['placeholder'] ); ?></span>
				</div>
			<?php endif; ?>

			<div class="hezarfen-field-actions">
				<button type="button" class="hezarfen-button hezarfen-button-small hezarfen-button-secondary edit-field" data-field-id="<?php echo esc_attr( $field_id ); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						<path d="M18.5 2.49998C18.8978 2.10216 19.4374 1.87866 20 1.87866C20.5626 1.87866 21.1022 2.10216 21.5 2.49998C21.8978 2.89781 22.1213 3.43737 22.1213 3.99998C22.1213 4.56259 21.8978 5.10216 21.5 5.49998L12 15L8 16L9 12L18.5 2.49998Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
					<?php esc_html_e( 'Edit', 'hezarfen-for-woocommerce' ); ?>
				</button>
				<?php if ( ! $is_default ) : ?>
					<button type="button" class="hezarfen-button hezarfen-button-small hezarfen-button-danger delete-field" data-field-id="<?php echo esc_attr( $field_id ); ?>">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<?php esc_html_e( 'Delete', 'hezarfen-for-woocommerce' ); ?>
					</button>
				<?php else : ?>
					<button type="button" class="hezarfen-button hezarfen-button-small hezarfen-button-warning reset-field" data-field-id="<?php echo esc_attr( $field_id ); ?>">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M1 4V10H7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M3.51 15C4.13 17.09 5.81 18.77 7.91 19.42C10.01 20.07 12.33 19.54 14 18.12C15.67 16.7 16.38 14.57 15.84 12.5C15.3 10.43 13.57 8.85 11.43 8.26C9.29 7.67 7.04 8.15 5.34 9.5L1 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<?php esc_html_e( 'Reset', 'hezarfen-for-woocommerce' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render field type icon
	 */
	private function render_field_type_icon( $field_type ) {
		$icons = array(
			'text' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 7V4H20V7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 20H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 4V20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			'textarea' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"/><line x1="7" y1="9" x2="17" y2="9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="7" y1="13" x2="17" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
			'select' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="2"/><path d="M7 10L12 15L17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			'radio' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="3" fill="currentColor"/><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/></svg>',
			'checkbox' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/><path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			'number' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 1V23" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6312 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6312 13.6815 18 14.5717 18 15.5C18 16.4283 17.6312 17.3185 16.9749 17.9749C16.3185 18.6312 15.4283 19 14.5 19H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			'email' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 6L12 13L2 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			'tel' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22 16.92V19.92C22.0011 20.1985 21.9441 20.4742 21.8325 20.7293C21.7209 20.9845 21.5573 21.2136 21.3521 21.4019C21.1468 21.5901 20.9046 21.7335 20.6407 21.8227C20.3769 21.9119 20.0974 21.9451 19.82 21.92C16.7428 21.5856 13.787 20.5341 11.19 18.85C8.77382 17.3147 6.72533 15.2662 5.18999 12.85C3.49997 10.2412 2.44824 7.27099 2.11999 4.18C2.095 3.90347 2.12787 3.62476 2.21649 3.36162C2.30512 3.09849 2.44756 2.85669 2.63476 2.65162C2.82196 2.44655 3.0498 2.28271 3.30379 2.17052C3.55777 2.05833 3.83233 2.00026 4.10999 2H7.10999C7.59344 1.99522 8.06456 2.16708 8.43321 2.48353C8.80186 2.79999 9.04207 3.23945 9.10999 3.72C9.23662 4.68007 9.47144 5.62273 9.80999 6.53C9.94454 6.88792 9.97366 7.27691 9.8939 7.65088C9.81415 8.02485 9.62886 8.36811 9.35999 8.64L8.08999 9.91C9.51355 12.4135 11.5865 14.4864 14.09 15.91L15.36 14.64C15.6319 14.3711 15.9751 14.1858 16.3491 14.1061C16.7231 14.0263 17.1121 14.0555 17.47 14.19C18.3773 14.5286 19.3199 14.7634 20.28 14.89C20.7658 14.9585 21.2094 15.2032 21.5265 15.5775C21.8437 15.9518 22.0122 16.4296 22 16.92Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			'date' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/><line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		);

		echo $icons[ $field_type ] ?? $icons['text'];
	}

	/**
	 * Render field item (legacy method for compatibility)
	 */
	private function render_field_item( $field_id, $field, $is_default = false ) {
		// This method is kept for compatibility but now uses the modern card rendering
		$field['field_id'] = $field_id;
		$field['is_default'] = $is_default;
		$this->render_modern_field_card( $field );
	}

	/**
	 * Get custom fields
	 *
	 * @return array
	 */
	public function get_custom_fields() {
		$fields = get_option( 'hezarfen_checkout_custom_fields', array() );
		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Get default WooCommerce fields
	 *
	 * @return array
	 */
	public function get_default_fields() {
		$default_fields = array();
		
		// Get default WooCommerce fields
		$wc_countries = WC()->countries;
		$fields = $wc_countries->get_address_fields();
		
		// Billing fields
		foreach ( $fields as $key => $field ) {
			$default_fields['billing_' . $key] = array_merge( $field, array(
				'section' => 'billing',
				'name' => $key,
				'type' => $field['type'] ?? 'text',
				'is_default' => true,
				'original_key' => 'billing_' . $key,
				'enabled' => true,
				'priority' => $field['priority'] ?? 10,
			));
		}
		
		// Shipping fields
		foreach ( $fields as $key => $field ) {
			$default_fields['shipping_' . $key] = array_merge( $field, array(
				'section' => 'shipping',
				'name' => $key,
				'type' => $field['type'] ?? 'text',
				'is_default' => true,
				'original_key' => 'shipping_' . $key,
				'enabled' => true,
				'priority' => $field['priority'] ?? 10,
			));
		}
		
		// Additional common fields
		$additional_fields = array(
			'billing_email' => array(
				'label' => __( 'Email address', 'woocommerce' ),
				'type' => 'email',
				'required' => true,
				'section' => 'billing',
				'name' => 'email',
				'is_default' => true,
				'original_key' => 'billing_email',
			),
			'billing_phone' => array(
				'label' => __( 'Phone', 'woocommerce' ),
				'type' => 'tel',
				'required' => false,
				'section' => 'billing',
				'name' => 'phone',
				'is_default' => true,
				'original_key' => 'billing_phone',
			),
			'order_comments' => array(
				'label' => __( 'Order notes', 'woocommerce' ),
				'type' => 'textarea',
				'required' => false,
				'section' => 'order',
				'name' => 'order_comments',
				'is_default' => true,
				'original_key' => 'order_comments',
			),
		);
		
		$default_fields = array_merge( $default_fields, $additional_fields );
		
		// Get any customizations from options
		$customized_fields = get_option( 'hezarfen_checkout_default_fields', array() );
		
		// Merge customizations
		foreach ( $customized_fields as $field_id => $customizations ) {
			if ( isset( $default_fields[$field_id] ) ) {
				$default_fields[$field_id] = array_merge( $default_fields[$field_id], $customizations );
			}
		}
		
		return $default_fields;
	}

	/**
	 * Save custom fields configuration
	 *
	 * @param array $fields Fields array.
	 */
	public function save_custom_fields_config( $fields ) {
		update_option( 'hezarfen_checkout_custom_fields', $fields );
	}

	/**
	 * Save default fields customizations
	 *
	 * @param array $fields Fields array.
	 */
	public function save_default_fields_config( $fields ) {
		update_option( 'hezarfen_checkout_default_fields', $fields );
	}

	/**
	 * AJAX save field
	 */
	public function ajax_save_field() {
		check_ajax_referer( 'hezarfen_checkout_field_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		// Validate required fields
		$required_fields = array( 'field_name', 'field_label', 'field_type', 'field_section' );
		foreach ( $required_fields as $required_field ) {
			if ( empty( $_POST[ $required_field ] ) ) {
				wp_send_json_error( array(
					'message' => sprintf( __( '%s is required.', 'hezarfen-for-woocommerce' ), str_replace( 'field_', '', $required_field ) ),
				) );
			}
		}

		// Validate field name (alphanumeric and underscores only)
		$field_name = sanitize_text_field( $_POST['field_name'] );
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $field_name ) ) {
			wp_send_json_error( array(
				'message' => __( 'Field name can only contain letters, numbers, and underscores.', 'hezarfen-for-woocommerce' ),
			) );
		}

		// Validate field type
		$field_type = sanitize_text_field( $_POST['field_type'] );
		if ( ! array_key_exists( $field_type, $this->field_types ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid field type.', 'hezarfen-for-woocommerce' ),
			) );
		}

		// Validate section
		$field_section = sanitize_text_field( $_POST['field_section'] );
		if ( ! array_key_exists( $field_section, $this->field_sections ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid field section.', 'hezarfen-for-woocommerce' ),
			) );
		}

		// Validate priority
		$field_priority = intval( $_POST['field_priority'] );
		if ( $field_priority < 1 || $field_priority > 100 ) {
			wp_send_json_error( array(
				'message' => __( 'Priority must be between 1 and 100.', 'hezarfen-for-woocommerce' ),
			) );
		}

		// Handle countries selection
		$show_for_countries = array();
		if ( ! empty( $_POST['field_show_for_countries'] ) && is_array( $_POST['field_show_for_countries'] ) ) {
			$show_for_countries = array_map( 'sanitize_text_field', $_POST['field_show_for_countries'] );
		}

		$field_data = array(
			'name'                 => $field_name,
			'label'                => sanitize_text_field( $_POST['field_label'] ),
			'type'                 => $field_type,
			'section'              => $field_section,
			'placeholder'          => sanitize_text_field( $_POST['field_placeholder'] ),
			'options'              => sanitize_textarea_field( $_POST['field_options'] ),
			'required'             => isset( $_POST['field_required'] ) ? true : false,
			'enabled'              => isset( $_POST['field_enabled'] ) ? true : false,
			'priority'             => $field_priority,
			'show_for_countries'   => $show_for_countries,
		);

		$field_id = sanitize_text_field( $_POST['field_id'] );
		$is_default = isset( $_POST['is_default'] ) && $_POST['is_default'] === '1';

		if ( $is_default ) {
			// Handle default field editing
			$default_fields_customizations = get_option( 'hezarfen_checkout_default_fields', array() );
			$default_fields_customizations[ $field_id ] = $field_data;
			$this->save_default_fields_config( $default_fields_customizations );
		} else {
			// Handle custom field creation/editing
			$fields = $this->get_custom_fields();

			if ( empty( $field_id ) ) {
				$field_id = 'hezarfen_' . $field_data['section'] . '_' . $field_data['name'];
			}

			// Check for duplicate field names (excluding current field when editing)
			$existing_field_id = 'hezarfen_' . $field_data['section'] . '_' . $field_data['name'];
			if ( $existing_field_id !== $field_id && isset( $fields[ $existing_field_id ] ) ) {
				wp_send_json_error( array(
					'message' => __( 'A field with this name already exists in the selected section.', 'hezarfen-for-woocommerce' ),
				) );
			}

			$fields[ $field_id ] = $field_data;
			$this->save_custom_fields_config( $fields );
		}

		wp_send_json_success( array(
			'message' => __( 'Field saved successfully.', 'hezarfen-for-woocommerce' ),
			'field_id' => $field_id,
		) );
	}

	/**
	 * AJAX delete field
	 */
	public function ajax_delete_field() {
		check_ajax_referer( 'hezarfen_checkout_field_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$field_id = sanitize_text_field( $_POST['field_id'] );
		$fields = $this->get_custom_fields();

		if ( isset( $fields[ $field_id ] ) ) {
			unset( $fields[ $field_id ] );
			$this->save_custom_fields_config( $fields );
			wp_send_json_success( array(
				'message' => __( 'Field deleted successfully.', 'hezarfen-for-woocommerce' ),
			) );
		}

		wp_send_json_error( array(
			'message' => __( 'Field not found.', 'hezarfen-for-woocommerce' ),
		) );
	}

	/**
	 * AJAX reset default field
	 */
	public function ajax_reset_field() {
		check_ajax_referer( 'hezarfen_checkout_field_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$field_id = sanitize_text_field( $_POST['field_id'] );
		$customizations = get_option( 'hezarfen_checkout_default_fields', array() );

		if ( isset( $customizations[ $field_id ] ) ) {
			unset( $customizations[ $field_id ] );
			$this->save_default_fields_config( $customizations );
			wp_send_json_success( array(
				'message' => __( 'Field reset to default successfully.', 'hezarfen-for-woocommerce' ),
			) );
		}

		wp_send_json_error( array(
			'message' => __( 'Field not found or already at default.', 'hezarfen-for-woocommerce' ),
		) );
	}

	/**
	 * AJAX reorder fields
	 */
	public function ajax_reorder_fields() {
		check_ajax_referer( 'hezarfen_checkout_field_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$field_order = array_map( 'sanitize_text_field', $_POST['field_order'] );
		$fields = $this->get_custom_fields();
		$reordered_fields = array();

		foreach ( $field_order as $field_id ) {
			if ( isset( $fields[ $field_id ] ) ) {
				$reordered_fields[ $field_id ] = $fields[ $field_id ];
			}
		}

		$this->save_custom_fields_config( $reordered_fields );
		wp_send_json_success();
	}

	/**
	 * Add custom fields to checkout and modify default fields
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function add_custom_fields( $fields ) {
		$custom_fields = $this->get_custom_fields();
		$default_field_customizations = get_option( 'hezarfen_checkout_default_fields', array() );

		// Get current customer country for conditional logic
		$customer_country = '';
		if ( WC()->customer ) {
			$customer_country = WC()->customer->get_billing_country();
			if ( empty( $customer_country ) ) {
				$customer_country = WC()->customer->get_shipping_country();
			}
		}

		// Apply customizations to default fields
		foreach ( $default_field_customizations as $field_id => $customizations ) {
			$section = $customizations['section'] ?? 'billing';
			
			if ( isset( $fields[$section][$field_id] ) ) {
				// Apply customizations to existing default field
				$fields[$section][$field_id] = array_merge( $fields[$section][$field_id], array(
					'label' => $customizations['label'] ?? $fields[$section][$field_id]['label'],
					'placeholder' => $customizations['placeholder'] ?? $fields[$section][$field_id]['placeholder'],
					'required' => $customizations['required'] ?? $fields[$section][$field_id]['required'],
					'priority' => $customizations['priority'] ?? $fields[$section][$field_id]['priority'],
				));

				// Handle enabled/disabled state
				if ( isset( $customizations['enabled'] ) && ! $customizations['enabled'] ) {
					unset( $fields[$section][$field_id] );
				}
			}
		}

		// Add custom fields
		foreach ( $custom_fields as $field_id => $field_data ) {
			if ( ! $field_data['enabled'] ) {
				continue;
			}

			// Check country condition
			if ( ! empty( $field_data['show_for_countries'] ) && is_array( $field_data['show_for_countries'] ) ) {
				if ( ! empty( $customer_country ) && ! in_array( $customer_country, $field_data['show_for_countries'] ) ) {
					continue;
				}
			}

			$section = $field_data['section'];
			if ( ! isset( $fields[ $section ] ) ) {
				$fields[ $section ] = array();
			}

			$field_config = array(
				'label'       => $field_data['label'],
				'type'        => $field_data['type'],
				'required'    => $field_data['required'],
				'priority'    => $field_data['priority'],
				'class'       => array( 'form-row-wide' ),
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
						// Support key|value format
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

			// Handle checkbox field
			if ( $field_data['type'] === 'checkbox' ) {
				$field_config['type'] = 'checkbox';
				$field_config['class'] = array( 'form-row-wide', 'validate-required' );
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

			$value = isset( $_POST[ $field_id ] ) ? $_POST[ $field_id ] : '';

			if ( empty( $value ) ) {
				wc_add_notice( sprintf( __( '%s is a required field.', 'hezarfen-for-woocommerce' ), $field_data['label'] ), 'error' );
			}
		}
	}

	/**
	 * Save custom fields to order
	 *
	 * @param int $order_id Order ID.
	 */
	public function save_custom_fields_to_order( $order_id ) {
		$custom_fields = $this->get_custom_fields();
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		foreach ( $custom_fields as $field_id => $field_data ) {
			if ( ! $field_data['enabled'] ) {
				continue;
			}

			$value = isset( $_POST[ $field_id ] ) ? $_POST[ $field_id ] : '';
			if ( ! empty( $value ) ) {
				$order->update_meta_data( $field_id, sanitize_text_field( $value ) );
			}
		}

		$order->save();
	}

	/**
	 * Display custom fields in admin
	 *
	 * @param WC_Order $order Order object.
	 */
	public function display_custom_fields_in_admin( $order ) {
		$custom_fields = $this->get_custom_fields();
		$has_fields = false;

		// First check if there are any fields with values
		foreach ( $custom_fields as $field_id => $field_data ) {
			$value = $order->get_meta( $field_id );
			if ( ! empty( $value ) ) {
				$has_fields = true;
				break;
			}
		}

		if ( ! $has_fields ) {
			return;
		}

		echo '<div class="hezarfen-custom-fields">';
		echo '<h3>' . esc_html__( 'Custom Fields', 'hezarfen-for-woocommerce' ) . '</h3>';

		foreach ( $custom_fields as $field_id => $field_data ) {
			$value = $order->get_meta( $field_id );
			if ( ! empty( $value ) ) {
				echo '<p><strong>' . esc_html( $field_data['label'] ) . ':</strong> ';
				
				// Handle different field types
				if ( $field_data['type'] === 'checkbox' ) {
					echo $value ? esc_html__( 'Yes', 'hezarfen-for-woocommerce' ) : esc_html__( 'No', 'hezarfen-for-woocommerce' );
				} else {
					echo esc_html( $value );
				}
				
				echo '</p>';
			}
		}

		echo '</div>';
	}

	/**
	 * AJAX export fields
	 */
	public function ajax_export_fields() {
		check_ajax_referer( 'hezarfen_checkout_field_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$fields = $this->get_custom_fields();
		$export_data = array(
			'version' => WC_HEZARFEN_VERSION,
			'export_date' => current_time( 'Y-m-d H:i:s' ),
			'fields' => $fields,
		);

		$json_data = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
		$filename = 'hezarfen-checkout-fields-' . date( 'Y-m-d-H-i-s' ) . '.json';

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $json_data ) );

		echo $json_data;
		exit;
	}

	/**
	 * AJAX import fields
	 */
	public function ajax_import_fields() {
		check_ajax_referer( 'hezarfen_checkout_field_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		if ( ! isset( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array(
				'message' => __( 'No file uploaded or upload error.', 'hezarfen-for-woocommerce' ),
			) );
		}

		$file_content = file_get_contents( $_FILES['import_file']['tmp_name'] );
		$import_data = json_decode( $file_content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid JSON file.', 'hezarfen-for-woocommerce' ),
			) );
		}

		if ( ! isset( $import_data['fields'] ) || ! is_array( $import_data['fields'] ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid file format. Missing fields data.', 'hezarfen-for-woocommerce' ),
			) );
		}

		$replace_mode = isset( $_POST['replace_mode'] ) && $_POST['replace_mode'] === 'true';
		$existing_fields = $replace_mode ? array() : $this->get_custom_fields();
		
		// Merge or replace fields
		$new_fields = array_merge( $existing_fields, $import_data['fields'] );
		
		$this->save_custom_fields_config( $new_fields );

		$imported_count = count( $import_data['fields'] );
		wp_send_json_success( array(
			'message' => sprintf( 
				_n( 
					'Successfully imported %d field.', 
					'Successfully imported %d fields.', 
					$imported_count, 
					'hezarfen-for-woocommerce' 
				), 
				$imported_count 
			),
		) );
	}
}
