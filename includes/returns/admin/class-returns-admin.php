<?php
/**
 * Contains the Returns_Admin screen controller.
 *
 * @package Hezarfen\Inc\Returns
 */

namespace Hezarfen\Inc\Returns\Admin;

use Hezarfen\Inc\Returns\Core\Return_Event;
use Hezarfen\Inc\Returns\Core\Return_Status;
use Hezarfen\Inc\Returns\Returns_Module;

defined( 'ABSPATH' ) || exit();

/**
 * The "İadeler" admin screen: list, detail and the actions on a request.
 *
 * Parses and authorises input, then delegates to Return_Service. Business
 * rules never live in this class.
 */
class Returns_Admin {

	const PAGE_SLUG    = 'hezarfen-returns';
	const CAPABILITY   = 'manage_woocommerce';
	const ACTION_FIELD = 'hezarfen_returns_admin_action';
	const NONCE_ACTION = 'hezarfen_returns_admin';

	/**
	 * Module container.
	 *
	 * @var Returns_Module
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param Returns_Module $module Module container.
	 */
	public function __construct( $module ) {
		$this->module = $module;

		add_action( 'admin_menu', array( $this, 'register_menu' ), 12 );
		add_filter( 'parent_file', array( $this, 'keep_parent_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * URL of a request's detail screen.
	 *
	 * @param int $return_id Request ID.
	 *
	 * @return string
	 */
	public static function get_detail_url( $return_id ) {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&return_id=' . (int) $return_id );
	}

	/**
	 * Adds the submenu entry with a pending counter.
	 *
	 * The same screen is listed under both Hezarfen and WooCommerce, because
	 * that is where a shop manager looks for order paperwork. Both entries
	 * point at one slug, so there is still a single screen behind them.
	 *
	 * @return void
	 */
	public function register_menu() {
		$pending = $this->module->repository()->count( array( 'status' => Return_Status::get_open_statuses() ) );

		$title = __( 'İadeler', 'hezarfen-for-woocommerce' );
		$label = $title;

		if ( $pending ) {
			$label .= sprintf(
				' <span class="awaiting-mod"><span class="pending-count">%d</span></span>',
				(int) $pending
			);
		}

		add_submenu_page(
			'hezarfen',
			$title,
			$label,
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);

		add_submenu_page(
			'woocommerce',
			$title,
			$label,
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Keeps the screen highlighted under Hezarfen, whichever entry was
	 * clicked.
	 *
	 * Without this WordPress picks the parent it happens to find first in
	 * the submenu registry, so the highlight would drift between the two
	 * menus for no reason the merchant can see.
	 *
	 * @param string $parent_file Parent menu slug WordPress resolved.
	 *
	 * @return string
	 */
	public function keep_parent_menu( $parent_file ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the current screen, not acting on it.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( self::PAGE_SLUG === $page ) {
			return 'hezarfen';
		}

		return $parent_file;
	}

	/**
	 * The Hezarfen wordmark shown in the corner of the module's screens.
	 *
	 * The list and the detail view are plain WordPress chrome, so without a
	 * mark of their own nothing tells the merchant which plugin these pages
	 * came from.
	 *
	 * @return void
	 */
	public static function render_brand() {
		?>
		<div class="hez-admin-brand">
			<span class="hez-admin-brand__name">Hezarfen</span>
			<span class="hez-admin-brand__tag"><?php esc_html_e( 'İade Yönetimi', 'hezarfen-for-woocommerce' ); ?></span>
		</div>
		<?php
	}

	/**
	 * Loads the admin stylesheet on the module's screen only.
	 *
	 * @param string $hook Current screen hook.
	 *
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'hezarfen-returns-admin',
			HEZARFEN_RETURNS_ASSETS_URL . 'css/returns-admin.css',
			array(),
			\Hezarfen\Inc\Returns\Frontend\Return_Assets::asset_version( 'css/returns-admin.css' )
		);
	}

	/**
	 * Renders either the list or a request's detail.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'hezarfen-for-woocommerce' ) );
		}

		$this->render_notice();

		$request = $this->get_requested_return();

		if ( $request ) {
			$this->render_detail( $request );

			return;
		}

		$table = new Returns_List_Table( $this->module );
		$table->prepare_items();
		?>
		<div class="wrap hez-returns-admin">
			<?php self::render_brand(); ?>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'İade Talepleri', 'hezarfen-for-woocommerce' ); ?></h1>
			<hr class="wp-header-end">

			<?php $table->views(); ?>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<?php $table->search_box( __( 'Talep ara', 'hezarfen-for-woocommerce' ), 'hezarfen-returns' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders one request.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request The request.
	 *
	 * @return void
	 */
	private function render_detail( $request ) {
		$module          = $this->module;
		$events          = $module->events()->get_for_return( $request->get_id() );
		$reasons         = $module->reasons();
		$shipping_method = $module->shipping()->get_for_request( $request );
		$transitions     = Return_Status::get_allowed_transitions( $request->get_status() );
		$list_url        = admin_url( 'admin.php?page=' . self::PAGE_SLUG );

		require HEZARFEN_RETURNS_PATH . 'admin/views/return-detail.php';
	}

	/**
	 * Runs the submitted admin action.
	 *
	 * @return void
	 */
	public function handle_actions() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST[ self::ACTION_FIELD ] ) ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu işlemi yapma yetkiniz yok.', 'hezarfen-for-woocommerce' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$action = sanitize_key( wp_unslash( $_POST[ self::ACTION_FIELD ] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$return_id = isset( $_POST['return_id'] ) ? absint( wp_unslash( $_POST['return_id'] ) ) : 0;

		$request = $this->module->repository()->get( $return_id );

		if ( ! $request ) {
			return;
		}

		$result = $this->dispatch( $action, $request );

		$this->redirect_with_notice( $request, $result );
	}

	/**
	 * Maps an action key to a service call.
	 *
	 * @param string                                    $action  Action key.
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request The request.
	 *
	 * @return true|\WP_Error|null Null when the action is unknown.
	 */
	private function dispatch( $action, $request ) {
		$service = $this->module->service();
		$message = $this->read_message();
		$actor   = null;

		switch ( $action ) {
			case 'approve':
				return $service->change_status( $request, Return_Status::APPROVED, array( 'message' => $message ) );
			case 'reject':
				return $service->change_status( $request, Return_Status::REJECTED, array( 'message' => $message ) );
			case 'received':
				return $service->change_status( $request, Return_Status::RECEIVED, array( 'message' => $message ) );
			case 'complete':
				return $service->change_status( $request, Return_Status::COMPLETED, array( 'message' => $message ) );
			case 'cancel':
				return $service->change_status( $request, Return_Status::CANCELLED, array( 'message' => $message ) );
			case 'request-info':
				return $service->request_info( $request, $message, $actor );
			case 'note':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_actions().
				$visible = ! empty( $_POST['customer_visible'] );

				return $service->add_note( $request, $message, $actor, $visible );
			case 'tracking':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_actions().
				$courier = isset( $_POST['courier'] ) ? sanitize_text_field( wp_unslash( $_POST['courier'] ) ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_actions().
				$number = isset( $_POST['tracking_number'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_number'] ) ) : '';

				return $service->set_tracking( $request, $courier, $number, $actor );
		}

		return null;
	}

	/**
	 * Reads the free text field shared by most actions.
	 *
	 * @return string
	 */
	private function read_message() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_actions().
		if ( ! isset( $_POST['message'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_actions().
		return sanitize_textarea_field( wp_unslash( $_POST['message'] ) );
	}

	/**
	 * Sends the admin back to the detail screen with a status message.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Request $request The request.
	 * @param true|\WP_Error|null                       $result  Outcome of the action.
	 *
	 * @return void
	 */
	private function redirect_with_notice( $request, $result ) {
		$url = self::get_detail_url( $request->get_id() );

		if ( is_wp_error( $result ) ) {
			$url = add_query_arg(
				array(
					'hezarfen_returns_notice' => 'error',
					'hezarfen_returns_msg'    => rawurlencode( $result->get_error_message() ),
				),
				$url
			);
		} elseif ( true === $result ) {
			$url = add_query_arg( 'hezarfen_returns_notice', 'success', $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Prints the notice carried in the query string.
	 *
	 * @return void
	 */
	private function render_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice.
		$notice = isset( $_GET['hezarfen_returns_notice'] ) ? sanitize_key( wp_unslash( $_GET['hezarfen_returns_notice'] ) ) : '';

		if ( ! $notice ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice.
		$message = isset( $_GET['hezarfen_returns_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['hezarfen_returns_msg'] ) ) : '';

		if ( 'error' === $notice ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( $message ? $message : __( 'İşlem tamamlanamadı.', 'hezarfen-for-woocommerce' ) )
			);

			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'İade talebi güncellendi.', 'hezarfen-for-woocommerce' )
		);
	}

	/**
	 * The request the URL points at.
	 *
	 * @return \Hezarfen\Inc\Returns\Core\Return_Request|null
	 */
	private function get_requested_return() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$return_id = isset( $_GET['return_id'] ) ? absint( wp_unslash( $_GET['return_id'] ) ) : 0;

		return $return_id ? $this->module->repository()->get( $return_id ) : null;
	}

	/**
	 * Human readable label of a timeline actor.
	 *
	 * @param Return_Event $event Timeline entry.
	 *
	 * @return string
	 */
	public static function get_actor_label( $event ) {
		if ( $event->get_actor_name() ) {
			return $event->get_actor_name();
		}

		switch ( $event->get_actor_type() ) {
			case Return_Event::ACTOR_ADMIN:
				return __( 'Mağaza', 'hezarfen-for-woocommerce' );
			case Return_Event::ACTOR_CUSTOMER:
				return __( 'Müşteri', 'hezarfen-for-woocommerce' );
		}

		return __( 'Sistem', 'hezarfen-for-woocommerce' );
	}
}
