<?php
/**
 * Contains the Helper trait.
 * 
 * @package Hezarfen\ManualShipmentTracking
 */

namespace Hezarfen\ManualShipmentTracking;

defined( 'ABSPATH' ) || exit;

trait Helper_Trait {
    /**
     * Xml2array
     *
     * @param mixed   $xml_object XML object.
     * @param mixed[] $out Result.
     *
     * @return mixed[]
     */
    function xml2array( $xml_object, $out = array() ) {
        foreach ( (array) $xml_object as $index => $node ) {
            $out[ $index ] = ( is_object( $node ) || is_array( $node ) ) ? $this->xml2array( $node ) : $node;
        }
        return $out;
    }

    /**
     * Telefon numarasını istenilen formata çevirir.
     *
     * @param string $gsm_number GSM numarası.
     *
     * @return string
     */
    function normalize_phone( $gsm_number ) {
        $gsm_number = $this->remove_whitespaces( $gsm_number );
        $gsm_number = ltrim( $gsm_number, '+90' );
        $gsm_number = ltrim( $gsm_number, '0' );
        return $gsm_number;
    }

    /**
     * Verilen stringdeki boşluk karakterlerini siler.
     *
     * @param string $str String.
     *
     * @return string
     */
    function remove_whitespaces( $str ) {
        return str_replace( ' ', '', $str );
    }
}