<?php
/**
 * MSS Settings integration with Hezarfen
 * 
 * @package Hezarfen\Inc\MSS
 */

namespace Hezarfen\Inc\MSS;

defined( 'ABSPATH' ) || exit();

/**
 * MSS Settings class
 */
class MSS_Settings {
	
	const SECTION = 'mss_settings';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_sections_hezarfen', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_hezarfen', array( $this, 'add_settings' ), 10, 2 );
		add_action( 'woocommerce_settings_save_hezarfen', array( $this, 'save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		
		// Initialize the original MSS admin class for post types and other functionality
		$this->init_mss_admin();
	}
	
	/**
	 * Add MSS section to Hezarfen settings
	 */
	public function add_section( $sections ) {
		$sections[ self::SECTION ] = __( 'Mesafeli Satış Sözleşmesi', 'hezarfen-for-woocommerce' );
		return $sections;
	}
	
	/**
	 * Add MSS settings to the section
	 */
	public function add_settings( $settings, $current_section ) {
		if ( self::SECTION !== $current_section ) {
			return $settings;
		}
		
		$mss_settings = array(
			array(
				'title' => __( 'Mesafeli Satış Sözleşmesi Ayarları', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Web sitenize mesafeli satış sözleşmesi ve ön bilgilendirme form desteği ekleyin.', 'hezarfen-for-woocommerce' ),
				'id'    => 'hezarfen_mss_title'
			),
			
			array(
				'title'   => __( 'MSS Etkin', 'hezarfen-for-woocommerce' ),
				'desc'    => __( 'Mesafeli Satış Sözleşmesi özelliğini etkinleştir', 'hezarfen-for-woocommerce' ),
				'id'      => 'hezarfen_mss_enabled',
				'default' => 'no',
				'type'    => 'checkbox',
			),
			
			array(
				'title'    => __( 'MSS Taslak', 'hezarfen-for-woocommerce' ),
				'desc'     => __( 'Mesafeli Satış Sözleşmesi taslağını seçin', 'hezarfen-for-woocommerce' ),
				'id'       => 'intense_mss_ayarlar[mss_taslak_id]',
				'type'     => 'select',
				'options'  => $this->get_contract_templates(),
				'class'    => 'wc-enhanced-select',
			),
			
			array(
				'title'    => __( 'Ön Bilgilendirme Formu Taslak', 'hezarfen-for-woocommerce' ),
				'desc'     => __( 'Ön Bilgilendirme Formu taslağını seçin', 'hezarfen-for-woocommerce' ),
				'id'       => 'intense_mss_ayarlar[obf_taslak_id]',
				'type'     => 'select',
				'options'  => $this->get_contract_templates(),
				'class'    => 'wc-enhanced-select',
			),
			
			array(
				'type' => 'sectionend',
				'id'   => 'hezarfen_mss_title'
			),
			
			array(
				'title' => __( 'Sözleşme Yönetimi', 'hezarfen-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Sözleşme taslakları oluşturun ve yönetin.', 'hezarfen-for-woocommerce' ),
				'id'    => 'hezarfen_mss_management_title'
			),
			
			array(
				'title' => __( 'Sözleşme Taslakları', 'hezarfen-for-woocommerce' ),
				'type'  => 'mss_contract_management',
				'id'    => 'hezarfen_mss_contract_management',
			),
			
			array(
				'type' => 'sectionend',
				'id'   => 'hezarfen_mss_management_title'
			),
		);
		
		return $mss_settings;
	}
	
	/**
	 * Get contract templates for dropdown
	 */
	private function get_contract_templates() {
		$templates = array( '' => __( 'Seçin...', 'hezarfen-for-woocommerce' ) );
		
		$posts = get_posts( array(
			'post_type'      => 'intense_mss_form',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		
		foreach ( $posts as $post ) {
			$templates[ $post->ID ] = $post->post_title;
		}
		
		return $templates;
	}
	
	/**
	 * Save settings
	 */
	public function save_settings() {
		global $current_section;
		
		if ( self::SECTION !== $current_section ) {
			return;
		}
		
		// Handle MSS specific settings save
		if ( isset( $_POST['intense_mss_ayarlar'] ) && is_array( $_POST['intense_mss_ayarlar'] ) ) {
			$mss_settings = array_map( 'sanitize_text_field', $_POST['intense_mss_ayarlar'] );
			update_option( 'intense_mss_ayarlar', $mss_settings );
		}
	}
	
	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}
		
		global $current_section;
		if ( self::SECTION !== $current_section ) {
			return;
		}
		
		wp_enqueue_script(
			'hezarfen-mss-admin',
			HEZARFEN_MSS_URL . 'js/admin.js',
			array( 'jquery' ),
			HEZARFEN_MSS_VERSION,
			true
		);
		
		wp_enqueue_style(
			'hezarfen-mss-admin',
			HEZARFEN_MSS_URL . 'css/admin.css',
			array(),
			HEZARFEN_MSS_VERSION
		);
	}
	
	/**
	 * Initialize MSS admin functionality
	 */
	private function init_mss_admin() {
		// Post types are handled by the original MSS admin class
		// Just add custom field type for contract management
		add_action( 'woocommerce_admin_field_mss_contract_management', array( $this, 'output_contract_management' ) );
	}
	


	
	/**
	 * Output contract management field
	 */
	public function output_contract_management( $value ) {
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<div class="mss-contract-management">
					<p>
						<a href="<?php echo admin_url( 'post-new.php?post_type=intense_mss_form' ); ?>" class="button button-primary">
							<?php _e( 'Yeni Sözleşme Taslağı Oluştur', 'hezarfen-for-woocommerce' ); ?>
						</a>
						<a href="<?php echo admin_url( 'edit.php?post_type=intense_mss_form' ); ?>" class="button">
							<?php _e( 'Mevcut Taslakları Görüntüle', 'hezarfen-for-woocommerce' ); ?>
						</a>
					</p>
					<p class="description">
						<?php _e( 'Sözleşme taslakları oluşturduktan sonra yukarıdaki dropdown menülerden seçebilirsiniz.', 'hezarfen-for-woocommerce' ); ?>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}
}

// Initialize MSS Settings
new MSS_Settings();