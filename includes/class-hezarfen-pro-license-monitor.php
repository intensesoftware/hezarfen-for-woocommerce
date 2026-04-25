<?php
/**
 * Monitors Hezarfen Pro license/subscription expiry from intense.com.tr.
 *
 * Reads Pro's WooCommerce API Manager client options (read-only) and polls the
 * remote status endpoint on a daily cron to cache when support/updates expire.
 * Exposes a single public getter used by the UI layer to render notices.
 *
 * @package Hezarfen\Inc
 */

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit();

/**
 * Pro_License_Monitor — data layer for license expiry tracking.
 */
class Pro_License_Monitor {

	const CRON_HOOK           = 'hezarfen_check_pro_license_expiry';
	const OPT_LAST_RESPONSE   = 'hezarfen_pro_license_last_response';
	const OPT_STATUS_CACHE    = 'hezarfen_pro_license_status_cache';
	const OPT_LAST_ERROR      = 'hezarfen_pro_license_last_error';
	const OPT_LAST_SUCCESS_TS = 'hezarfen_pro_license_last_success';

	const PRO_PRODUCT_ID    = 18509;
	const PRO_DATA_KEY      = 'wc_am_client_18509';
	const PRO_INSTANCE_KEY  = 'wc_am_client_18509_instance';
	const PRO_ACTIVATED_KEY = 'wc_am_client_18509_activated';

