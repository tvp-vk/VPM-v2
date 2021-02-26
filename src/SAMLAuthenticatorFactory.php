<?php

namespace Drupal\latvia_auth;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Utils;
use Drupal\Core\Url;

/**
 * Class SamlAuthenticatorFactory.
 *
 * @package Drupal\latvia_auth
 */
class SAMLAuthenticatorFactory implements SAMLAuthenticatorFactoryInterface {

  /**
   * The variable that holds an instance of ConfigFactoryInterface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * SamlAuthenticatorFactory constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Reference to ConfigFactoryInterface.
   *
   * @throws \Drupal\Core\Extension\MissingDependencyException
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Settings for the OneLogin_Saml2_Auth library.
   *
   * Creates an instance of the OneLogin_Saml2_Auth library with default and,
   * if given, custom settings.
   *
   * @param array $settings
   *   Custom settings for the initialization of the OneLogin_Saml2_Auth
   *   library.
   *
   * @return Auth
   *   Returns a new instance of the OneLogin_Saml2_Auth library.
   */
  public function createFromSettings(array $settings = []) {
    $path = \Drupal::service('settings')->get('auth_path');

    $config = $this->configFactory->get('latvia_auth.settings');

    $cert = $config->get('cert');
    if(empty($cert)) {
      $cert = file_get_contents(DRUPAL_ROOT."/../certs/latvia_auth/cert.txt", FILE_USE_INCLUDE_PATH);
    }

    $default_settings = [
      'strict' => true,
      'debug' => false,
      'sp' => [
        'entityId' => 'https://' . $path . '/',
        'assertionConsumerService' => [
          'url' => Url::fromRoute('latvia_auth.acs', [], ['absolute' => TRUE])->toString(),
          'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST'
        ],
        'singleLogoutService' => [
          'url' => Url::fromRoute('latvia_auth.slo', [], ['absolute' => TRUE])->toString(),
          'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        ],
      ],
      'idp' => [
        'entityId' => 'http://www.latvija.lv/sts',
        'singleSignOnService' => [
          'url' => \Drupal::service('settings')->get('latvia_saml_path'),
          'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        ],
        'singleLogoutService' => [
          'url' => \Drupal::service('settings')->get('latvia_saml_path'),
          'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        ],
        'x509cert' => $cert,
      ],
      'contactPerson' => [
        'technical' => [
          'company' => 'VRAA',
          'givenName' => 'Ieva',
          'surName' => 'Metra',
          'emailAddress' => 'ieva.metra@vraa.gov.lv',
          'telephoneNumber' => '+371 26670388'
        ],
        'support' => [
          'company' => 'VRAA',
          'givenName' => 'Ieva',
          'surName' => 'Metra',
          'emailAddress' => 'ieva.metra@vraa.gov.lv',
          'telephoneNumber' => '+371 26670388'
        ],
      ],
      'organization' => [
        'lv' => [
          'name' => 'VRAA',
          'displayname' => 'State Regional Development Agency',
          'url' => 'http://vraa.gov.lv/'
        ],
      ]
    ];

    $settings = NestedArray::mergeDeep($default_settings, $settings);

    $auth = new Auth($settings);
    Utils::setProxyVars(true);

    return $auth;
  }

}
