<?php

/**
 * @file
 * Contains \Drupal\latvia_auth\EventSubscriber\RouteSubscriber.
 */

namespace Drupal\latvia_auth\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events and restrict access to user.pass route.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The router builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routerBuilder;

  /**
   * Constructs a Route subscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Configuration Factory.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $router_builder
   *   The router builder service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, RouteBuilderInterface $router_builder) {
    $this->configFactory = $configFactory;
    $this->routerBuilder = $router_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    if (\Drupal::service('site.path') == 'sites/tvp_auth') {
      $disallow_routes = [
        'user.login',
        'user.register',
        'user.pass',
      ];
      foreach ($disallow_routes as $disallow_route) {
        if ($route = $collection->get($disallow_route)) {
          $route->setRequirement('_access', 'FALSE');
        }
      }
    }
    else {
      $config = $this->configFactory->get('latvia_auth.settings');

      if ($config->get('disable_default_login')) {
        if ($route = $collection->get('user.pass')) {
          $route->setRequirement('_access', 'FALSE');
        }
      }

      if ($config->get('activate')) {
        if ($logout_route = $collection->get('user.logout')) {
          $logout_route->setPath('/onelogin_saml/slo');
        }
      }
    }
  }

  /**
   * Rebuilds the router when latvia_auth.settings is changed.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    if ($event->getConfig()->getName() === 'latvia_auth.settings') {
      $this->routerBuilder->setRebuildNeeded();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[ConfigEvents::SAVE][] = ['onConfigSave', 0];
    return $events;
  }
}
