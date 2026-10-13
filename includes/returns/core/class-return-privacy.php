<?php
/**
 * Contains the Return_Privacy registrar.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Core;

defined( 'ABSPATH' ) || exit();

/**
 * Puts return requests inside WordPress's personal data tools.
 *
 * The module keeps a customer's name, phone and full home address in its
 * own tables — the address a courier drives to. Data that lives outside
 * posts and usermeta is invisible to the export and erase tools unless it
 * registers itself, so a customer exercising their rights would have been
 * handed everything except the one record that says where they live.
 *
 * Erasure anonymises rather than deletes, the way WooCommerce treats
 * orders: the request, its amounts and its timeline stay so the store's
 * books still balance, while everything that points at a person goes.
 */
class Return_Privacy {

	const GROUP = 'hezarfen-returns';

	/**
	 * How many requests one export/erase page covers.
	 */
	const PAGE_SIZE = 20;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Adds the exporter.
	 *
	 * @param array<string, mixed> $exporters Registered exporters.
	 *
	 * @return array<string, mixed>
	 */
	public function register_exporter( $exporters ) {
		$exporters[ self::GROUP ] = array(
			'exporter_friendly_name' => __( 'Hezarfen iade talepleri', 'hezarfen-for-woocommerce' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Adds the eraser.
	 *
	 * @param array<string, mixed> $erasers Registered erasers.
	 *
	 * @return array<string, mixed>
	 */
	public function register_eraser( $erasers ) {
		$erasers[ self::GROUP ] = array(
			'eraser_friendly_name' => __( 'Hezarfen iade talepleri', 'hezarfen-for-woocommerce' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Exports one page of a customer's return requests.
	 *
	 * @param string $email Customer e-mail address.
	 * @param int    $page  1-based page number.
	 *
	 * @return array<string, mixed>
	 */
	public function export( $email, $page = 1 ) {
		$requests = $this->find( $email, (int) $page );
		$items    = array();

		foreach ( $requests as $request ) {
			$items[] = array(
				'group_id'    => self::GROUP,
				'group_label' => __( 'İade talepleri', 'hezarfen-for-woocommerce' ),
				'item_id'     => 'hezarfen-return-' . $request->get_id(),
				'data'        => $this->export_fields( $request ),
			);
		}

		return array(
			'data' => $items,
			'done' => count( $requests ) < self::PAGE_SIZE,
		);
	}

	/**
	 * Anonymises one page of a customer's return requests.
	 *
	 * @param string $email Customer e-mail address.
	 * @param int    $page  1-based page number.
	 *
	 * @return array<string, mixed>
	 */
	public function erase( $email, $page = 1 ) {
		$requests = $this->find( $email, (int) $page );
		$removed  = false;

		foreach ( $requests as $request ) {
			$request->anonymize();

			$saved = $this->repository()->save( $request );

			if ( ! is_wp_error( $saved ) ) {
				$removed = true;
			}
		}

		return array(
			'items_removed'  => false,
			'items_retained' => $removed,
			'messages'       => $removed
				? array( __( 'İade taleplerindeki kişisel bilgiler silindi; talep kayıtları muhasebe için anonim olarak korundu.', 'hezarfen-for-woocommerce' ) )
				: array(),
			// Erasure walks the same pages as the export, and anonymised rows
			// stop matching the e-mail — so the page cursor is not advanced,
			// the next call simply finds what is left.
			'done'           => count( $requests ) < self::PAGE_SIZE,
		);
	}

	/**
	 * The requests belonging to an e-mail address.
	 *
	 * @param string $email Customer e-mail address.
	 * @param int    $page  1-based page number.
	 *
	 * @return Return_Request[]
	 */
	private function find( $email, $page ) {
		$email = sanitize_email( (string) $email );

		// The tables only exist once the module has been switched on at
		// least once; querying them before that would be an SQL error inside
		// somebody else's privacy request.
		if ( ! $email || ! get_option( Returns_Schema::VERSION_OPTION, '' ) ) {
			return array();
		}

		return $this->repository()->query(
			array(
				'customer_email' => $email,
				'limit'          => self::PAGE_SIZE,
				'offset'         => max( 0, ( max( 1, $page ) - 1 ) * self::PAGE_SIZE ),
				'orderby'        => 'id',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * The exportable fields of one request.
	 *
	 * @param Return_Request $request The request.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function export_fields( $request ) {
		$fields = array(
			array(
				'name'  => __( 'Talep numarası', 'hezarfen-for-woocommerce' ),
				'value' => $request->get_return_number(),
			),
			array(
				'name'  => __( 'Sipariş', 'hezarfen-for-woocommerce' ),
				'value' => (string) $request->get_order_id(),
			),
			array(
				'name'  => __( 'Durum', 'hezarfen-for-woocommerce' ),
				'value' => Return_Status::get_label( $request->get_status() ),
			),
			array(
				'name'  => __( 'Oluşturulma', 'hezarfen-for-woocommerce' ),
				'value' => $request->get_created_at(),
			),
			array(
				'name'  => __( 'E-posta', 'hezarfen-for-woocommerce' ),
				'value' => $request->get_customer_email(),
			),
		);

		if ( $request->get_customer_note() ) {
			$fields[] = array(
				'name'  => __( 'Müşteri notu', 'hezarfen-for-woocommerce' ),
				'value' => $request->get_customer_note(),
			);
		}

		if ( $request->has_pickup_address() ) {
			$fields[] = array(
				'name'  => __( 'Kargo alım adresi', 'hezarfen-for-woocommerce' ),
				'value' => Return_Pickup_Address::format( $request->get_pickup_address() ),
			);
		}

		if ( $request->get_tracking_number() ) {
			$fields[] = array(
				'name'  => __( 'Kargo takip numarası', 'hezarfen-for-woocommerce' ),
				'value' => trim( $request->get_courier() . ' ' . $request->get_tracking_number() ),
			);
		}

		foreach ( $request->get_items() as $index => $item ) {
			$fields[] = array(
				/* translators: %d: line number within the request. */
				'name'  => sprintf( __( 'İade edilen ürün %d', 'hezarfen-for-woocommerce' ), (int) $index + 1 ),
				'value' => sprintf(
					'%s × %d%s',
					$item->get_product_name(),
					$item->get_quantity(),
					$item->get_reason_note() ? ' — ' . $item->get_reason_note() : ''
				),
			);
		}

		return $fields;
	}

	/**
	 * Request store.
	 *
	 * @return Return_Repository_Interface
	 */
	private function repository() {
		return \Hezarfen\Inc\Returns\Returns_Module::instance()->repository();
	}
}
