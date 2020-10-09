<?php

use Hezarfen\Inc\Encryption;

class EncryptionTest extends WP_UnitTestCase {
    /**
     * Test: check Hezarfen Encryption Key created before?
     */
    public function test_is_encryption_key_generated()
    {
        add_option('_hezarfen_encryption_key_generated', 'yes');
        
        $this->assertEquals( Encryption::is_encryption_key_generated(), true );
    }

    public function test_is_encryption_key_not_generated()
    {
        // if option not found or not created
        $this->assertEquals( Encryption::is_encryption_key_generated(), false );

        // if option is not equal to 'yes'
        add_option('_hezarfen_encryption_key_generated', 'no');
        
        $this->assertEquals( Encryption::is_encryption_key_generated(), false );

        delete_option('_hezarfen_encryption_key_generated');
    }
}