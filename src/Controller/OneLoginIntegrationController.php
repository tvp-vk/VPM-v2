<?php

namespace Drupal\latvia_auth\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Drupal\latvia_auth\AuthenticationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use OneLogin\Saml2\Auth;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * Class OneLoginSAMLController.
 *
 * @package Drupal\latvia_auth\Controller
 */
class OneLoginIntegrationController extends ControllerBase {

  /**
   * The variable that holds an instance of the OneLogin_Saml2_Auth library.
   *
   * @var \OneLogin\Saml2\Auth
   */
  protected $oneLoginSaml2Auth;

  /**
   * The variable that holds an instance of the custom AuthenticationService.
   *
   * @var \Drupal\latvia_auth\AuthenticationServiceInterface
   */
  protected $authenticationService;

  /**
   * The variable that holds an instance of the AccountProxy class.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $user;

  /**
   * The variable that holds an instance of the ConfigFactoryInterface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Default error message lv
   *
   * @var string
   */
  protected $defaultMessage = 'Kaut kas nogāja greizi, lūdzu sazinies ar lapas uzturētāju!';

  /**
   * Default error message en
   *
   * @var string
   */
  protected $defaultMessageEn = 'Something went wrong, contact page administrator.';


  /**
   * OneLoginIntegrationController constructor.
   *
   * @param \OneLogin\Saml2\Auth $one_login_saml_2_auth
   *   Reference to the OneLogin_Saml2_Auth library.
   * @param \Drupal\latvia_auth\AuthenticationServiceInterface $authentication_service
   *   Reference to the AuthenticationServiceInterface interface.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current account.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Reference to the ConfigFactoryInterface.
   */
  public function __construct(Auth $one_login_saml_2_auth, AuthenticationServiceInterface $authentication_service, AccountInterface $account, MessengerInterface $messenger, ConfigFactoryInterface $config_factory) {
    $this->oneLoginSaml2Auth = $one_login_saml_2_auth;
    $this->authenticationService = $authentication_service;
    $this->account = $account;
    $this->messenger = $messenger;
    $this->config = $config_factory->get('latvia_auth.settings');
  }

  /**
   * The create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Reference to the ContainerInterface interface.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('latvia_auth.authenticator_service'),
      $container->get('latvia_auth.authentication_service'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('config.factory')
    );
  }

  /**
   * The SingleSignOn method.
   *
   * Tries to send a request to log the user in.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a RedirectResponse to a specific page or the homepage, regarding
   *   the given settings.
   */
  public function singleSignOn(Request $request) {
    $auth_path = \Drupal::service('settings')->get('auth_path');
    $host = \Drupal::request()->getHost();

    if (!empty($auth_path) && $auth_path != $host) {
      $response =  new TrustedRedirectResponse(Url::fromUri('//' . $auth_path . '/onelogin_saml/sso', ['query' => ['returnTo' => $host]])->toString());
      return $response->send();
    }

    $params = $request->query->all();
    if (isset($params['returnTo'])) {
      $target = $params['returnTo'];
    }

    if (!$this->account->isAnonymous()) {
      if (isset($target) && strpos($target, 'latvia_auth/sso') === FALSE) {
        return new RedirectResponse(Url::fromUri('internal:' . $target));
      }
      else {
        return new RedirectResponse('/');
      }
    }

    if (isset($target) && strpos($target, 'latvia_auth/sso') === FALSE) {
      $this->oneLoginSaml2Auth->login($target);
    }
    else {
      $this->oneLoginSaml2Auth->login();
    }
  }

