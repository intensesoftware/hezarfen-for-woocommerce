<?php
/**
 * Kullanici hesabim sayfasiyla ilgili islemler
 *
 * @package Intense\MSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IN_MSS_KullaniciArayuz
 */
class IN_MSS_KullaniciArayuz {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'woocommerce_order_details_after_customer_details', array( $this, 'render_sozlesmeler' ) );
		add_action( 'wp_footer', array( $this, 'add_contract_modal_script' ) );
	}

	/**
	 * Sozlesmeleri hesabim sayfasinda goster.
	 *
	 * @param  \WC_Order $siparis WC Order instance.
	 * @return void
	 */
	public function render_sozlesmeler( $siparis ) {
		$siparis_no = $siparis->get_id();
		$contracts = $this->get_sozlesme_detaylar( $siparis_no );

		// Debug output
		echo '<script>console.log("Order ID: ' . $siparis_no . '");</script>';
		echo '<script>console.log("Contracts found: ' . count($contracts) . '");</script>';

		if ( empty( $contracts ) ) {
			?>
			<p><?php esc_html_e( 'After your payment is completed, the contracts regarding your order will be available here.', 'hezarfen-for-woocommerce' ); ?></p>
			<?php
			return;
		}
		?>

		<!-- Contract Agreement Checkboxes -->
		<div class="hezarfen-contracts-section">
			<?php foreach ( $contracts as $contract ) : ?>
				<div class="hezarfen-contract-item">
					<label class="hezarfen-contract-label">
						<input type="checkbox" id="contract-<?php echo esc_attr( $contract->id ); ?>" class="hezarfen-contract-checkbox">
						<span><?php 
							printf( 
								__( '%s sözleşmesini okudum ve kabul ediyorum.', 'hezarfen-for-woocommerce' ), 
								'<a href="#" class="hezarfen-contract-link" data-contract-id="' . esc_attr( $contract->id ) . '">' . esc_html( $contract->contract_name ) . '</a>'
							);
						?></span>
					</label>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Contract Modals -->
		<?php foreach ( $contracts as $contract ) : ?>
			<div class="hezarfen-modal" id="hezarfen-contract-modal-<?php echo esc_attr( $contract->id ); ?>">
				<div class="hezarfen-modal-overlay"></div>
				<div class="hezarfen-modal-container">
					<div class="hezarfen-modal-header">
						<h3><?php echo esc_html( $contract->contract_name ); ?></h3>
						<button type="button" class="hezarfen-modal-close">&times;</button>
					</div>
					<div class="hezarfen-modal-content">
						<?php echo wp_kses_post( $contract->contract_content ); ?>
					</div>
					<div class="hezarfen-modal-footer">
						<p><strong><?php esc_html_e( 'Confirmation Date:', 'hezarfen-for-woocommerce' ); ?></strong> <?php echo esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $contract->created_at ) ) ); ?></p>
						<button type="button" class="hezarfen-modal-close button"><?php esc_html_e( 'Close', 'hezarfen-for-woocommerce' ); ?></button>
					</div>
				</div>
			</div>
		<?php endforeach; ?>


		<style>
		.hezarfen-contracts-section {
			margin: 20px 0;
		}
		.hezarfen-contract-item {
			margin: 10px 0;
		}
		.hezarfen-contract-checkbox {
			margin-right: 8px;
		}
		.hezarfen-contract-link {
			text-decoration: none;
			color: #0073aa;
			cursor: pointer;
		}
		.hezarfen-contract-link:hover {
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
	 * Sozlesme detaylari
	 *
	 * @param  int $siparis_no WC Order ID.
	 * @return object
	 */
	private function get_sozlesme_detaylar( $siparis_no ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hezarfen_contracts WHERE order_id=%s ORDER BY created_at ASC", $siparis_no ) );
	}

	/**
	 * Add contract modal JavaScript to footer
	 *
	 * @return void
	 */
	public function add_contract_modal_script() {
		// Only add if we have contracts on the page
		global $wpdb;
		$has_contracts = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hezarfen_contracts" );
		if ( ! $has_contracts ) {
			return;
		}
		?>
		<script>
		console.log('Contract modal script loaded');
		
		function initContractModals() {
			console.log('Initializing contract modals');
			
			// Handle contract link clicks on order details page
			var contractLinks = document.querySelectorAll('.hezarfen-contract-link');
			console.log('Found contract links:', contractLinks.length);
			
			contractLinks.forEach(function(link) {
				console.log('Adding click handler to link:', link);
				link.addEventListener('click', function(e) {
					e.preventDefault();
					console.log('Contract link clicked');
					var contractId = this.getAttribute('data-contract-id');
					console.log('Contract ID:', contractId);
					var modal = document.getElementById('hezarfen-contract-modal-' + contractId);
					console.log('Modal element:', modal);
					if (modal) {
						modal.style.display = 'block';
						console.log('Modal opened');
					} else {
						console.log('Modal not found');
					}
				});
			});

			// Handle modal close buttons
			document.querySelectorAll('.hezarfen-modal-close').forEach(function(closeBtn) {
				closeBtn.addEventListener('click', function() {
					var modal = this.closest('.hezarfen-modal');
					if (modal) {
						modal.style.display = 'none';
					}
				});
			});

			// Handle modal overlay clicks
			document.querySelectorAll('.hezarfen-modal-overlay').forEach(function(overlay) {
				overlay.addEventListener('click', function() {
					var modal = this.closest('.hezarfen-modal');
					if (modal) {
						modal.style.display = 'none';
					}
				});
			});

			// Handle ESC key to close modals
			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape') {
					document.querySelectorAll('.hezarfen-modal').forEach(function(modal) {
						modal.style.display = 'none';
					});
				}
			});
		}
		
		// Try multiple ways to initialize
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initContractModals);
		} else {
			initContractModals();
		}
		
		// Also try with a small delay
		setTimeout(initContractModals, 100);
		</script>
		<?php
	}
}

new IN_MSS_KullaniciArayuz();
