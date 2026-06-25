<?php
/**
 * REST controller that exposes Turkish district & neighborhood reference data
 * for the block-based checkout.
 *
 * @package Hezarfen\Inc\Blocks
 */

namespace Hezarfen\Inc\Blocks;

use Hezarfen\Inc\Mahalle_Local;
use Hezarfen\Inc\Helper;

defined( 'ABSPATH' ) || exit();

/**
 * Hezarfen_Locations_REST
 *
 * Read-only endpoints under the `hezarfen/v1` namespace used by the checkout
 * block to lazily load districts (ilçe) and neighborhoods (mahalle). The data
 * is static reference data shipped with the plugin, so the endpoints are
 * publicly readable.
 */
class Hezarfen_Locations_REST {

	const REST_NAMESPACE = 'hezarfen/v1';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/districts',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_districts' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'city' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/neighborhoods',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_neighborhoods' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'city'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'district' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Returns the districts for a given city plate number (e.g. "TR34").
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_districts( $request ) {
		$city      = $request->get_param( 'city' );
		$districts = Mahalle_Local::get_districts( $city );

		return new \WP_REST_Response( $this->to_options( $districts ), 200 );
	}

	/**
	 * Returns the neighborhoods for a given city plate number and district.
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_neighborhoods( $request ) {
		$city          = $request->get_param( 'city' );
		$district      = $request->get_param( 'district' );
		$neighborhoods = Mahalle_Local::get_neighborhoods( $city, $district, false );

		return new \WP_REST_Response( $this->to_options( $neighborhoods ), 200 );
	}

	/**
	 * Converts a flat list of names into `{ value, label }` option objects,
	 * matching the shape consumed by the checkout block selects.
	 *
	 * @param string[] $items List of names.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	protected function to_options( $items ) {
		$options = array();

		foreach ( $items as $item ) {
			$options[] = array(
				'value' => $item,
				'label' => $item,
			);
		}

		return $options;
	}
}
