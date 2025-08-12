<?php

namespace Drupal\tour;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathMatcher;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\tour\Entity\Tour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TourHelper.
 *
 * Provides helper methods for the Tour module.
 */
class TourHelper implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new TourHelper object.
   *
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   *   Checks the current route.
   * @param \Drupal\Core\Path\PathMatcher $pathMatcher
   *   Path matching helper service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected CurrentRouteMatch $currentRouteMatch,
    protected PathMatcher $pathMatcher,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_route_match'),
      $container->get('path_matcher'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
    );
  }

  /**
   * Sets $route_name with custom handling for the front page.
   *
   * @return string
   *   The route name.
   */
  public function checkRoute(): string {
    $route_name = $this->currentRouteMatch->getRouteName();
    if ($this->pathMatcher->isFrontPage()) {
      $route_name = '<front>';
    }
    return $route_name;
  }

  /**
   * Returns tour entities from current route regardless of access level.
   *
   * @return array
   *   An associative array of tour entities keyed by their entity IDs.
   */
  public function loadTourEntities(): array {
    $route_match = $this->currentRouteMatch;
    $route_name = $this->checkRoute();
    try {
      $results = $this->entityTypeManager->getStorage('tour')
        ->getQuery()
        ->condition('routes.*.route_name', $route_name)
        ->condition('status', TRUE)
        ->accessCheck(FALSE)
        ->execute();

      if (empty($results)) {
        return $results;
      }

      // The $results match the route name. Test each of these to see if it
      // also matches the route parameters.
      $tours = Tour::loadMultiple(array_keys($results));
      $matches = [];
      $route_parameters = $route_match->getRawParameters()->all();

      foreach ($tours as $tour_id => $tour) {
        $tour_routes = $tour->getRoutes();
        foreach ($tour_routes as $tour_route) {
          if ($tour_route['route_name'] != $route_name) {
            // Ignore tour routes that are not related to this route.
            continue;
          }

          // Massage the route parameters using the tour route parameters.
          $params = $route_parameters;
          foreach ($tour_route['route_params'] ?? [] as $key => $tour_param) {
            switch ($key) {
              case 'id':
              case 'dashboard':
                if (($entity = $route_match->getParameter('dashboard')) && ($entity instanceof ConfigEntityInterface)) {
                  if ($entity->id() === $tour_param) {
                    $params['id'] = $entity->id();
                  }
                }
                break;

              case 'node':
              case 'taxonomy_term':
                if (($entity = $route_match->getParameter($key)) && ($entity instanceof EntityInterface)) {
                  if ($entity->id() === $tour_param) {
                    $params['bundle'] = $entity->id();
                  }
                }
                break;

              case 'bundle':
              case 'node_type':
                if (($entity = $route_match->getParameter('node')) && ($entity instanceof EntityInterface)) {
                  if ($entity->bundle() === $tour_param) {
                    $params['bundle'] = $entity->bundle();
                  }
                }
                break;

              case 'taxonomy_vocabulary':
                if (($vocab = $route_match->getParameter('taxonomy_vocabulary')) && ($vocab instanceof VocabularyInterface)) {
                  if ($vocab->id() === $tour_param) {
                    $params['bundle'] = $vocab->id();
                  }
                }
                break;

              default:
                break;
            }
          }

          // Test that the tour matches the route name and parameters.
          if ($tour->hasMatchingRoute($route_name, $params)) {
            $matches[$tour_id] = $results[$tour_id];
          }
        }
      }

      return $matches;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      return [];
    }
  }

  /**
   * Check settings and set string values for Tour/No Tour.
   *
   * @return array
   *   An associative array of tour labels.
   */
  public function getTourLabels(): array {
    $config = $this->configFactory->get('tour.settings');
    if ($config->get('display_custom_labels')) {
      $tour_avail_text = $config->get('tour_avail_text');
      $tour_no_avail_text = $config->get('tour_no_avail_text');
    }
    else {
      $tour_avail_text = $this->t('Tour');
      $tour_no_avail_text = $this->t('No tour');
    }

    return [
      'tour_avail_text' => $tour_avail_text,
      'tour_no_avail_text' => $tour_no_avail_text,
    ];
  }

  /**
   * Check settings and if empty tours should be hidden.
   *
   * @param bool $isEmpty
   *   If the current page has any tips.
   *
   * @return bool
   *   If the tour should be hidden or not.
   */
  public function shouldEmptyBeHidden(bool $isEmpty): bool {
    if ($isEmpty) {
      $config = $this->configFactory->get('tour.settings');
      if ($config->get('hide_tour_when_empty')) {
        return TRUE;
      }
      return FALSE;
    }
    return FALSE;
  }

}
