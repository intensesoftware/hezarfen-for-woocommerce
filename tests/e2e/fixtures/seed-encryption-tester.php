<?php
/**
 * Mark Hezarfen's encryption key as generated and store the canonical
 * tester ciphertext (encrypted form of "Istanbul"). Called via
 * `wp eval-file` AFTER `wp config set HEZARFEN_ENCRYPTION_KEY '…'`
 * so the constant is already defined for this PHP request.
 */
update_option( 'hezarfen_encryption_key_generated', 'yes' );
$enc = new \Hezarfen\Inc\Data\PostMetaEncryption();
update_option( 'hezarfen_encryption_tester_text', $enc->encrypt( 'Istanbul' ) );
echo 'encryption tester seeded';