  /**
   * The Assertion Consumer Service method.
   *
   * Tries to handle the incoming request from the singleSignOn method.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a RedirectResponse to a specific page or the homepage, regarding
   *   the given settings.
   */
  public function assertionConsumerService(Request $request) {
    $postReq = $request->request->all();
    $host = \Drupal::request()->getHost();

    if (isset($postReq['RelayState']) && isset($postReq['SAMLResponse']) && !empty($postReq['SAMLResponse'])) {
      $auth_path = \Drupal::service('settings')->get('auth_path');

      if ($auth_path == $host) {
        $this->oneLoginSaml2Auth->processResponse();

        $errors = $this->oneLoginSaml2Auth->getErrors();
        if (!empty($errors)) {
          $settings = $this->oneLoginSaml2Auth->getSettings();
          $debug_error = '';
          if ($settings->isDebugActive()) {
            $debug_error = "<br>" . $this->oneLoginSaml2Auth->getLastErrorReason();
          }

          \Drupal::logger('latvia_auth')->error("There was at least one error processing the SAML Response<br>" . implode("<br>", $errors) . $debug_error);
          $error = $this->defaultMessage;
        }
        else {
          if($p_code = $this->authenticationService->processLoginRequest()) {
            $p_code = $this->authenticationService->cryptPcode($p_code);
          }
          else {
            \Drupal::logger('latvia_auth')->error("There was at least one error processing the SAML Response: no personal code found in SAML response!");
            $error = $this->defaultMessage;
          }
        }

        return [
          '#theme' => 'latvia_auth_redirect',
          '#data' => (isset($p_code)) ? $p_code : '',
          '#token' => $this->getToken($postReq['RelayState']),
          '#link' => $postReq['RelayState'] . '/onelogin_saml/acs',
          '#error' => (isset($error)) ? $error : '',
          '#cache' => [
            'max-age' => 0
          ],
          '#attached' => [
            'library' => [
              'latvia_auth/latvia_auth_form'
            ],
          ],
        ];
      }
    }

    if (isset($postReq['TVPAuthResponse']) && !empty($postReq['TVPAuthResponse']) && isset($postReq['TVPToken']) && !empty($postReq['TVPToken'])) {
      $errors = '';

      if ($postReq['TVPToken'] !== $this->getToken($host)) {
        $errors = 'error';
      }

      $identifier = $this->authenticationService->decryptPcode($postReq['TVPAuthResponse']);
      if(empty($identifier)) {
        $errors = 'error';
      }

      if (empty($errors)) {
        $identifier = json_decode($identifier, true);
        if($uid = $this->authenticationService->processLogin(substr($identifier['identifier'], 3))) {
          \Drupal::service('user.data')->set('latvia_auth', $uid, 'logged_in', true);
          return $this->redirect('entity.user.canonical', ['user' => $uid]);
        }
      }

      \Drupal::messenger()->addError(t('Something went wrong, contact page administrator.'));
      return $this->redirect('user.login');
    }

    return [
      '#theme' => 'latvia_auth_redirect',
      '#data' => '',
      '#link' => '',
      '#error' => $this->defaultMessage,
      '#cache' => [
        'max-age' => 0
      ]
    ];
  }

  /**
   * The singleLogOut method.
   *
   * Takes care of logging the user out.
   */
  public function singleLogOut(Request $request) {
    $auth_path = \Drupal::service('settings')->get('auth_path');
    $host = \Drupal::request()->getHost();

    if (!empty($auth_path) && $auth_path != $host) {
      if ($this->config->get('activate')) {
        $uid = \Drupal::currentUser()->id();
        if (\Drupal::service('user.data')->get('latvia_auth', \Drupal::currentUser()->id(), 'logged_in')) {
          $response =  new TrustedRedirectResponse(Url::fromUri('//' . $auth_path . '/onelogin_saml/slo', ['query' => ['returnTo' => $host, 'user' => $uid, 'token' => $this->getToken($host)]])->toString());
          return $response->send();
        }
        else {
          // If logged in by drupal auth, logout and redirect to front
          user_logout();
          return $this->redirect('<front>');
        }
      }
    }

    if ($auth_path == $host) {
      $params = $request->query->all();

      if (isset($params['token']) && $params['token'] === $this->getToken($params['returnTo']) && isset($params['user'])) {
        $target = null;
        if(isset($params['returnTo'])) {
          $target = $params['returnTo'];
        }
        $this->oneLoginSaml2Auth->logout($target . '?user=' . $params['user']);
      }
    }

    return [
      '#theme' => 'latvia_auth_redirect',
      '#data' => '',
      '#link' => '',
      '#error' => $this->defaultMessage,
      '#cache' => [
        'max-age' => 0
      ]
    ];
  }

  /**
   * Single Log Out service.
   *
   * A service for requests of logging the user out.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a RedirectResponse to a specific page or the homepage, regarding
   *   the given settings.
   */
  public function singleLogOutService(Request $request) {
    $params = $request->query->all();

    $auth_path = \Drupal::service('settings')->get('auth_path');
    $host = \Drupal::request()->getHost();

    if (!empty($auth_path) && $auth_path == $host) {
      $this->oneLoginSaml2Auth->processSLO();
      $errors = $this->oneLoginSaml2Auth->getErrors();

      if ($errors) {
        $reason = $this->oneLoginSaml2Auth->getLastErrorReason();
        \Drupal::logger('latvia_auth')->error("SLS endpoint found an error." . $reason);
      }
      if (isset($params['RelayState']) && !empty($params['RelayState'])) {
        $relay = explode('?', $params['RelayState']);

        $response =  new TrustedRedirectResponse(Url::fromUri('//' . $relay[0] . '/onelogin_saml/sls?' . $relay[1] . '&token=' . $this->getToken($relay[0]))->toString());
        return $response->send();
      }
    }

    if (!empty($auth_path) && $auth_path != $host && isset($params['user']) && isset($params['token']) && $params['token'] === $this->getToken($host)) {
      \Drupal::service('user.data')->set('latvia_auth', $params['user'], 'logged_in', false);
      user_logout();
      return $this->redirect('<front>');
    }

    return [
      '#theme' => 'latvia_auth_redirect',
      '#data' => '',
      '#link' => '',
      '#error' => $this->defaultMessage,
      '#cache' => [
        'max-age' => 0
      ]
    ];
  }

  /**
   * Generate secure token
   *
   * @param string $host
   * @return string
   */
  public function getToken($host) {
    return hash('sha256', $host . \Drupal::service('settings')->get('crypt_key') . date('Y.m.d'));
  }
}
