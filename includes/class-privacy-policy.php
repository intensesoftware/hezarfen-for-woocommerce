<?php
/**
 * Privacy Policy Guide integration
 * 
 * @package Hezarfen\Inc
 */

defined( 'ABSPATH' ) || exit();

/**
 * Privacy Policy class for WordPress Privacy Policy Guide integration
 */
class Hezarfen_Privacy_Policy {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
	}
	
	/**
	 * Add Hezarfen privacy policy content to WordPress Privacy Policy Guide
	 * 
	 * @return void
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '
			<div class="wp-suggested-text">
				<h2>' . esc_html__( 'Hezarfen - WooCommerce Kargo Entegrasyonu', 'hezarfen-for-woocommerce' ) . '</h2>
				
				<h3>' . esc_html__( 'Gizlilik Politikası / Privacy Policy', 'hezarfen-for-woocommerce' ) . '</h3>
				
				<p>' . esc_html__( 'Kişisel verilerinizin gizliliği ve güvenliği bizim için önemlidir.', 'hezarfen-for-woocommerce' ) . '<br>' . 
				esc_html__( 'Intense Yazılım olarak, web sitemiz ve API servislerimiz üzerinden toplanan verilerin işlenmesi ve korunması ile ilgili detaylı bilgilere kendi sayfamızdan ulaşabilirsiniz:', 'hezarfen-for-woocommerce' ) . '</p>
				
				<p>👉 <a href="https://intense.com.tr/yasal/gizlilik-politikasi/" target="_blank" rel="noopener">' . esc_html__( 'Gizlilik Politikamızı buradan okuyun', 'hezarfen-for-woocommerce' ) . '</a></p>
				
				<hr>
				
				<p><em>' . esc_html__( 'Your privacy and data security are important to us.', 'hezarfen-for-woocommerce' ) . '<br>' .
				esc_html__( 'As Intense Yazılım, we provide full details on how we process and protect data collected through our website and API services on our dedicated page:', 'hezarfen-for-woocommerce' ) . '</em></p>
				
				<p>👉 <a href="https://intense.com.tr/yasal/gizlilik-politikasi/" target="_blank" rel="noopener"><em>' . esc_html__( 'Read our full Privacy Policy here', 'hezarfen-for-woocommerce' ) . '</em></a></p>
			</div>
		';

		wp_add_privacy_policy_content(
			__( 'Hezarfen - WooCommerce Kargo Entegrasyonu', 'hezarfen-for-woocommerce' ),
			wp_kses_post( $content )
		);
	}
}

// Initialize the privacy policy class
new Hezarfen_Privacy_Policy();