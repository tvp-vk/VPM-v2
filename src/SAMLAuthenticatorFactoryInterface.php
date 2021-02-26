<?php

namespace Drupal\latvia_auth;

/**
 * Interface SamlAuthenticatorServiceInterface.
 *
 * @package Drupal\latvia_auth
 */
interface SAMLAuthenticatorFactoryInterface {

  /**
   * Creates and/or returns in instance of the OneLogin_Saml2_Auth library.
   *
   * @param array $settings
   *   returns in instance of the OneLogin_Saml2_Auth library.
   */
  public function createFromSettings(array $settings);

}
