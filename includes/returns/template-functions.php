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
	 * The conversion goes through `get_gmt_from_date()` rather than the
	 * site's current `gmt_offset`: the offset in force today is not
	 * necessarily the one that was in force when the row was written, and
	 * subtracting the wrong one moves the timestamp by an hour. On a store
	 * whose timezone observes DST that made the same request read 14:00 in
	 * summer and 15:00 in winter.
	 *
	 * @param string $mysql_date Datetime in `Y-m-d H:i:s`, site time.
	 *
	 * @return string
	 */
	function hezarfen_returns_format_datetime( $mysql_date ) {
		if ( ! $mysql_date || '0000-00-00 00:00:00' === $mysql_date ) {
			return '';
		}

		$timestamp = (int) get_gmt_from_date( $mysql_date, 'U' );

		if ( ! $timestamp ) {
			return '';
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}
}

if ( ! function_exists( 'hezarfen_returns_format_date' ) ) {
	/**
	 * Formats a calendar day for display.
	 *
	 * A pickup day is a day, not a moment: the timestamp is anchored at
	 * noon UTC so no site offset can push it onto the day before or after.
	 *
	 * @param string $date Day in `Y-m-d`.
	 *
	 * @return string
	 */
	function hezarfen_returns_format_date( $date ) {
		$timestamp = $date ? strtotime( $date . ' 12:00:00 UTC' ) : false;

		if ( ! $timestamp ) {
			return '';
		}

		return wp_date( get_option( 'date_format' ), $timestamp );
	}
}

if ( ! function_exists( 'hezarfen_returns_plain_price' ) ) {
	/**
	 * A formatted price with no markup left in it.
	 *
	 * `wc_price()` wraps the amount in spans and writes the currency as an
	 * HTML entity, so stripping the tags alone leaves `&#8378;` behind —
	 * which then shows up verbatim wherever the string is escaped, such as
	 * an admin notice or a timeline entry.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 *
	 * @return string
	 */
	function hezarfen_returns_plain_price( $amount, $currency = '' ) {
		$formatted = wc_price( $amount, array( 'currency' => $currency ) );

		return trim( wp_strip_all_tags( html_entity_decode( $formatted, ENT_QUOTES, 'UTF-8' ) ) );
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

if ( ! function_exists( 'hezarfen_returns_form_enctype' ) ) {
	/**
	 * Prints the multipart encoding attribute when something on the form
	 * needs to carry a file.
	 *
	 * The attribute is not printed unconditionally: it belongs on a form only
	 * when an add-on has actually put a file input on it, and an add-on cannot
	 * reach the form tag the template owns.
	 *
	 * @param string $context Which form is being rendered: `request` or `info`.
	 *
	 * @return void
	 */
	function hezarfen_returns_form_enctype( $context ) {
		/**
		 * Filters whether a returns form must accept file uploads.
		 *
		 * @param bool   $accepts Whether the form carries a file input.
		 * @param string $context Form being rendered.
		 */
		if ( apply_filters( 'hezarfen_returns_form_accepts_uploads', false, $context ) ) {
			echo ' enctype="multipart/form-data"';
		}
	}
}
