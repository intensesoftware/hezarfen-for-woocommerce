<?php
/**
 * Contract Renderer
 *
 * @package Hezarfen\MSS
 */

namespace Hezarfen\Inc\MSS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract_Renderer class
 */
class Contract_Renderer {

	/**
	 * Render contracts on checkout page using dynamic contracts from settings
	 *
	 * @param string $display_type Display type (inline|modal).
	 * @return void
	 */
	public static function render_contracts( $display_type = 'inline' ) {
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$contracts = isset( $settings['contracts'] ) ? $settings['contracts'] : array();
		
		if ( empty( $contracts ) ) {
			return;
		}
		
		$contract_contents = array();
		
		foreach ( $contracts as $contract ) {
			// Skip disabled contracts
			if ( empty( $contract['enabled'] ) ) {
				continue;
			}
			
			// Skip contracts without templates
			if ( empty( $contract['template_id'] ) ) {
				continue;
			}
			
			$content = self::get_contract_content_from_template( $contract['template_id'] );
			if ( $content ) {
				$contract_contents[] = array(
					'contract' => array(
						'id' => $contract['id'],
						'name' => $contract['name'],
						'type' => $contract['id'],
						'enabled' => true,
						'required' => true,
					),
					'content' => $content,
				);
			}
		}

		if ( empty( $contract_contents ) ) {
			return;
		}

		// Render based on display type
		if ( 'modal' === $display_type ) {
			self::render_modal_view( $contract_contents );
		} else {
			self::render_inline_view( $contract_contents );
		}
	}

	/**
	 * Get contract content from WordPress page template ID
	 *
	 * @param int $template_id WordPress page ID.
	 * @param int $order_id Optional order ID for order-specific variables.
	 * @return string|false
	 */
	public static function get_contract_content_from_template( $template_id, $order_id = null ) {
		if ( empty( $template_id ) ) {
			return false;
		}
		
		$template_post = get_post( intval( $template_id ) );
		
		// Only allow WordPress pages
		if ( ! $template_post || $template_post->post_type !== 'page' || $template_post->post_status !== 'publish' ) {
			return false;
		}
		
		$raw_content = $template_post->post_content;
		
		if ( empty( $raw_content ) ) {
			return false;
		}

		$processed_content = wpautop( $raw_content );
		
		// Process template variables using the dedicated processor
		$processed_content = Template_Processor::process_variables( $processed_content, $order_id );
		
		return $processed_content;
	}

	/**
	 * Get contract content from settings-based template selection (legacy)
	 *
	 * @param string $contract_type Contract type (mss, obf, cayma, ozel1, ozel2).
	 * @param int    $order_id Optional order ID for order-specific variables.
	 * @return string|false
	 */
	public static function get_contract_content_by_type( $contract_type, $order_id = null ) {
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$template_key = $contract_type . '_template_id';
		
		if ( empty( $settings[ $template_key ] ) ) {
			return false;
		}
		
		return self::get_contract_content_from_template( $settings[ $template_key ], $order_id );
	}

	/**
	 * Get contract content by processing stored content or WordPress page content (legacy)
	 *
	 * @param array $contract Contract data.
	 * @return string|false
	 */
	private static function get_contract_content( $contract ) {
		$raw_content = '';
		
		// Check if content is stored directly
		if ( ! empty( $contract['content'] ) ) {
			$raw_content = $contract['content'];
		}
		// Check if content should be retrieved from a WordPress page
		elseif ( ! empty( $contract['template_id'] ) ) {
			$template_post = get_post( $contract['template_id'] );
			if ( $template_post && $template_post->post_type === 'page' && $template_post->post_status === 'publish' ) {
				$raw_content = $template_post->post_content;
			}
		}
		
		if ( empty( $raw_content ) ) {
			return false;
		}

		$processed_content = wpautop( $raw_content );
		
		// Process template variables using the dedicated processor
		$processed_content = Template_Processor::process_variables( $processed_content );
		
		return $processed_content;
	}



