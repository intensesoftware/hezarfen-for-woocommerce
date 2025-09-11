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

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_ajax_hezarfen_save_checkout_field', array( $this, 'ajax_save_field' ) );
		add_action( 'wp_ajax_hezarfen_delete_checkout_field', array( $this, 'ajax_delete_field' ) );
		add_action( 'wp_ajax_hezarfen_reset_checkout_field', array( $this, 'ajax_reset_field' ) );
		add_action( 'wp_ajax_hezarfen_reorder_checkout_fields', array( $this, 'ajax_reorder_fields' ) );
		add_action( 'wp_ajax_hezarfen_export_checkout_fields', array( $this, 'ajax_export_fields' ) );
		add_action( 'wp_ajax_hezarfen_import_checkout_fields', array( $this, 'ajax_import_fields' ) );
	}

	/**
	 * Initialize
	 */
	public function init() {
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
			add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_custom_fields_in_admin' ) );
			add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_custom_fields_in_admin' ) );
		} else {
			add_filter( 'woocommerce_checkout_fields', array( $this, 'add_custom_fields' ), 20 );
			add_action( 'woocommerce_checkout_process', array( $this, 'validate_custom_fields' ) );
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_custom_fields_to_order' ) );
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
		<div id="hezarfen-checkout-field-editor">
			<div class="hezarfen-field-editor-header">
				<h3><?php esc_html_e( 'Checkout Fields Editor', 'hezarfen-for-woocommerce' ); ?></h3>
				<div class="hezarfen-header-actions">
					<button type="button" class="button" id="export-fields">
						<?php esc_html_e( 'Export Fields', 'hezarfen-for-woocommerce' ); ?>
					</button>
					<button type="button" class="button" id="import-fields">
						<?php esc_html_e( 'Import Fields', 'hezarfen-for-woocommerce' ); ?>
					</button>
					<button type="button" class="button button-primary" id="add-new-field">
						<?php esc_html_e( 'Add New Field', 'hezarfen-for-woocommerce' ); ?>
					</button>
				</div>
			</div>

			<!-- Field Type Tabs -->
			<div class="hezarfen-field-tabs">
				<button type="button" class="hezarfen-tab-button active" data-tab="default">
					<?php esc_html_e( 'Default Fields', 'hezarfen-for-woocommerce' ); ?>
				</button>
				<button type="button" class="hezarfen-tab-button" data-tab="custom">
					<?php esc_html_e( 'Custom Fields', 'hezarfen-for-woocommerce' ); ?>
				</button>
			</div>

			<!-- Default Fields Tab -->
			<div class="hezarfen-tab-content active" id="default-fields-tab">
				<div class="hezarfen-fields-list" id="sortable-default-fields">
					<?php if ( empty( $default_fields ) ) : ?>
						<div class="hezarfen-no-fields">
							<p><?php esc_html_e( 'No default fields found.', 'hezarfen-for-woocommerce' ); ?></p>
						</div>
					<?php else : ?>
						<div class="hezarfen-fields-header">
							<p class="description"><?php esc_html_e( 'Edit default WooCommerce checkout fields. Changes will affect how these fields appear on checkout.', 'hezarfen-for-woocommerce' ); ?></p>
						</div>
						<?php 
						// Group fields by section
						$sections = array( 'billing' => array(), 'shipping' => array(), 'order' => array() );
						foreach ( $default_fields as $field_id => $field ) {
							$section = $field['section'] ?? 'billing';
							$sections[$section][$field_id] = $field;
						}
						?>
						<?php foreach ( $sections as $section_name => $section_fields ) : ?>
							<?php if ( ! empty( $section_fields ) ) : ?>
								<h4><?php echo esc_html( ucfirst( $section_name ) . ' Fields' ); ?></h4>
								<?php foreach ( $section_fields as $field_id => $field ) : ?>
									<?php $this->render_field_item( $field_id, $field, true ); ?>
								<?php endforeach; ?>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<!-- Custom Fields Tab -->
			<div class="hezarfen-tab-content" id="custom-fields-tab">
				<div class="hezarfen-fields-list" id="sortable-custom-fields">
					<?php if ( empty( $custom_fields ) ) : ?>
						<div class="hezarfen-no-fields">
							<p><?php esc_html_e( 'No custom fields created yet.', 'hezarfen-for-woocommerce' ); ?></p>
							<p class="description"><?php esc_html_e( 'Create your first custom field by clicking the "Add New Field" button above.', 'hezarfen-for-woocommerce' ); ?></p>
						</div>
					<?php else : ?>
						<div class="hezarfen-fields-header">
							<p class="description"><?php esc_html_e( 'Drag and drop to reorder fields. Fields are displayed in the order shown here.', 'hezarfen-for-woocommerce' ); ?></p>
						</div>
						<?php 
						// Sort fields by priority for display
						uasort( $custom_fields, function( $a, $b ) {
							return ( $a['priority'] ?? 10 ) <=> ( $b['priority'] ?? 10 );
						});
						?>
						<?php foreach ( $custom_fields as $field_id => $field ) : ?>
							<?php $this->render_field_item( $field_id, $field, false ); ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<!-- Field Editor Modal -->
			<div id="field-editor-modal" class="hezarfen-modal" style="display: none;">
				<div class="hezarfen-modal-content">
					<div class="hezarfen-modal-header">
						<h3 id="modal-title"><?php esc_html_e( 'Add New Field', 'hezarfen-for-woocommerce' ); ?></h3>
						<span class="hezarfen-modal-close">&times;</span>
					</div>
					<div class="hezarfen-modal-body">
						<form id="field-editor-form">
							<input type="hidden" id="field-id" name="field_id" value="">
							<input type="hidden" id="is-default-field" name="is_default" value="0">
							
							<div class="hezarfen-form-row">
								<label for="field-name"><?php esc_html_e( 'Field Name', 'hezarfen-for-woocommerce' ); ?> *</label>
								<input type="text" id="field-name" name="field_name" required>
							</div>

							<div class="hezarfen-form-row">
								<label for="field-label"><?php esc_html_e( 'Field Label', 'hezarfen-for-woocommerce' ); ?> *</label>
								<input type="text" id="field-label" name="field_label" required>
							</div>

							<div class="hezarfen-form-row">
								<label for="field-type"><?php esc_html_e( 'Field Type', 'hezarfen-for-woocommerce' ); ?> *</label>
								<select id="field-type" name="field_type" required>
									<option value=""><?php esc_html_e( 'Select field type', 'hezarfen-for-woocommerce' ); ?></option>
									<?php foreach ( $this->field_types as $type => $label ) : ?>
										<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="hezarfen-form-row">
								<label for="field-section"><?php esc_html_e( 'Section', 'hezarfen-for-woocommerce' ); ?> *</label>
								<select id="field-section" name="field_section" required>
									<option value=""><?php esc_html_e( 'Select section', 'hezarfen-for-woocommerce' ); ?></option>
									<?php foreach ( $this->field_sections as $section => $label ) : ?>
										<option value="<?php echo esc_attr( $section ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="hezarfen-form-row">
								<label for="field-placeholder"><?php esc_html_e( 'Placeholder', 'hezarfen-for-woocommerce' ); ?></label>
								<input type="text" id="field-placeholder" name="field_placeholder">
							</div>

							<div class="hezarfen-form-row" id="field-options-row" style="display: none;">
								<label for="field-options"><?php esc_html_e( 'Options (one per line)', 'hezarfen-for-woocommerce' ); ?></label>
								<textarea id="field-options" name="field_options" rows="4"></textarea>
							</div>

							<div class="hezarfen-form-row">
								<label for="field-required">
									<input type="checkbox" id="field-required" name="field_required" value="1">
									<?php esc_html_e( 'Required field', 'hezarfen-for-woocommerce' ); ?>
								</label>
							</div>

							<div class="hezarfen-form-row">
								<label for="field-enabled">
									<input type="checkbox" id="field-enabled" name="field_enabled" value="1" checked>
									<?php esc_html_e( 'Enable field', 'hezarfen-for-woocommerce' ); ?>
								</label>
							</div>

							<div class="hezarfen-form-row">
								<label for="field-priority"><?php esc_html_e( 'Priority', 'hezarfen-for-woocommerce' ); ?></label>
								<input type="number" id="field-priority" name="field_priority" value="10" min="1" max="100">
							</div>

							<div class="hezarfen-form-row">
								<label for="field-show-for-countries"><?php esc_html_e( 'Show only for countries (optional)', 'hezarfen-for-woocommerce' ); ?></label>
								<select id="field-show-for-countries" name="field_show_for_countries" multiple style="height: 120px;">
									<?php
									$countries = WC()->countries->get_countries();
									foreach ( $countries as $code => $name ) {
										echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $name ) . '</option>';
									}
									?>
								</select>
								<p class="description"><?php esc_html_e( 'Leave empty to show for all countries. Hold Ctrl/Cmd to select multiple countries.', 'hezarfen-for-woocommerce' ); ?></p>
							</div>
						</form>
					</div>
					<div class="hezarfen-modal-footer">
						<button type="button" class="button" id="cancel-field"><?php esc_html_e( 'Cancel', 'hezarfen-for-woocommerce' ); ?></button>
						<button type="button" class="button button-primary" id="save-field"><?php esc_html_e( 'Save Field', 'hezarfen-for-woocommerce' ); ?></button>
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
	 * Render field item
	 */
	private function render_field_item( $field_id, $field, $is_default = false ) {
		$field_type = $field['type'] ?? 'text';
		$field_type_label = isset( $this->field_types[ $field_type ] ) ? $this->field_types[ $field_type ] : $field_type;
		$section_label = isset( $this->field_sections[ $field['section'] ] ) ? $this->field_sections[ $field['section'] ] : $field['section'];
		?>
		<div class="hezarfen-field-item <?php echo $is_default ? 'is-default' : 'is-custom'; ?>" data-field-id="<?php echo esc_attr( $field_id ); ?>" data-is-default="<?php echo $is_default ? '1' : '0'; ?>">
			<?php if ( ! $is_default ) : ?>
				<div class="hezarfen-field-handle" title="<?php esc_attr_e( 'Drag to reorder', 'hezarfen-for-woocommerce' ); ?>">
					<span class="dashicons dashicons-menu"></span>
				</div>
			<?php endif; ?>
			<div class="hezarfen-field-info">
				<div class="hezarfen-field-name">
					<?php echo esc_html( $field['label'] ); ?>
					<span class="hezarfen-field-name-id">(<?php echo esc_html( $field['name'] ?? $field_id ); ?>)</span>
					<?php if ( $is_default ) : ?>
						<span class="hezarfen-field-badge default"><?php esc_html_e( 'Default', 'hezarfen-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="hezarfen-field-details">
					<span class="hezarfen-field-type"><?php echo esc_html( $field_type_label ); ?></span>
					<span class="hezarfen-field-section"><?php echo esc_html( $section_label ); ?></span>
					<?php if ( isset( $field['priority'] ) ) : ?>
						<span class="hezarfen-field-priority"><?php printf( esc_html__( 'Priority: %d', 'hezarfen-for-woocommerce' ), $field['priority'] ); ?></span>
					<?php endif; ?>
					<?php if ( isset( $field['required'] ) && $field['required'] ) : ?>
						<span class="hezarfen-field-required"><?php esc_html_e( 'Required', 'hezarfen-for-woocommerce' ); ?></span>
					<?php endif; ?>
					<?php if ( isset( $field['enabled'] ) && ! $field['enabled'] ) : ?>
						<span class="hezarfen-field-disabled"><?php esc_html_e( 'Disabled', 'hezarfen-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<div class="hezarfen-field-actions">
				<button type="button" class="button button-small edit-field" data-field-id="<?php echo esc_attr( $field_id ); ?>">
					<?php esc_html_e( 'Edit', 'hezarfen-for-woocommerce' ); ?>
				</button>
				<?php if ( ! $is_default ) : ?>
					<button type="button" class="button button-small delete-field" data-field-id="<?php echo esc_attr( $field_id ); ?>">
						<?php esc_html_e( 'Delete', 'hezarfen-for-woocommerce' ); ?>
					</button>
				<?php else : ?>
					<button type="button" class="button button-small reset-field" data-field-id="<?php echo esc_attr( $field_id ); ?>">
						<?php esc_html_e( 'Reset', 'hezarfen-for-woocommerce' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
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
