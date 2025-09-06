<?php
/**
 * Contract Management Admin Page
 *
 * @package Hezarfen\MSS
 */

namespace Hezarfen\Inc\MSS\Admin;

use Hezarfen\Inc\MSS\Core\Contract_Manager;
use Hezarfen\Inc\MSS\Core\Contract_Types;
use Hezarfen\Inc\MSS\Core\Contract_Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract_Management_Page class
 */
class Contract_Management_Page {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_hezarfen_save_contract', array( $this, 'ajax_save_contract' ) );
		add_action( 'wp_ajax_hezarfen_get_contract', array( $this, 'ajax_get_contract' ) );
		add_action( 'wp_ajax_hezarfen_save_content', array( $this, 'ajax_save_content' ) );
		add_action( 'wp_ajax_hezarfen_delete_contract', array( $this, 'ajax_delete_contract' ) );
		add_action( 'wp_ajax_hezarfen_duplicate_contract', array( $this, 'ajax_duplicate_contract' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Get template options for dropdown (MSS forms + WordPress pages)
	 *
	 * @return string
	 */
	private function get_template_options() {
		$options = '';
		
		// Get MSS form templates
		$mss_templates = get_posts( array(
			'post_type' => 'intense_mss_form',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
		) );
		
		// Get WordPress pages
		$pages = get_posts( array(
			'post_type' => 'page',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
		) );
		
		// Add MSS templates with prefix
		if ( ! empty( $mss_templates ) ) {
			$options .= '<optgroup label="' . esc_attr__( 'MSS Templates', 'hezarfen-for-woocommerce' ) . '">';
			foreach ( $mss_templates as $template ) {
				$options .= sprintf(
					'<option value="%d" data-type="mss_form">[MSS] %s</option>',
					$template->ID,
					esc_html( $template->post_title )
				);
			}
			$options .= '</optgroup>';
		}
		
		// Add pages with prefix
		if ( ! empty( $pages ) ) {
			$options .= '<optgroup label="' . esc_attr__( 'WordPress Pages', 'hezarfen-for-woocommerce' ) . '">';
			foreach ( $pages as $page ) {
				$options .= sprintf(
					'<option value="%d" data-type="page">[Sayfa] %s</option>',
					$page->ID,
					esc_html( $page->post_title )
				);
			}
			$options .= '</optgroup>';
		}
		
		return $options;
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		// Load on all WooCommerce admin pages for now (we can optimize later)
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'woocommerce' ) === false ) {
			return;
		}
		
		// Also check if we're on the settings page
		$is_settings_page = (isset($_GET['page']) && $_GET['page'] === 'wc-settings') ||
		                   strpos( $hook_suffix, 'wc-settings' ) !== false;
		
		if ( $is_settings_page ) {
			
			wp_enqueue_script(
				'hezarfen-contract-management',
				HEZARFEN_MSS_URL . 'js/contract-management.js',
				array( 'jquery', 'wp-util' ),
				HEZARFEN_MSS_VERSION,
				true
			);

			wp_localize_script( 'hezarfen-contract-management', 'hezarfen_contract_ajax', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'hezarfen_contract_nonce' ),
				'strings'  => array(
					'confirm_delete' => __( 'Are you sure you want to delete this contract?', 'hezarfen-for-woocommerce' ),
					'saving'         => __( 'Saving...', 'hezarfen-for-woocommerce' ),
					'deleting'       => __( 'Deleting...', 'hezarfen-for-woocommerce' ),
					'duplicating'    => __( 'Duplicating...', 'hezarfen-for-woocommerce' ),
				),
			));

