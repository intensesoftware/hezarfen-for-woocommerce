<?php
/**
 * Contains the Returns_Module bootstrap.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns;

use Hezarfen\Inc\Returns\Core\Return_Eligibility;
use Hezarfen\Inc\Returns\Core\Return_Event_Repository;
use Hezarfen\Inc\Returns\Core\Return_Policy_Resolver;
use Hezarfen\Inc\Returns\Core\Return_Reasons;
use Hezarfen\Inc\Returns\Core\Return_Repository;
use Hezarfen\Inc\Returns\Core\Return_Service;
use Hezarfen\Inc\Returns\Core\Return_Settings;
use Hezarfen\Inc\Returns\Core\Returns_Schema;
use Hezarfen\Inc\Returns\Shipping\Return_Shipping_Registry;

defined( 'ABSPATH' ) || exit();

/**
 * Composition root of the returns module.
 *
 * Owns the object graph and the wiring; it is the only class that knows
 * which concrete implementation satisfies which contract. Everything else
 * receives its collaborators through the constructor.
 */
class Returns_Module {

	/**
	 * Singleton instance.
	 *
	 * @var Returns_Module|null
	 */
	private static $instance = null;

	/**
	 * Lazily built services keyed by name.
	 *
	 * @var array<string, mixed>
	 */
	private $services = array();

	/**
	 * Returns the shared instance.
	 *
	 * @return Returns_Module
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Loads the module and hooks it into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		$module = self::instance();

		$module->define_constants();
		$module->load_files();

		// The settings screen must exist even while the feature is off,
		// otherwise the merchant has no way to switch it on.
		add_action( 'init', array( $module, 'on_init' ), 15 );

		if ( is_admin() ) {
			new Admin\Returns_Settings();
		}
	}

	/**
	 * Runs the schema check and boots the customer facing pieces once the
	 * feature is enabled.
	 *
	 * @return void
	 */
	public function on_init() {
		if ( ! Return_Settings::is_enabled() ) {
			return;
		}

		Returns_Schema::maybe_install();

		// The public page is what guests and e-mail links point at, so it
		// has to exist as soon as the feature is on — not only when the
		// merchant happens to re-save the settings.
		if ( ! Return_Settings::get_page_id() ) {
			Frontend\Guest_Returns::ensure_page();
		}

		new Frontend\My_Account_Returns( $this );
		new Frontend\Guest_Returns( $this );
		new Frontend\Return_Form_Handler( $this );
		new Frontend\Return_Assets();
		new Emails\Return_Emails();

		if ( is_admin() ) {
			new Admin\Returns_Admin( $this );
			new Admin\Order_Returns_Panel( $this );
		}

		/**
		 * Fires once the returns module is fully booted.
		 *
		 * Add-ons register their reason, policy and shipping providers on
		 * this action.
		 *
		 * @param Returns_Module $module The module instance.
		 */
		do_action( 'hezarfen_returns_loaded', $this );
	}

	/**
	 * Defines the module's path constants.
	 *
	 * @return void
	 */
	private function define_constants() {
		if ( ! defined( 'HEZARFEN_RETURNS_PATH' ) ) {
			define( 'HEZARFEN_RETURNS_PATH', WC_HEZARFEN_UYGULAMA_YOLU . 'includes/returns/' );
		}

		if ( ! defined( 'HEZARFEN_RETURNS_TEMPLATE_PATH' ) ) {
			define( 'HEZARFEN_RETURNS_TEMPLATE_PATH', WC_HEZARFEN_UYGULAMA_YOLU . 'templates/' );
		}

		if ( ! defined( 'HEZARFEN_RETURNS_ASSETS_URL' ) ) {
			define( 'HEZARFEN_RETURNS_ASSETS_URL', WC_HEZARFEN_UYGULAMA_URL . 'assets/returns/' );
		}
	}

