<?php

namespace Hezarfen\Inc;

defined( 'ABSPATH' ) || exit;

use Hezarfen\Inc;

class Encryption
{
    /**
     * Check Hezarfen Encryption Key generated before
     */
    public static function is_encryption_key_generated()
    {
        $is_encryption_key_generated = get_option('_hezarfen_encryption_key_generated', 'no');

        return ( $is_encryption_key_generated == 'yes' );
    }
}