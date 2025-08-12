<?php

namespace Drupal\opigno_module\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Defines the event subscriber service.
 *
 * @package Drupal\opigno_module\EventSubscriber
 */
class QueryPathEventSubscriber implements EventSubscriberInterface {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * QueryPathEventSubscriber constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The request stack service.
   */
  public function __construct(RouteMatchInterface $route_match, RequestStack $request) {
    $this->routeMatch = $route_match;
    $this->requestStack = $request;
  }

  /**
   * Remember the query string (with the table sort) as a session variable.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function checkRedirection(ResponseEvent $event): void {
    // Remember the query only for activities, modules and groups listings.
    $available_routes = [
      'entity.opigno_activity.collection',
      'entity.opigno_module.collection',
      'entity.group.collection',
    ];
    $current_route = $this->routeMatch->getRouteName();
    if (!in_array($current_route, $available_routes)) {
      return;
    }

    $request = $this->requestStack->getCurrentRequest();
    $session = $request->getSession();
    $param = $request->query->all();

    // If we have an empty page (without the new query params), we need to
    // load the previous one.
    if (!empty($param)) {
      $session->set($current_route, $param);
      return;
    }

    $order_values = $session->get($current_route);
    if (!empty($order_values)) {
      unset($order_values['ajax_page_state']);
      $url = Url::fromRoute($current_route);
      $url->setOption('query', $order_values);
      $response = new RedirectResponse($url->toString());
      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['checkRedirection'];
    return $events;
  }

}
