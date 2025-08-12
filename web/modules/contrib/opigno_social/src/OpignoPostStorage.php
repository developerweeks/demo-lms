<?php

namespace Drupal\opigno_social;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Routing\AccessAwareRouterInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the storage class for Opigno post entities.
 *
 * @package Drupal\opigno_social
 */
class OpignoPostStorage extends SqlContentEntityStorage implements OpignoPostStorageInterface {


  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected ?Request $request;

  /**
   * The route access service.
   *
   * @var \Drupal\Core\Routing\AccessAwareRouterInterface
   */
  protected AccessAwareRouterInterface $router;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    RequestStack $request_stack,
    AccessAwareRouterInterface $router,
    RouteMatchInterface $route_match,
    ...$default
  ) {
    parent::__construct(...$default);
    $this->request = $request_stack->getCurrentRequest();
    $this->router = $router;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('request_stack'),
      $container->get('router'),
      $container->get('current_route_match'),
      $entity_type,
      $container->get('database'),
      $container->get('entity_field.manager'),
      $container->get('cache.entity'),
      $container->get('language_manager'),
      $container->get('entity.memory_cache'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMainRoutePropertyFromRequest(string $prop, mixed $default = NULL): mixed {
    if (!$this->request->isXmlHttpRequest()) {
      return $this->routeMatch->getParameter($prop) ?? $default;
    }

    $referer = $this->request->server->get('HTTP_REFERER');
    $route_info = $this->router->match($referer);

    return $route_info[$prop] ?? $default;
  }

}
