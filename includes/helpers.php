<?php

defined( 'ABSPATH' ) || exit;

function hez_hide_district_neighborhood() {
    return 'yes' === get_option( 'hezarfen_hide_district_neighborhood_fields', 'no' );
}