	/**
	 * Render inline view
	 *
	 * @param array $contract_contents Contract contents array.
	 * @return void
	 */
	private static function render_inline_view( $contract_contents ) {
		?>
		<div id="checkout-sozlesmeler" class="hezarfen-inline-contracts">
			<h3><?php esc_html_e( 'Contracts and Forms', 'hezarfen-for-woocommerce' ); ?></h3>
			
			<?php foreach ( $contract_contents as $item ) : ?>
				<div class="sozlesme-container contract-<?php echo esc_attr( $item['contract']['type'] ); ?>" data-contract-id="<?php echo esc_attr( $item['contract']['id'] ); ?>">
					<?php echo wp_kses_post( $item['content'] ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render modal view
	 *
	 * @param array $contract_contents Contract contents array.
	 * @return void
	 */
	private static function render_modal_view( $contract_contents ) {
		echo '<script>console.log("Rendering modal view with ' . count($contract_contents) . ' contracts");</script>';
		?>
		<!-- Contract Modals -->
		<?php foreach ( $contract_contents as $item ) : ?>
			<div class="hezarfen-modal" id="hezarfen-modal-<?php echo esc_attr( $item['contract']['id'] ); ?>">
				<div class="hezarfen-modal-overlay"></div>
				<div class="hezarfen-modal-container">
					<div class="hezarfen-modal-header">
						<h3><?php echo esc_html( $item['contract']['name'] ); ?></h3>
						<button type="button" class="hezarfen-modal-close">&times;</button>
					</div>
					<div class="hezarfen-modal-content">
						<?php echo wp_kses_post( $item['content'] ); ?>
					</div>
					<div class="hezarfen-modal-footer">
						<button type="button" class="hezarfen-modal-close button"><?php esc_html_e( 'Close', 'hezarfen-for-woocommerce' ); ?></button>
					</div>
				</div>
			</div>
		<?php endforeach; ?>


		<style>
		.contract-modal-link {
			text-decoration: none;
			color: #0073aa;
			cursor: pointer;
		}
		.contract-modal-link:hover {
			text-decoration: underline;
		}
		.hezarfen-modal {
			display: none;
			position: fixed;
			z-index: 1000;
			left: 0;
			top: 0;
			width: 100%;
			height: 100%;
		}
		.hezarfen-modal-overlay {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background-color: rgba(0, 0, 0, 0.5);
		}
		.hezarfen-modal-container {
			position: relative;
			background-color: #fff;
			margin: 5% auto;
			padding: 0;
			border: 1px solid #888;
			width: 80%;
			max-width: 800px;
			max-height: 80vh;
			overflow-y: auto;
			border-radius: 4px;
			box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
		}
		.hezarfen-modal-header {
			padding: 20px;
			border-bottom: 1px solid #eee;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.hezarfen-modal-header h3 {
			margin: 0;
		}
		.hezarfen-modal-close {
			background: none;
			border: none;
			font-size: 24px;
			cursor: pointer;
			color: #666;
		}
		.hezarfen-modal-close:hover {
			color: #000;
		}
		.hezarfen-modal-content {
			padding: 20px;
		}
		.hezarfen-modal-footer {
			padding: 20px;
			border-top: 1px solid #eee;
			text-align: right;
		}
		</style>
		<?php
	}

	/**
	 * Render contract checkboxes
	 *
	 * @return void
	 */
	public static function render_contract_checkboxes() {
		$settings = get_option( 'hezarfen_mss_settings', array() );
		$contracts = isset( $settings['contracts'] ) ? $settings['contracts'] : array();
		
		// Debug output
		echo '<script>console.log("Checkout contracts found: ' . count($contracts) . '");</script>';
		
		if ( empty( $contracts ) ) {
			echo '<script>console.log("No contracts configured in settings");</script>';
			return;
		}
		
		$hidden_contracts = isset( $settings['gosterilmeyecek_sozlesmeler'] ) 
			? $settings['gosterilmeyecek_sozlesmeler'] 
			: array();
		
		$default_checked = isset( $settings['sozlesme_onay_checkbox_varsayilan_durum'] ) 
			? (int) $settings['sozlesme_onay_checkbox_varsayilan_durum'] 
			: 0;

		?>
		<div class="in-sozlesme-onay-checkboxes">
			<?php foreach ( $contracts as $contract ) : ?>
				<?php 
				// Skip disabled contracts
				if ( empty( $contract['enabled'] ) ) {
					continue;
				}
				
				// Skip contracts without templates
				if ( empty( $contract['template_id'] ) ) {
					continue;
				}
				
				// Skip validation for hidden contracts (by contract ID)
				if ( in_array( $contract['id'], $hidden_contracts, true ) ) {
					continue;
				}
				?>
				<script>console.log("Rendering contract checkbox for: <?php echo esc_js( $contract['name'] ); ?>");</script>
				<p class="form-row in-sozlesme-onay-checkbox validate-required">
					<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
						<input 
							type="checkbox" 
							class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" 
							name="contract_<?php echo esc_attr( $contract['id'] ); ?>_checkbox"
							<?php checked( $default_checked, 1 ); ?>
							required
						/>
						<span><?php 
							printf( 
								__( '%s sözleşmesini okudum ve kabul ediyorum.', 'hezarfen-for-woocommerce' ), 
								'<a href="#" class="contract-modal-link" data-contract-id="' . esc_attr( $contract['id'] ) . '">' . esc_html( $contract['name'] ) . '</a>'
							);
						?></span>
					</label>
				</p>
			<?php endforeach; ?>
		</div>
		<?php
	}
}