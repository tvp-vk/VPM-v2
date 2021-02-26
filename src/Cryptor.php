<?php

/**
 * @file
 * Contains Drupal\latvia_auth\Cryptor.
 */

namespace Drupal\latvia_auth;

use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;
use Drupal\Core\Site\Settings;
use Drupal\Component\Utility\Crypt;

/**
 * Provides security helpers : token generate, crypt, decrypt.
 */
class Cryptor {

  /**
   * Undocumented function
   *
   * @return void
   */
  public function loadEncryptionKey() {
    $key = \Drupal::service('settings')->get('crypt_key');

    return Key::loadFromAsciiSafeString($key);
  }

  /**
   * Generate a random token.
   *
   * @return string
   *   A random token.
   */
  public function generateToken() {
    $token = bin2hex(openssl_random_pseudo_bytes(16));
    return $token;
  }

  /**
   * {@inheritdoc}
   */
  public function encrypt($text) {
    $key = $this->loadEncryptionKey();

    return Crypto::encrypt($text, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function decrypt($text) {
    $key = $this->loadEncryptionKey();

    try {
      return Crypto::decrypt($text, $key);
    } catch (\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {

    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function hashString($text) {
    return Crypt::hmacBase64($text, Settings::getHashSalt());
  }
}
