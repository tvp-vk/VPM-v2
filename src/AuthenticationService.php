<?php

namespace Drupal\latvia_auth;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use OneLogin\Saml2\Auth;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\latvia_auth\Cryptor;

/**
 * AuthenticationService Class.
 *
 * This class takes care of logging the user in and/or creating one when not
 * present yet. The difference with the SAMLAuthenticatorFactory, is that that
 * class instantiates the OneLogin_Saml2_Auth library with certain settings,
 * while this class uses that instance to log the user in.
 *
 * @package Drupal\latvia_auth
 */
class AuthenticationService implements AuthenticationServiceInterface {

  /**
   * The variable that holds an instance of the OneLogin_Saml2_Auth library.
   *
   * @var \OneLogin\Saml2\Auth
   */
  private $oneLoginSaml2Auth;

  /**
   * The variable that holds an instance of the EntityTypeManagerInterface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Private personal identifier
   *
   * @var string
   */
  protected $identifier = "http://schemas.xmlsoap.org/ws/2005/05/identity/claims/privatepersonalidentifier";

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Drupal\latvia_auth\Cryptor definition.
   *
   * @var Drupal\latvia_auth\Cryptor
   */
  protected $cryptor;

  /**
   * AuthenticationService constructor.
   *
   * @param \OneLogin\Saml2\Auth $one_login_saml_2_auth
   *   Reference to OneLogin_Saml2_Auth.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Reference to EntityTypeManagerInterface.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger Factory.
   * @param \Drupal\latvia_auth\Cryptor $cryptor
   */
  public function __construct(Auth $one_login_saml_2_auth, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, LoggerChannelFactoryInterface $logger_factory, Cryptor $cryptor) {
    $this->oneLoginSaml2Auth = $one_login_saml_2_auth;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->loggerFactory = $logger_factory;
    $this->cryptor = $cryptor;
  }

  /**
   * The processLoginRequest function.
   *
   * This function takes the attributes sent with the login request from
   * OneLogin. It tries to find a personal identifier in SAML.
   *
   * @return string personal identifier or false
   */
  public function processLoginRequest() {
    // If there is no nameId found, logging in with SAML has no use. So redirect
    // the user back to the homepage with a message accordingly.
    $pk = $this->oneLoginSaml2Auth->getNameId();
    if (empty($pk)) {
      $this->loggerFactory->get('latvia_auth')->error('A NameId could not be found. Please supply a NameId in your SAML Response.');

      return false;
    }

    if (strncmp($pk, "PK:", 3) === 0) {
      return $pk;
    }

    return false;
  }

  /**
   * It tries to find a user by passed personal identifier and process the drupal auth.
   *
   * @return user id if successfully logged in or null
   */
  public function processLogin($identifier) {
    if ($account = $this->load($identifier)) {
      $account = $this->userLoginFinalize($account);

      return $account->id();
    }

    // No user found, show error message
    $this->loggerFactory->get('latvia_auth')->error('No user found with passed personal code');
    return NULL;
  }

  /**
   * Load a Drupal user based on an external identifier.
   *
   * @param string $identifier
   *   The unique, external authentication name provided by authentication
   *   provider.
   *
   * @return \Drupal\user\UserInterface
   *   The loaded Drupal user.
   */
  public function load($identifier) {
    $identifier = \Drupal::service('latvia_auth.cryptor')->hashString($identifier);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['field_user_personal_code' => $identifier]);
    if ($users && count($users) == 1) {
      return reset($users);
    }

    return FALSE;
  }

  /**
   * Finalize logging in the external user.
   *
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user to finalize login for.
   *
   * @return \Drupal\user\UserInterface
   *   The logged in Drupal user.
   */
  public function userLoginFinalize(UserInterface $account) {
    user_login_finalize($account);
    $this->loggerFactory->get('latvia_auth')->notice('Successfully logged in by latvija.lv by user %name', ['%name' => $account->getAccountName()]);
    \Drupal::service('user.data')->set('latvia_auth', $account->id(), 'logged_in', true);

    return $account;
  }

  /**
   * Crypt people personal code
   *
   * @param string $personal_code
   *
   * @return string
   */
  public function cryptPcode($personal_code) {
    $user_data = [
      'identifier' => $personal_code
    ];
    return $this->cryptor->encrypt(json_encode($user_data));
  }

  /**
   * Encrypt passed data
   *
   * @param string $data
   *
   * @return string
   */
  public function decryptPcode($data) {
    return $this->cryptor->decrypt($data);
  }
}