	/**
	 * Requires the module's classes.
	 *
	 * @return void
	 */
	private function load_files() {
		$files = array(
			'core/class-returns-schema.php',
			'core/class-return-settings.php',
			'core/class-return-status.php',
			'core/trait-hydrates-props.php',
			'core/class-return-item.php',
			'core/class-return-event.php',
			'core/class-return-request.php',
			'core/interface-return-repository.php',
			'core/class-return-repository.php',
			'core/class-return-event-repository.php',
			'core/interface-return-reason-provider.php',
			'core/class-default-reason-provider.php',
			'core/class-return-reasons.php',
			'core/class-return-policy.php',
			'core/interface-return-policy-provider.php',
			'core/class-global-return-policy-provider.php',
			'core/class-return-policy-resolver.php',
			'core/class-return-eligibility.php',
			'core/class-return-service.php',
			'shipping/interface-return-shipping-method.php',
			'shipping/class-customer-ships-method.php',
			'shipping/class-kargokit-return-method.php',
			'shipping/class-return-shipping-registry.php',
			'frontend/class-return-assets.php',
			'frontend/class-return-access.php',
			'frontend/class-return-form-handler.php',
			'frontend/class-my-account-returns.php',
			'frontend/class-guest-returns.php',
			// The e-mail classes extend WC_Email, which WooCommerce only
			// loads once the mailer boots, so they are required inside the
			// `woocommerce_email_classes` filter instead of here.
			'emails/class-return-emails.php',
			'admin/class-returns-settings.php',
			'template-functions.php',
		);

		if ( is_admin() ) {
			$files[] = 'admin/class-returns-list-table.php';
			$files[] = 'admin/class-returns-admin.php';
			$files[] = 'admin/class-order-returns-panel.php';
		}

		foreach ( $files as $file ) {
			require_once HEZARFEN_RETURNS_PATH . $file;
		}
	}

	/**
	 * Request store.
	 *
	 * @return Core\Return_Repository_Interface
	 */
	public function repository() {
		if ( ! isset( $this->services['repository'] ) ) {
			/**
			 * Filters the repository implementation the module uses.
			 *
			 * @param Core\Return_Repository_Interface $repository Default wpdb backed repository.
			 */
			$this->services['repository'] = apply_filters( 'hezarfen_returns_repository', new Return_Repository() );
		}

		return $this->services['repository'];
	}

	/**
	 * Timeline store.
	 *
	 * @return Return_Event_Repository
	 */
	public function events() {
		if ( ! isset( $this->services['events'] ) ) {
			$this->services['events'] = new Return_Event_Repository();
		}

		return $this->services['events'];
	}

	/**
	 * Reason registry.
	 *
	 * @return Return_Reasons
	 */
	public function reasons() {
		if ( ! isset( $this->services['reasons'] ) ) {
			$this->services['reasons'] = new Return_Reasons();
		}

		return $this->services['reasons'];
	}

	/**
	 * Policy resolver.
	 *
	 * @return Return_Policy_Resolver
	 */
	public function policies() {
		if ( ! isset( $this->services['policies'] ) ) {
			$this->services['policies'] = new Return_Policy_Resolver();
		}

		return $this->services['policies'];
	}

	/**
	 * Eligibility calculator.
	 *
	 * @return Return_Eligibility
	 */
	public function eligibility() {
		if ( ! isset( $this->services['eligibility'] ) ) {
			$this->services['eligibility'] = new Return_Eligibility( $this->repository(), $this->policies() );
		}

		return $this->services['eligibility'];
	}

	/**
	 * Shipping method registry.
	 *
	 * @return Return_Shipping_Registry
	 */
	public function shipping() {
		if ( ! isset( $this->services['shipping'] ) ) {
			$this->services['shipping'] = new Return_Shipping_Registry();
		}

		return $this->services['shipping'];
	}

	/**
	 * Application service.
	 *
	 * @return Return_Service
	 */
	public function service() {
		if ( ! isset( $this->services['service'] ) ) {
			$this->services['service'] = new Return_Service(
				$this->repository(),
				$this->events(),
				$this->eligibility(),
				$this->reasons(),
				$this->shipping()
			);
		}

		return $this->services['service'];
	}
}
