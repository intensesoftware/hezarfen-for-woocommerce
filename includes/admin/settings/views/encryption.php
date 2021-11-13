<?php
/**
 * Template file for the encryption setting form.
 * 
 * @package includes/admin/settings/views/encryption.php
 */

?>

<h2><?php echo esc_html__( 'Encryption Key Health', 'hezarfen-for-woocommerce' ); ?></h2>

<table style="width:50%" class="widefat hezarfen-admin-settings-encryption-status">
	<tr>
		<th>
			<p>
			<?php 
			echo esc_html__(
				'Is the encryption key genereated and placed the wp-config.php?',
				'hezarfen-for-woocommerce'
			); 
			?>
			</p>
			<p class="description">
			<?php 
			echo esc_html__(
				'It checks encryption key whetever placed to the wp-config.php',
				'hezarfen-for-woocommerce'
			); 
			?>
			</p>
		</th>
		<td>
			<?php 
			echo $health_check_status
				? '<span class="yes">'
				: '<span class="no">'; 
			?>
			<?php 
			echo $health_check_status
				? esc_html__( 'Yes', 'hezarfen-for-woocommerce' )
				: esc_html__( 'No', 'hezarfen-for-woocommerce' ); 
			?>
			<?php echo '</span>'; ?>
		</td>
	</tr>
	<tr>
		<th>
			<p>
			<?php 
			echo esc_html__(
				'Key comparison success?',
				'hezarfen-for-woocommerce'
			); 
			?>
			</p>
			<p class="description">
			<?php 
			echo esc_html__(
				'Checks if the key created when the plugin was first installed is still the same key.',
				'hezarfen-for-woocommerce'
			); 
			?>
			</p>
		</th>
		<td>
			<?php 
			echo $test_the_key
				? '<span class="yes">'
				: '<span class="no">'; 
			?>
			<?php 
			echo $test_the_key
				? esc_html__( 'Yes', 'hezarfen-for-woocommerce' )
				: esc_html__( 'No', 'hezarfen-for-woocommerce' ); 
			?>
			<?php echo '</span>'; ?>
		</td>
	</tr>
</table>

<style>
	.hezarfen-admin-settings-encryption-status .yes { color:green; font-weight:bold }
	.hezarfen-admin-settings-encryption-status .no { color:red; font-weight:bold }
	.hezarfen-admin-settings-encryption-status td {padding-top:30px}
</style>
