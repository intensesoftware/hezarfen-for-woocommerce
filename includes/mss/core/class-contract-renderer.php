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
	 * Render all active contracts
	 *
	 * @param string $display_type Display type (inline|modal).
	 * @return void
	 */
	public static function render_contracts( $display_type = 'inline' ) {
		$contracts = Contract_Manager::get_active_contracts();
		
		if ( empty( $contracts ) ) {
			return;
		}

		$contract_contents = array();
		
		// Process each contract
		foreach ( $contracts as $contract ) {
			$content = self::get_contract_content( $contract );
			if ( $content ) {
				$contract_contents[] = array(
					'contract' => $contract,
					'content'  => $content,
				);
			}
		}

		// Render based on display type
		if ( 'modal' === $display_type ) {
			self::render_modal_view( $contract_contents );
		} else {
			self::render_inline_view( $contract_contents );
		}
	}

	/**
	 * Get contract content by processing stored content
	 *
	 * @param array $contract Contract data.
	 * @return string|false
	 */
	private static function get_contract_content( $contract ) {
		if ( empty( $contract['content'] ) ) {
			return false;
		}

		$raw_content = wpautop( $contract['content'] );
		
		// Process template variables using the dedicated processor
		$processed_content = Template_Processor::process_variables( $raw_content );
		
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
		<div id="checkout-sozlesmeler">
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
		// Modal view implementation
		// This would include modal structure and JavaScript
		?>
		<div id="checkout-sozlesmeler-modal">
			<?php foreach ( $contract_contents as $item ) : ?>
				<div class="sozlesme-modal-trigger" data-contract-id="<?php echo esc_attr( $item['contract']['id'] ); ?>">
					<a href="#" class="sozlesme-modal-link">
						<?php echo esc_html( $item['contract']['custom_label'] ?: $item['contract']['name'] ); ?>
					</a>
				</div>
				
				<div class="sozlesme-modal-content" id="modal-<?php echo esc_attr( $item['contract']['id'] ); ?>" style="display: none;">
					<div class="modal-header">
						<h4><?php echo esc_html( $item['contract']['name'] ); ?></h4>
						<button class="modal-close">&times;</button>
					</div>
					<div class="modal-body">
						<?php echo wp_kses_post( $item['content'] ); ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render contract checkboxes
	 *
	 * @return void
	 */
	public static function render_contract_checkboxes() {
		$contracts = Contract_Manager::get_active_contracts();
		$settings  = get_option( 'hezarfen_mss_settings', array() );
		
		$hidden_contracts = isset( $settings['gosterilmeyecek_sozlesmeler'] ) 
			? $settings['gosterilmeyecek_sozlesmeler'] 
			: array();
		
		$default_checked = isset( $settings['sozlesme_onay_checkbox_varsayilan_durum'] ) 
			? (int) $settings['sozlesme_onay_checkbox_varsayilan_durum'] 
			: 0;

		?>
		<div class="in-sozlesme-onay-checkboxes">
			<?php foreach ( $contracts as $contract ) : ?>
				<?php if ( ! in_array( $contract['type'], $hidden_contracts, true ) ) : ?>
					<p class="form-row in-sozlesme-onay-checkbox <?php echo $contract['required'] ? 'validate-required' : ''; ?>">
						<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
							<input 
								type="checkbox" 
								class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" 
								name="contract_<?php echo esc_attr( $contract['id'] ); ?>_checkbox"
								<?php checked( $default_checked, 1 ); ?>
								<?php echo $contract['required'] ? 'required' : ''; ?>
							/>
							<span><?php 
								$label = $contract['custom_label'] ?: sprintf( 
									__( 'I agree to the %s.', 'hezarfen-for-woocommerce' ), 
									$contract['name'] 
								);
								echo esc_html( $label );
							?></span>
						</label>
					</p>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}
}