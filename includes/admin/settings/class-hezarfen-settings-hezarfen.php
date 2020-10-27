<?php
/**
 * Hezarfen Settings Tab
 *
 */

defined( 'ABSPATH' ) || exit;

use Hezarfen\Inc\Encryption;

if( class_exists( 'Hezarfen_Settings_Hezarfen', false ) )
	return new Hezarfen_Settings_Hezarfen();

class Hezarfen_Settings_Hezarfen extends WC_Settings_Page {


	/**
	 * Hezarfen_Settings_Hezarfen constructor.
	 */
	public function __construct()
	{

		$this->id = 'hezarfen';
		$this->label = 'Hezarfen';

		parent::__construct();

	}

	/**
	 * Get sections
	 *
	 * @return array
	 */
	public function get_sections()
	{
		$sections = array(
			'general' => __( 'General', 'hezarfen-for-woocommerce' ),
			'mahalle_io'  =>  'mahalle.io',
			'checkout'  =>  __( 'Checkout Fields', 'hezarfen-for-woocommerce' ),
			'encryption'  =>  __( 'Encryption', 'hezarfen-for-woocommerce' ),
		);

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}


	/**
	 * Get setting fields.
	 *
	 * @param string $current_section
	 * @return mixed
	 */
	public function get_settings( $current_section = '' )
	{


		if( 'mahalle_io' == $current_section )
		{

			$settings = apply_filters( 'hezarfen_mahalle_io_settings', array(

				array(

					'title' => __( 'mahalle.io Settings', 'hezarfen-for-woocommerce' ),
					'type' => 'title',
					'desc' => 'mahalle.io is a optional and paid service for show Turkey neighbors in checkout page.',
					'id' => 'hezarfen_mahalleio_options'

				),

				array(

					'title' => __( 'API Key', 'hezarfen-for-woocommerce' ),
					'type' => 'text',
					'desc' => 'API key may be created on mahalle.io my account page',
					'id' => 'hezarfen_mahalle_io_api_key'

				),

				array(
					'type' => 'sectionend',
					'id' => 'hezarfen_mahalleio_options'
				)

			) );

		}elseif( 'checkout' == $current_section ){


			$settings = apply_filters( 'hezarfen_checkout_settings', array(

				array(

					'title' => __( 'Checkout Form Settings', 'hezarfen-for-woocommerce' ),
					'type' => 'title',
					'desc' => 'Update checkout fields and checkout page options.',
					'id' => 'hezarfen_checkout_options'

				),

				array(

					'title' => __( 'Show T.C. Identity Field on Checkout Page ', 'hezarfen-for-woocommerce' ),
					'type' => 'checkbox',
					'desc' => 'T.C. Identity Field optionally shows on checkout field when invoice type selected as person.',
					'id' => 'hezarfen_checkout_show_TC_identity_field',
					'default' => 'no',
					'std' => 'yes'

				),

				array(
					'title' => 'Checkout T.C. Identity Number Fields Required Statuses',
					'desc' => __('Is T.C. Identity Number field required?', 'hezarfen-for-woocommerce'),
					'id' => 'hezarfen_checkout_is_TC_identity_number_field_required',
					'default' => 'no',
					'type' => 'checkbox'
				),

				array(
					'type' => 'sectionend',
					'id' => 'hezarfen_checkout_options'
				)

			) );


		}elseif( 'encryption' == $current_section ){

			// if encryption key not generated before, generate a new key.
			if(!Encryption::is_encryption_key_generated())
			{
				// create a new random key.
				$encryption_key = Encryption::create_random_key();

				$fields = array(
					array(
						'title' => __( 'Encryption Settings', 'hezarfen-for-woocommerce' ),
						'type' => 'title',
						'desc' => 'If the T.C. Identity Field is active, an encryption key must be generated. The following encryption key generated will be lost upon saving the form. Please back up the generated encryption key to a secure area, then paste it anywhere in the wp-config.php file. In case of deletion of the hezarfen-encryption-key line from wp-config.php, retrospectively, the orders will be sent to T.C. no values will become unreadable.',
						'id' => 'hezarfen_checkout_options'
					),
					array(
						'title' => __( 'Encryption Key', 'hezarfen-for-woocommerce' ),
						'type' => 'textarea',
						'css' => 'width:100%;height:60px',
						'default' => sprintf("define( 'HEZARFEN_ENCRYPTION_KEY', '%s' );", $encryption_key),
						'desc' => 'Back up the phrase in the box to a safe area, then place it in wp-config.php file.',
					),
					array(
						'title' => __( 'Encryption Key Confirmation', 'hezarfen-for-woocommerce' ),
						'type' => 'checkbox',
						'desc' => 'I backed up the key to a secure area and placed it in the wp-config file. In case the encryption key value is deleted from the wp-config.php file, all past orders will be transferred to T.C. I know I cannot access ID data.',
						'id' => 'hezarfen_checkout_show_TC_identity_field',
						'default' => 'no',
						'std' => 'yes'
					),
					array(
						'type' => 'sectionend',
						'id' => 'hezarfen_checkout_options'
					)
				);
			}
			
			$settings = apply_filters( 'hezarfen_checkout_settings', $fields );
		}else{


			// section 1
			$settings = apply_filters( 'hezarfen_general_settings', array() );


		}

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );

	}

	/**
	 * Output the settings
	 *
	 * @since 1.0
	 */
	public function output() {

		global $current_section;
		global $hide_save_button;

		if( $current_section == 'encryption' )
		{
			if(Encryption::is_encryption_key_generated())
			{
				$hide_save_button = true;
				
				// is key generated and placed to the wp-config.php?
				$health_check_status = Encryption::health_check();

				// is key correct and is it equal to the key that generated first time?
				$test_the_key = Encryption::test_the_encryption_key();

				require 'views/encryption.php';

			}else{
				// load the key geneate view
				$settings = $this->get_settings( $current_section );
				WC_Admin_Settings::output_fields( $settings );
			}

		}else
		{
			$settings = $this->get_settings( $current_section );
			WC_Admin_Settings::output_fields( $settings );
		}
	}

	public function save(){

		global $current_section;

		// if encryption key generated before, do not continue.
		if( $current_section=='encryption' && ( Encryption::health_check() || Encryption::test_the_encryption_key() ) )
		{
			return false;
		}

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::save_fields( $settings );

		if( $current_section == 'encryption' )
		{
			if( get_option( 'hezarfen_checkout_show_TC_identity_field', false ) == 'yes' )
			{
				update_option('_hezarfen_encryption_key_generated', 'yes');
				
				if(Encryption::health_check())
				{
					// create an encryption tester text.
					Encryption::create_encryption_tester_text();
				}
			}
		}
	}

}

return new Hezarfen_Settings_Hezarfen();