	const API_BASE          = 'https://intense.com.tr/';
	const WARNING_THRESHOLD = 30 * DAY_IN_SECONDS;
	const STALE_THRESHOLD   = 7 * DAY_IN_SECONDS;

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'run_check' ) );
		add_action( 'admin_init', array( $this, 'schedule_cron' ) );
		add_action( 'admin_init', array( $this, 'maybe_initial_sync' ) );
		add_action( 'current_screen', array( $this, 'maybe_refresh_on_license_screen' ) );

		register_deactivation_hook( WC_HEZARFEN_FILE, array( __CLASS__, 'unschedule_cron' ) );
	}

	/**
	 * Pro lisans sayfası açıldığında son sync 1 saatten eskiyse anında taze veri çek.
	 * Müşteri intense.com.tr'de yenileme yaptıktan sonra kendi sitesine dönüp
	 * lisans sayfasını açtığında banner'ın hemen güncel görünmesini sağlar.
	 *
	 * @return void
	 */
	public function maybe_refresh_on_license_screen() {
		if ( ! is_admin() ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		if ( false === strpos( (string) $screen->id, 'wc_am_client_' . self::PRO_PRODUCT_ID . '_dashboard' ) ) {
			return;
		}
		$last = (int) get_option( self::OPT_LAST_SUCCESS_TS );
		if ( $last > 0 && ( time() - $last ) < HOUR_IN_SECONDS ) {
			return; // son 1 saat içinde zaten çekilmiş
		}
		$this->run_check();
	}

	/**
	 * Detects whether Hezarfen Pro is installed and licensed.
	 *
	 * @return bool
	 */
	public static function is_pro_detected() {
		if ( defined( 'HEZARFEN_PRO_VERSION' ) ) {
			return true;
		}
		if ( get_option( 'hezarfen_pro_db_version' ) ) {
			return true;
		}
		$data = get_option( self::PRO_DATA_KEY );
		return is_array( $data ) && ! empty( $data );
	}

	/**
	 * Schedules the daily cron event if not already scheduled.
	 *
	 * @return void
	 */
	public function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Clears the cron on plugin deactivation.
	 *
	 * @return void
	 */
	public static function unschedule_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Triggers an initial sync the first time Pro is detected.
	 *
	 * @return void
	 */
	public function maybe_initial_sync() {
		if ( get_option( self::OPT_LAST_SUCCESS_TS ) ) {
			return;
		}
		if ( ! self::is_pro_detected() ) {
			return;
		}
		if ( wp_next_scheduled( self::CRON_HOOK . '_initial' ) ) {
			return;
		}
		wp_schedule_single_event( time() + 30, self::CRON_HOOK . '_initial' );
		add_action( self::CRON_HOOK . '_initial', array( $this, 'run_check' ) );
	}

	/**
	 * Taze veri çeker. "Tazele" butonu için kullanılır.
	 *
	 * run_check() başarılı ise cache'i yeni değerle üstüne yazar, başarısız ise
	 * eski cache korunur — bu yüzden cache'i önceden silmiyoruz (API fail olursa
	 * kart boşa gitmesin). Ayrıca intense.com.tr tarafına hezarfen_force_flush=1
	 * sinyali gönderir ki WCAM cache'i de invalidate edilsin.
	 *
	 * @return void
	 */
	public function force_refresh() {
		$this->run_check( true );
	}

	/**
	 * Cron callback — fetches remote status and updates the cache.
	 *
	 * @param bool $force_flush intense.com.tr tarafına WCAM cache'ini zorla flush
	 *                          etmesi için sinyal gönder. Sadece manuel "Tazele"
	 *                          buton akışından true olarak çağırılır.
	 * @return void
	 */
	public function run_check( $force_flush = false ) {
		if ( ! self::is_pro_detected() ) {
			return;
		}
		if ( 'Activated' !== get_option( self::PRO_ACTIVATED_KEY ) ) {
			return;
		}

		$response = $this->fetch_status( $force_flush );
		if ( ! is_array( $response ) ) {
			return;
		}

		$resource = $this->parse_resource( $response );
		$this->update_cache( $resource );
	}

	/**
	 * Performs the POST request to the WCAM status endpoint.
	 *
	 * @param bool $force_flush intense.com.tr tarafına WCAM cache flush sinyali gönder.
	 * @return array|null Decoded response array or null on failure.
	 */
	public function fetch_status( $force_flush = false ) {
		$data     = get_option( self::PRO_DATA_KEY );
		$api_key  = is_array( $data ) && isset( $data[ self::PRO_DATA_KEY . '_api_key' ] )
			? (string) $data[ self::PRO_DATA_KEY . '_api_key' ]
			: '';
		$instance = (string) get_option( self::PRO_INSTANCE_KEY );
		$domain   = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( '' === $api_key || '' === $instance || '' === $domain ) {
			return null;
		}

		$args = array(
			'wc-api'       => 'wc-am-api',
			'wc_am_action' => 'status',
			'api_key'      => $api_key,
			'product_id'   => self::PRO_PRODUCT_ID,
			'instance'     => $instance,
			'object'       => $domain,
		);
		if ( $force_flush ) {
			$args['hezarfen_force_flush'] = 1;
		}
		$url = add_query_arg( $args, self::API_BASE );

		$response = wp_safe_remote_post( esc_url_raw( $url ), array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			update_option(
				self::OPT_LAST_ERROR,
				array(
					'at'  => time(),
					'msg' => is_wp_error( $response )
						? $response->get_error_message()
						: 'HTTP ' . wp_remote_retrieve_response_code( $response ),
				),
				false
			);
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return null;
		}

		if ( ! defined( 'HEZARFEN_DEBUG_LICENSE_RESPONSE' ) || HEZARFEN_DEBUG_LICENSE_RESPONSE ) {
			update_option( self::OPT_LAST_RESPONSE, $body, false );
		}
		update_option( self::OPT_LAST_SUCCESS_TS, time(), false );
		delete_option( self::OPT_LAST_ERROR );

		return $body;
	}

	/**
	 * Extracts the Hezarfen Pro resource (product 18509) from the WCAM status response.
	 *
	 * @param array<string, mixed> $response Decoded status response.
	 * @return array<string, mixed>|null
	 */
	public function parse_resource( array $response ) {
		$resources = $response['data']['api_key_expirations']['wc_subs_resources'] ?? null;
		if ( ! is_array( $resources ) ) {
			return null;
		}

		// En uzak support_expires_ts'e sahip resource'u seç — müşteri aynı key'e
		// bağlı birden fazla yenileme yapmışsa en son yenilemenin tarihi görünsün.
		$latest = null;
		foreach ( $resources as $resource ) {
			if ( ! is_array( $resource ) ) {
				continue;
			}
			if ( (int) ( $resource['product_id'] ?? 0 ) !== self::PRO_PRODUCT_ID ) {
				continue;
			}
			$ts = isset( $resource['support_expires_ts'] ) ? (int) $resource['support_expires_ts'] : 0;
			if ( $ts <= 0 ) {
				continue;
			}
			if ( null === $latest || $ts > (int) $latest['support_expires_ts'] ) {
				$latest = $resource;
			}
		}

		return $latest;
	}

	/**
	 * Backwards-compatible wrapper: returns only the timestamp.
	 *
	 * @param array<string, mixed> $response Decoded status response.
	 * @return int|null
	 */
	public function parse_support_expires_ts( array $response ) {
		$resource = $this->parse_resource( $response );
		return is_array( $resource ) ? (int) $resource['support_expires_ts'] : null;
	}

	/**
	 * Returns the full Pro API key from the WCAM client option (empty string if absent).
	 *
	 * @return string
	 */
	public function get_api_key() {
		$data = get_option( self::PRO_DATA_KEY );
		if ( is_array( $data ) && ! empty( $data[ self::PRO_DATA_KEY . '_api_key' ] ) ) {
			return (string) $data[ self::PRO_DATA_KEY . '_api_key' ];
		}
		return '';
	}

	/**
	 * Updates the normalized status cache from a parsed resource.
	 *
	 * @param array<string, mixed>|null $resource Parsed resource.
	 * @return void
	 */
	private function update_cache( $resource ) {
		if ( ! is_array( $resource ) ) {
			return;
		}

		$cache = array(
			'support_expires_ts' => (int) $resource['support_expires_ts'],
			'email_masked'       => isset( $resource['email_masked'] ) ? (string) $resource['email_masked'] : '',
			'sub_id'             => isset( $resource['sub_id'] ) ? (int) $resource['sub_id'] : 0,
			'order_id'           => isset( $resource['order_id'] ) ? (int) $resource['order_id'] : 0,
			'checked_at'         => time(),
		);

		update_option( self::OPT_STATUS_CACHE, $cache, false );
	}

	/**
	 * Returns the computed license state for the UI layer.
	 *
	 * @return array{status:string, support_expires_ts:int, days_left:int, checked_at:int}
	 */
	public function get_license_state() {
		if ( ! self::is_pro_detected() ) {
			return $this->empty_state( 'no_pro' );
		}
		if ( 'Activated' !== get_option( self::PRO_ACTIVATED_KEY ) ) {
			return $this->empty_state( 'not_activated' );
		}

		$override        = apply_filters( 'hezarfen_pro_license_support_expires_override', null );
		$email_override  = (string) apply_filters( 'hezarfen_pro_license_email_masked_override', '' );
		$sub_id_override = (int) apply_filters( 'hezarfen_pro_license_sub_id_override', 0 );
		if ( is_int( $override ) && $override > 0 ) {
			return $this->compute_state_from_ts( $override, time(), $email_override, $sub_id_override );
		}

		$cache = get_option( self::OPT_STATUS_CACHE );
		if ( ! is_array( $cache ) || empty( $cache['support_expires_ts'] ) ) {
			return $this->empty_state( 'unknown' );
		}

		$last_success = (int) get_option( self::OPT_LAST_SUCCESS_TS );
		if ( $last_success > 0 && ( time() - $last_success ) > self::STALE_THRESHOLD ) {
			return $this->empty_state( 'unknown' );
		}

		return $this->compute_state_from_ts(
			(int) $cache['support_expires_ts'],
			(int) ( $cache['checked_at'] ?? time() ),
			(string) ( $cache['email_masked'] ?? '' ),
			(int) ( $cache['sub_id'] ?? 0 ),
			(int) ( $cache['order_id'] ?? 0 )
		);
	}

	/**
	 * Builds a license state array for status values that carry no timestamp.
	 *
	 * @param string $status Status label.
	 * @return array{status:string, support_expires_ts:int, days_left:int, checked_at:int, email_masked:string}
	 */
	private function empty_state( $status ) {
		return array(
			'status'             => $status,
			'support_expires_ts' => 0,
			'days_left'          => 0,
			'checked_at'         => 0,
			'email_masked'       => '',
			'sub_id'             => 0,
			'order_id'           => 0,
		);
	}

	/**
	 * Computes status and days_left from a timestamp.
	 *
	 * @param int    $ts           Support expiry unix timestamp.
	 * @param int    $checked_at   When the cache was last updated.
	 * @param string $email_masked Optional masked email from the status response.
	 * @param int    $sub_id       Optional subscription ID from the status response.
	 * @return array{status:string, support_expires_ts:int, days_left:int, checked_at:int, email_masked:string, sub_id:int}
	 */
	private function compute_state_from_ts( $ts, $checked_at, $email_masked = '', $sub_id = 0, $order_id = 0 ) {
		$now  = time();
		$diff = $ts - $now;

		if ( $diff <= 0 ) {
			$status = 'expired';
		} elseif ( $diff <= self::WARNING_THRESHOLD ) {
			$status = 'expiring_soon';
		} else {
			$status = 'ok';
		}

		return array(
			'status'             => $status,
			'support_expires_ts' => $ts,
			'days_left'          => (int) ceil( $diff / DAY_IN_SECONDS ),
			'checked_at'         => $checked_at,
			'email_masked'       => (string) $email_masked,
			'sub_id'             => (int) $sub_id,
			'order_id'           => (int) $order_id,
		);
	}
}