			wp_enqueue_style(
				'hezarfen-contract-management',
				HEZARFEN_MSS_URL . 'css/contract-management.css',
				array(),
				HEZARFEN_MSS_VERSION
			);
		}
	}

	/**
	 * Render contract management interface
	 */
	public function render_contract_management() {
		$contracts = Contract_Manager::get_contracts();
		$contract_types = Contract_Types::get_types_for_dropdown();
		?>
		<div class="hezarfen-contract-management">
			<div class="contract-management-header">
				<h3><?php esc_html_e( 'Contract Management', 'hezarfen-for-woocommerce' ); ?></h3>
				<button type="button" class="button button-primary" id="add-new-contract">
					<?php esc_html_e( 'Add New Contract', 'hezarfen-for-woocommerce' ); ?>
				</button>
			</div>

			<div class="contract-list">
				<?php if ( empty( $contracts ) ) : ?>
					<div class="no-contracts">
						<p><?php esc_html_e( 'No contracts found. Click "Add New Contract" to create your first contract.', 'hezarfen-for-woocommerce' ); ?></p>
					</div>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'hezarfen-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Type', 'hezarfen-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Content', 'hezarfen-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Status', 'hezarfen-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Required', 'hezarfen-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Order', 'hezarfen-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'hezarfen-for-woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $contracts as $contract ) : ?>
								<tr data-contract-id="<?php echo esc_attr( $contract['id'] ); ?>">
									<td>
										<strong><?php echo esc_html( $contract['name'] ); ?></strong>
										<?php if ( ! empty( $contract['custom_label'] ) ) : ?>
											<br><small><?php echo esc_html( $contract['custom_label'] ); ?></small>
										<?php endif; ?>
									</td>
									<td>
										<?php
										$type_info = Contract_Types::get_type( $contract['type'] );
										echo esc_html( $type_info ? $type_info['label'] : $contract['type'] );
										?>
									</td>
									<td>
										<?php
										if ( ! empty( $contract['content'] ) ) {
											$content_preview = wp_trim_words( strip_tags( $contract['content'] ), 10, '...' );
											echo '<span title="' . esc_attr( $content_preview ) . '">' . esc_html( $content_preview ) . '</span>';
										} else {
											echo '<span class="dashicons dashicons-warning" title="' . esc_attr__( 'No content added', 'hezarfen-for-woocommerce' ) . '"></span>';
										}
										?>
									</td>
									<td>
										<span class="status-badge status-<?php echo $contract['enabled'] ? 'enabled' : 'disabled'; ?>">
											<?php echo $contract['enabled'] ? esc_html__( 'Enabled', 'hezarfen-for-woocommerce' ) : esc_html__( 'Disabled', 'hezarfen-for-woocommerce' ); ?>
										</span>
									</td>
									<td>
										<span class="dashicons dashicons-<?php echo $contract['required'] ? 'yes-alt' : 'dismiss'; ?>"></span>
									</td>
									<td><?php echo esc_html( $contract['display_order'] ); ?></td>
									<td>
										<div class="row-actions">
											<button type="button" class="button button-small edit-contract" data-contract-id="<?php echo esc_attr( $contract['id'] ); ?>">
												<?php esc_html_e( 'Edit', 'hezarfen-for-woocommerce' ); ?>
											</button>
											<button type="button" class="button button-small edit-content" data-contract-id="<?php echo esc_attr( $contract['id'] ); ?>">
												<?php esc_html_e( 'Edit Content', 'hezarfen-for-woocommerce' ); ?>
											</button>
											<button type="button" class="button button-small duplicate-contract" data-contract-id="<?php echo esc_attr( $contract['id'] ); ?>">
												<?php esc_html_e( 'Duplicate', 'hezarfen-for-woocommerce' ); ?>
											</button>
											<button type="button" class="button button-small button-link-delete delete-contract" data-contract-id="<?php echo esc_attr( $contract['id'] ); ?>">
												<?php esc_html_e( 'Delete', 'hezarfen-for-woocommerce' ); ?>
											</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<!-- Contract Modal -->
		<div id="contract-modal" class="hezarfen-modal" style="display: none;">
			<div class="hezarfen-modal-content">
				<div class="hezarfen-modal-header">
					<h2 id="modal-title"><?php esc_html_e( 'Add New Contract', 'hezarfen-for-woocommerce' ); ?></h2>
					<button type="button" class="hezarfen-modal-close">&times;</button>
				</div>
				<div class="hezarfen-modal-body">
					<form id="contract-form">
						<input type="hidden" id="contract-id" name="contract_id" value="" />
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="contract-name"><?php esc_html_e( 'Contract Name', 'hezarfen-for-woocommerce' ); ?> <span class="required">*</span></label>
								</th>
								<td>
									<input type="text" id="contract-name" name="contract_name" class="regular-text" required />
									<p class="description"><?php esc_html_e( 'Enter a descriptive name for this contract.', 'hezarfen-for-woocommerce' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="contract-type"><?php esc_html_e( 'Contract Type', 'hezarfen-for-woocommerce' ); ?> <span class="required">*</span></label>
								</th>
								<td>
									<select id="contract-type" name="contract_type" required>
										<option value=""><?php esc_html_e( 'Select Type', 'hezarfen-for-woocommerce' ); ?></option>
										<?php foreach ( $contract_types as $key => $label ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Choose the type of contract.', 'hezarfen-for-woocommerce' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="custom-label"><?php esc_html_e( 'Custom Label', 'hezarfen-for-woocommerce' ); ?></label>
								</th>
								<td>
									<input type="text" id="custom-label" name="custom_label" class="regular-text" />
									<p class="description"><?php esc_html_e( 'Optional custom label for frontend display. If empty, the contract name will be used.', 'hezarfen-for-woocommerce' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="display-order"><?php esc_html_e( 'Display Order', 'hezarfen-for-woocommerce' ); ?></label>
								</th>
								<td>
									<input type="number" id="display-order" name="display_order" min="0" value="999" />
									<p class="description"><?php esc_html_e( 'Lower numbers appear first.', 'hezarfen-for-woocommerce' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Options', 'hezarfen-for-woocommerce' ); ?></th>
								<td>
									<label>
										<input type="checkbox" id="contract-enabled" name="contract_enabled" value="1" checked />
										<?php esc_html_e( 'Enabled', 'hezarfen-for-woocommerce' ); ?>
									</label>
									<br />
									<label>
										<input type="checkbox" id="contract-required" name="contract_required" value="1" />
										<?php esc_html_e( 'Required (customers must agree to proceed)', 'hezarfen-for-woocommerce' ); ?>
									</label>
								</td>
							</tr>
						</table>
					</form>
				</div>
				<div class="hezarfen-modal-footer">
					<button type="button" class="button button-primary" id="save-contract">
						<?php esc_html_e( 'Save Contract', 'hezarfen-for-woocommerce' ); ?>
					</button>
					<button type="button" class="button" id="cancel-contract">
						<?php esc_html_e( 'Cancel', 'hezarfen-for-woocommerce' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Content Editor Modal -->
		<div id="content-modal" class="hezarfen-modal" style="display: none;">
			<div class="hezarfen-modal-content">
				<div class="hezarfen-modal-header">
					<h2 id="content-modal-title"><?php esc_html_e( 'Edit Contract Content', 'hezarfen-for-woocommerce' ); ?></h2>
					<button type="button" class="hezarfen-modal-close">&times;</button>
				</div>
				<div class="hezarfen-modal-body">
					<form id="content-form">
						<input type="hidden" id="content-contract-id" name="content_contract_id" value="" />
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="content-source"><?php esc_html_e( 'Content Source', 'hezarfen-for-woocommerce' ); ?></label>
								</th>
								<td>
									<select id="content-source" name="content_source">
										<option value="manual"><?php esc_html_e( 'Manual Content', 'hezarfen-for-woocommerce' ); ?></option>
										<option value="template"><?php esc_html_e( 'From Template', 'hezarfen-for-woocommerce' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Choose whether to create content manually or use an existing template.', 'hezarfen-for-woocommerce' ); ?></p>
								</td>
							</tr>
							
							<tr id="template-selection-row" style="display: none;">
								<th scope="row">
									<label for="template-select"><?php esc_html_e( 'Select Template', 'hezarfen-for-woocommerce' ); ?></label>
								</th>
								<td>
									<select id="template-select" name="template_id">
										<option value=""><?php esc_html_e( 'Choose a template...', 'hezarfen-for-woocommerce' ); ?></option>
										<?php echo $this->get_template_options(); ?>
									</select>
									<p class="description"><?php esc_html_e( 'Select a WordPress page or MSS form to use as template content.', 'hezarfen-for-woocommerce' ); ?></p>
								</td>
							</tr>
							
							<tr id="manual-content-row">
								<th scope="row">
									<label for="contract-content-editor"><?php esc_html_e( 'Content', 'hezarfen-for-woocommerce' ); ?></label>
								</th>
								<td>
									<?php
									wp_editor( '', 'contract-content-editor', array(
										'textarea_name' => 'contract_content_editor',
										'textarea_rows' => 15,
										'media_buttons' => true,
										'teeny'         => false,
										'quicktags'     => true,
									) );
									?>
									<p class="description">
										<?php esc_html_e( 'Enter the contract content. You can use HTML and the following variables:', 'hezarfen-for-woocommerce' ); ?>
										<br>
										<strong><?php esc_html_e( 'Available Variables:', 'hezarfen-for-woocommerce' ); ?></strong><br>
										<code>{FATURA_TAM_AD_UNVANI}</code> - <?php esc_html_e( 'Billing full name', 'hezarfen-for-woocommerce' ); ?><br>
										<code>{FATURA_ADRESI}</code> - <?php esc_html_e( 'Billing address', 'hezarfen-for-woocommerce' ); ?><br>
										<code>{ALICI_ADRESI}</code> - <?php esc_html_e( 'Customer address', 'hezarfen-for-woocommerce' ); ?><br>
										<code>{ALICI_TELEFONU}</code> - <?php esc_html_e( 'Customer phone', 'hezarfen-for-woocommerce' ); ?><br>
										<code>{ALICI_EPOSTA}</code> - <?php esc_html_e( 'Customer email', 'hezarfen-for-woocommerce' ); ?><br>
										<code>{GUNCEL_TARIH}</code> - <?php esc_html_e( 'Current date', 'hezarfen-for-woocommerce' ); ?><br>
										<code>{URUNLER}</code> - <?php esc_html_e( 'Products summary', 'hezarfen-for-woocommerce' ); ?><br>
										<code>{ODEME_YONTEMI}</code> - <?php esc_html_e( 'Payment method', 'hezarfen-for-woocommerce' ); ?><br>
										<code>{KARGO_BEDELI}</code> - <?php esc_html_e( 'Shipping cost', 'hezarfen-for-woocommerce' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</form>
				</div>
				<div class="hezarfen-modal-footer">
					<button type="button" class="button button-primary" id="save-content">
						<?php esc_html_e( 'Save Content', 'hezarfen-for-woocommerce' ); ?>
					</button>
					<button type="button" class="button" id="cancel-content">
						<?php esc_html_e( 'Cancel', 'hezarfen-for-woocommerce' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}



	/**
	 * AJAX handler for saving contracts
	 */
	public function ajax_save_contract() {
		check_ajax_referer( 'hezarfen_contract_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1, 403 );
		}

		$contract_data = array(
			'id'            => sanitize_text_field( $_POST['contract_id'] ),
			'name'          => sanitize_text_field( $_POST['contract_name'] ),
			'type'          => sanitize_text_field( $_POST['contract_type'] ),
			'content'       => isset( $_POST['contract_content'] ) ? wp_kses_post( $_POST['contract_content'] ) : '',
			'custom_label'  => sanitize_text_field( $_POST['custom_label'] ),
			'display_order' => intval( $_POST['display_order'] ),
			'enabled'       => ! empty( $_POST['contract_enabled'] ),
			'required'      => ! empty( $_POST['contract_required'] ),
		);

		// Generate ID for new contracts
		if ( empty( $contract_data['id'] ) ) {
			$contract_data['id'] = uniqid( 'contract_' );
		}

		// Validate contract data
		$validated = Contract_Validator::validate_contract_data( $contract_data );
		if ( is_wp_error( $validated ) ) {
			wp_send_json_error( $validated->get_error_message() );
		}

		// Save contract
		$result = Contract_Manager::save_contract( $validated );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( array(
			'message' => __( 'Contract saved successfully.', 'hezarfen-for-woocommerce' ),
			'contract' => $validated,
		) );
	}

	/**
	 * AJAX handler for getting contract data
	 */
	public function ajax_get_contract() {
		check_ajax_referer( 'hezarfen_contract_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1, 403 );
		}

		$contract_id = sanitize_text_field( $_POST['contract_id'] );
		$contract = Contract_Manager::get_contract( $contract_id );

		if ( ! $contract ) {
			wp_send_json_error( __( 'Contract not found.', 'hezarfen-for-woocommerce' ) );
		}

		wp_send_json_success( $contract );
	}

	/**
	 * AJAX handler for saving contract content
	 */
	public function ajax_save_content() {
		check_ajax_referer( 'hezarfen_contract_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1, 403 );
		}

		$contract_id = sanitize_text_field( $_POST['contract_id'] );
		$content_source = sanitize_text_field( $_POST['content_source'] ?? 'manual' );
		
		// Get existing contract
		$contract = Contract_Manager::get_contract( $contract_id );
		if ( ! $contract ) {
			wp_send_json_error( __( 'Contract not found.', 'hezarfen-for-woocommerce' ) );
		}

		// Handle content based on source
		if ( $content_source === 'template' ) {
			$template_id = intval( $_POST['template_id'] ?? 0 );
			if ( $template_id ) {
				// Clear manual content and set template ID
				$contract['content'] = '';
				$contract['template_id'] = $template_id;
			} else {
				wp_send_json_error( __( 'Please select a template.', 'hezarfen-for-woocommerce' ) );
			}
		} else {
			// Manual content
			$content = wp_kses_post( $_POST['contract_content'] );
			$contract['content'] = $content;
			// Clear template ID when using manual content
			$contract['template_id'] = '';
		}

		// Save contract
		$result = Contract_Manager::save_contract( $contract );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( array(
			'message' => __( 'Contract content saved successfully.', 'hezarfen-for-woocommerce' ),
		) );
	}

	/**
	 * AJAX handler for deleting contracts
	 */
	public function ajax_delete_contract() {
		check_ajax_referer( 'hezarfen_contract_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1, 403 );
		}

		$contract_id = sanitize_text_field( $_POST['contract_id'] );

		// Validate deletion
		$can_delete = Contract_Validator::can_delete_contract( $contract_id );
		if ( is_wp_error( $can_delete ) ) {
			wp_send_json_error( $can_delete->get_error_message() );
		}

		// Delete contract
		$result = Contract_Manager::delete_contract( $contract_id );
		if ( ! $result ) {
			wp_send_json_error( __( 'Failed to delete contract.', 'hezarfen-for-woocommerce' ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Contract deleted successfully.', 'hezarfen-for-woocommerce' ),
		) );
	}

	/**
	 * AJAX handler for duplicating contracts
	 */
	public function ajax_duplicate_contract() {
		check_ajax_referer( 'hezarfen_contract_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1, 403 );
		}

		$contract_id = sanitize_text_field( $_POST['contract_id'] );

		$result = Contract_Manager::duplicate_contract( $contract_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( array(
			'message' => __( 'Contract duplicated successfully.', 'hezarfen-for-woocommerce' ),
		) );
	}
}