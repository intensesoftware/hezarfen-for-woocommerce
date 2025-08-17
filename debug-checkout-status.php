<?php
/**
 * Debug script to check checkout block status
 * 
 * This file can be accessed directly to check the status of checkout block integration
 */

// Load WordPress
require_once '../../../wp-load.php';

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Access denied' );
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Hezarfen Checkout Block Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Hezarfen Checkout Block Debug Status</h1>
    
    <h2>WordPress & WooCommerce Status</h2>
    <div class="status <?php echo class_exists( 'WooCommerce' ) ? 'success' : 'error'; ?>">
        WooCommerce: <?php echo class_exists( 'WooCommerce' ) ? 'Active' : 'Not Active'; ?>
    </div>
    
    <div class="status <?php echo function_exists( 'woocommerce_register_additional_checkout_field' ) ? 'success' : 'error'; ?>">
        Additional Checkout Fields: <?php echo function_exists( 'woocommerce_register_additional_checkout_field' ) ? 'Available' : 'Not Available'; ?>
    </div>
    
    <h2>Hezarfen Plugin Status</h2>
    <div class="status <?php echo defined( 'WC_HEZARFEN_VERSION' ) ? 'success' : 'error'; ?>">
        Hezarfen Plugin: <?php echo defined( 'WC_HEZARFEN_VERSION' ) ? 'Loaded (v' . WC_HEZARFEN_VERSION . ')' : 'Not Loaded'; ?>
    </div>
    
    <div class="status <?php echo class_exists( 'Hezarfen\Inc\Checkout_Block_Integration' ) ? 'success' : 'error'; ?>">
        Checkout Block Integration: <?php echo class_exists( 'Hezarfen\Inc\Checkout_Block_Integration' ) ? 'Loaded' : 'Not Loaded'; ?>
    </div>
    
    <div class="status <?php echo class_exists( 'Hezarfen\Inc\Checkout_Block_Simple' ) ? 'success' : 'error'; ?>">
        Simple Test Integration: <?php echo class_exists( 'Hezarfen\Inc\Checkout_Block_Simple' ) ? 'Loaded' : 'Not Loaded'; ?>
    </div>
    
    <h2>Test Field Registration</h2>
    <?php
    if ( function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
        echo '<div class="status info">Testing field registration...</div>';
        
        try {
            $test_result = woocommerce_register_additional_checkout_field(
                array(
                    'id'       => 'hezarfen/debug-test',
                    'label'    => 'Debug Test Field',
                    'location' => 'address',
                    'type'     => 'text',
                    'required' => false,
                )
            );
            
            echo '<div class="status success">Test field registration: SUCCESS</div>';
        } catch ( Exception $e ) {
            echo '<div class="status error">Test field registration failed: ' . esc_html( $e->getMessage() ) . '</div>';
        }
    } else {
        echo '<div class="status error">Cannot test field registration - function not available</div>';
    }
    ?>
    
    <h2>Recent Debug Log (last 20 lines)</h2>
    <?php
    $debug_log = WP_CONTENT_DIR . '/debug.log';
    if ( file_exists( $debug_log ) ) {
        $lines = file( $debug_log );
        $recent_lines = array_slice( $lines, -20 );
        echo '<pre>' . esc_html( implode( '', $recent_lines ) ) . '</pre>';
    } else {
        echo '<div class="status info">Debug log not found</div>';
    }
    ?>
    
    <h2>Actions</h2>
    <p>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout' ); ?>">WooCommerce Checkout Settings</a> |
        <a href="<?php echo home_url( '/checkout' ); ?>">View Checkout Page</a> |
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>">Refresh Status</a>
    </p>
    
    <script>
        console.log('Hezarfen Debug Status Page Loaded');
        console.log('WooCommerce Active:', <?php echo class_exists( 'WooCommerce' ) ? 'true' : 'false'; ?>);
        console.log('Additional Fields Available:', <?php echo function_exists( 'woocommerce_register_additional_checkout_field' ) ? 'true' : 'false'; ?>);
    </script>
</body>
</html>