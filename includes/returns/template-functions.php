<?php
/**
 * Template helpers shared by the returns front-end and e-mails.
 *
 * @package Hezarfen\Inc\Returns
 */

use Hezarfen\Inc\Returns\Core\Return_Status;

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'hezarfen_returns_get_template' ) ) {
	/**
	 * Renders one of the module's templates, honouring theme overrides at
	 * `yourtheme/hezarfen/returns/…`.
	 *
	 * @param string               $name Template path relative to the templates directory.
	 * @param array<string, mixed> $args Variables extracted into the template.
	 *
	 * @return void
	 */
	function hezarfen_returns_get_template( $name, $args = array() ) {
		wc_get_template( $name, $args, 'hezarfen/', HEZARFEN_RETURNS_TEMPLATE_PATH );
	}
}

if ( ! function_exists( 'hezarfen_returns_status_badge' ) ) {
	/**
	 * Renders a status pill.
	 *
	 * @param string $status   Status key.
	 * @param bool   $customer Whether to use the customer facing label.
	 *
	 * @return string
	 */
	function hezarfen_returns_status_badge( $status, $customer = false ) {
		$label = $customer ? Return_Status::get_customer_label( $status ) : Return_Status::get_label( $status );

		return sprintf(
			'<span class="hez-return-badge hez-return-badge--%1$s">%2$s</span>',
			esc_attr( Return_Status::get_tone( $status ) ),
			esc_html( $label )
		);
	}
}

if ( ! function_exists( 'hezarfen_returns_format_datetime' ) ) {
	/**
	 * Formats a MySQL datetime stored in site time for display.
	 *
	 * @param string $mysql_date Datetime in `Y-m-d H:i:s`.
	 *
	 * @return string
	 */
	function hezarfen_returns_format_datetime( $mysql_date ) {
		if ( ! $mysql_date || '0000-00-00 00:00:00' === $mysql_date ) {
			return '';
		}

		$timestamp = strtotime( $mysql_date );

		if ( ! $timestamp ) {
			return '';
		}

		return wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$timestamp - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )
		);
	}
}

if ( ! function_exists( 'hezarfen_returns_event_icon' ) ) {
	/**
	 * Maps a timeline entry to the tone token its marker uses.
	 *
	 * @param \Hezarfen\Inc\Returns\Core\Return_Event $event Timeline entry.
	 *
	 * @return string
	 */
	function hezarfen_returns_event_icon( $event ) {
		if ( $event->get_to_status() ) {
			return Return_Status::get_tone( $event->get_to_status() );
		}

		return 'neutral';
	}
}
