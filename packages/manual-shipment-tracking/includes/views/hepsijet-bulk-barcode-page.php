<?php
/**
 * HepsiJet Bulk Barcode admin page template.
 *
 * @package Hezarfen\ManualShipmentTracking
 * @var array $orders_data Array of order data.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap hezarfen-bulk-barcode-wrap">
	<h1><?php esc_html_e( 'HepsiJet Toplu Barkod Oluştur / Yazdır', 'hezarfen-for-woocommerce' ); ?></h1>

	<!-- Bulk Desi Input Section -->
	<div class="hezarfen-bulk-desi-section">
		<h2><?php esc_html_e( 'Toplu Koli / Desi Girişi', 'hezarfen-for-woocommerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Aşağıdaki koli yapısı barkodu henüz oluşturulmamış tüm siparişlere uygulanır.', 'hezarfen-for-woocommerce' ); ?></p>
		<div class="hezarfen-bulk-packages-area">
			<div id="hezarfen-bulk-packages-container" class="hezarfen-packages-container">
				<div class="hezarfen-package-item">
					<span class="hezarfen-package-label"><?php esc_html_e( 'Koli 1:', 'hezarfen-for-woocommerce' ); ?></span>
					<input type="number" class="hezarfen-package-desi" min="0.01" max="9999" step="0.01" placeholder="Desi" />
					<button type="button" class="hezarfen-remove-package" style="visibility:hidden;" title="<?php esc_attr_e( 'Kaldır', 'hezarfen-for-woocommerce' ); ?>">&times;</button>
				</div>
			</div>
			<div class="hezarfen-bulk-packages-actions">
				<button type="button" id="hezarfen-bulk-add-package" class="button-link">+ <?php esc_html_e( 'Koli Ekle', 'hezarfen-for-woocommerce' ); ?></button>
				<button type="button" id="hezarfen-bulk-desi-apply" class="button button-secondary">
					<?php esc_html_e( 'Tümüne Uygula', 'hezarfen-for-woocommerce' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Orders Table Section -->
	<div class="hezarfen-bulk-orders-section">
		<table class="wp-list-table widefat fixed striped" id="hezarfen-bulk-orders-table">
			<thead>
				<tr>
					<th class="column-order-number"><?php esc_html_e( 'Sipariş', 'hezarfen-for-woocommerce' ); ?></th>
					<th class="column-customer"><?php esc_html_e( 'Müşteri', 'hezarfen-for-woocommerce' ); ?></th>
					<th class="column-items"><?php esc_html_e( 'Ürünler', 'hezarfen-for-woocommerce' ); ?></th>
					<th class="column-barcode-status"><?php esc_html_e( 'Barkod Durumu', 'hezarfen-for-woocommerce' ); ?></th>
					<th class="column-barcode-number"><?php esc_html_e( 'Barkod Numarası', 'hezarfen-for-woocommerce' ); ?></th>
					<th class="column-packages"><?php esc_html_e( 'Koliler', 'hezarfen-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders_data as $order_data ) : ?>
					<tr data-order-id="<?php echo esc_attr( $order_data['order_id'] ); ?>"
						data-has-barcode="<?php echo $order_data['has_barcode'] ? '1' : '0'; ?>"
						data-delivery-no="<?php echo esc_attr( $order_data['delivery_no'] ); ?>">
						<td class="column-order-number">
							<strong>#<?php echo esc_html( $order_data['order_number'] ); ?></strong>
						</td>
						<td class="column-customer">
							<?php echo esc_html( $order_data['customer_name'] ); ?>
						</td>
						<td class="column-items">
							<?php foreach ( $order_data['items'] as $item_line ) : ?>
								<div class="hezarfen-item-line">&bull; <?php echo esc_html( $item_line ); ?></div>
							<?php endforeach; ?>
						</td>
						<td class="column-barcode-status">
							<?php if ( $order_data['has_barcode'] ) : ?>
								<span class="hezarfen-status hezarfen-status-exists" title="<?php esc_attr_e( 'Barkod mevcut', 'hezarfen-for-woocommerce' ); ?>">&#10004;</span>
							<?php else : ?>
								<span class="hezarfen-status hezarfen-status-missing" title="<?php esc_attr_e( 'Barkod yok', 'hezarfen-for-woocommerce' ); ?>">&#10008;</span>
							<?php endif; ?>
						</td>
						<td class="column-barcode-number">
							<?php if ( $order_data['has_barcode'] ) : ?>
								<code><?php echo esc_html( $order_data['delivery_no'] ); ?></code>
							<?php else : ?>
								<span class="hezarfen-no-barcode">&mdash;</span>
							<?php endif; ?>
						</td>
						<td class="column-packages">
							<?php if ( $order_data['has_barcode'] ) : ?>
								<div class="hezarfen-packages-readonly">
									<?php echo esc_html( $order_data['desi'] ); ?> desi
								</div>
							<?php else : ?>
								<div class="hezarfen-packages-container">
									<div class="hezarfen-package-item">
										<span class="hezarfen-package-label"><?php esc_html_e( 'Koli 1:', 'hezarfen-for-woocommerce' ); ?></span>
										<input type="number" class="hezarfen-package-desi" min="0.01" max="9999" step="0.01" placeholder="Desi" />
										<button type="button" class="hezarfen-remove-package" style="visibility:hidden;" title="<?php esc_attr_e( 'Kaldır', 'hezarfen-for-woocommerce' ); ?>">&times;</button>
									</div>
								</div>
								<button type="button" class="hezarfen-add-package button-link">+ <?php esc_html_e( 'Koli Ekle', 'hezarfen-for-woocommerce' ); ?></button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Action Button -->
	<div class="hezarfen-bulk-actions-section">
		<button type="button" id="hezarfen-bulk-create-print" class="button button-primary button-large">
			<?php esc_html_e( 'Oluştur & Yazdır', 'hezarfen-for-woocommerce' ); ?>
		</button>
	</div>

	<!-- Progress Section (hidden initially) -->
	<div id="hezarfen-bulk-progress-section" class="hezarfen-bulk-progress-section" style="display:none;">
		<div class="hezarfen-progress-header">
			<h3 id="hezarfen-progress-title"><?php esc_html_e( 'Barkod Oluşturuluyor...', 'hezarfen-for-woocommerce' ); ?></h3>
			<span id="hezarfen-progress-counter">0/0</span>
			<span id="hezarfen-progress-percent">(0%)</span>
		</div>

		<div class="hezarfen-progress-bar-container">
			<div class="hezarfen-progress-bar" id="hezarfen-progress-bar" style="width:0%"></div>
		</div>

		<div id="hezarfen-progress-estimated" class="hezarfen-progress-estimated"></div>

		<div class="hezarfen-progress-log" id="hezarfen-progress-log"></div>

		<div class="hezarfen-progress-actions">
			<button type="button" id="hezarfen-cancel-btn" class="button button-secondary">
				<?php esc_html_e( 'İptal Et', 'hezarfen-for-woocommerce' ); ?>
			</button>
		</div>
	</div>

	<!-- Results Section (hidden initially) -->
	<div id="hezarfen-bulk-results-section" class="hezarfen-bulk-results-section" style="display:none;">
		<div id="hezarfen-results-summary" class="hezarfen-results-summary"></div>

		<div class="hezarfen-results-actions">
			<button type="button" id="hezarfen-print-btn" class="button button-primary button-large" style="display:none;">
				<?php esc_html_e( 'Başarılıları Yazdır', 'hezarfen-for-woocommerce' ); ?>
			</button>
			<button type="button" id="hezarfen-retry-btn" class="button button-secondary" style="display:none;">
				<?php esc_html_e( 'Hataları Tekrar Dene', 'hezarfen-for-woocommerce' ); ?>
			</button>
		</div>
	</div>
</div>
