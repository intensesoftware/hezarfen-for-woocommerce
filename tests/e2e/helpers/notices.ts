/**
 * Selectors for WooCommerce customer notices.
 *
 * WooCommerce renders notices two different ways depending on the store:
 * the classic `<ul class="woocommerce-error">` markup, and — on block
 * themes and anywhere the block notice templates are active — a
 * `<div class="wc-block-components-notice-banner is-error">`. A spec that
 * pins one of them passes on the developer's machine and fails on the
 * next store, so always match both.
 */

export const NOTICE_ERROR =
	'.woocommerce-error, .wc-block-components-notice-banner.is-error';

export const NOTICE_SUCCESS =
	'.woocommerce-message, .wc-block-components-notice-banner.is-success';

export const NOTICE_INFO =
	'.woocommerce-info, .wc-block-components-notice-banner.is-info';
