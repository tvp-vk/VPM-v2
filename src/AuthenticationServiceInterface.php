<?php

namespace Drupal\latvia_auth;

/**
 * Interface AuthenticationServiceInterface.
 *
 * The interface for the AuthenticationService.
 * Defines methods that should be used when the interface is implemented.
 *
 * @package Drupal\latvia_auth
 */
interface AuthenticationServiceInterface {

  /**
   * The processLoginRequest method.
   *
   * Gets the username or email address from the OneLogin request and calls
   * other functions accordingly.
   *
   * @return mixed
   *   Returns multiple things, depending on the part of the code that is
   *   executing.
   */
  public function processLoginRequest();
}
