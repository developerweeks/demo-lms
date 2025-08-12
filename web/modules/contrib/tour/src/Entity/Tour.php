<?php

namespace Drupal\tour\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\tour\TipPluginInterface;
use Drupal\tour\TipsPluginCollection;
use Drupal\tour\TourInterface;

/**
 * Defines the configured tour entity.
 *
 * @ConfigEntityType(
 *   id = "tour",
 *   label = @Translation("Tour"),
 *   label_collection = @Translation("Tours"),
 *   label_singular = @Translation("tour"),
 *   label_plural = @Translation("tours"),
 *   label_count = @PluralTranslation(
 *     singular = "@count tour",
 *     plural = "@count tours",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\tour\TourViewBuilder",
 *     "access" = "Drupal\tour\TourAccessControlHandler",
 *     "list_builder" = "Drupal\tour\TourListBuilder",
 *     "form" = {
 *       "default" = "Drupal\tour\Form\TourForm",
 *       "add" = "Drupal\tour\Form\TourForm",
 *       "edit" = "Drupal\tour\Form\TourForm",
 *       "edit_tips" = "Drupal\tour\Form\TourTipsListForm",
 *       "delete" = "Drupal\tour\Form\TourDeleteForm",
 *       "clone" = "Drupal\tour\Form\TourCloneForm",
 *     },
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/user-interface/tour/manage/{tour}",
 *     "delete-form" = "/admin/config/user-interface/tour/manage/{tour}/delete",
 *     "clone-form" = "/admin/config/user-interface/tour/manage/{tour}/clone",
 *   },
 *   admin_permission = "administer tour",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "routes",
 *     "tips",
 *   },
 *   lookup_keys = {
 *     "routes.*.route_name"
 *   }
 * )
 */
class Tour extends ConfigEntityBase implements TourInterface {

  /**
   * The name (plugin ID) of the tour.
   *
   * @var string|null
   */
  protected string|null $id;

  /**
   * The label of the tour.
   *
   * @var string
   */
  protected string $label;

  /**
   * The routes on which this tour should be displayed.
   *
   * @var array
   */
  protected $routes = [];

  /**
   * The modules needed by this tour.
   *
   * @var string
   */
  protected string $module = '';

  /**
   * The routes on which this tour should be displayed, keyed by route id.
   *
   * @var array
   */
  protected $keyedRoutes;

  /**
   * Holds the collection of tips that are attached to this tour.
   *
   * @var \Drupal\tour\TipsPluginCollection
   */
  protected TipsPluginCollection $tipsCollection;

  /**
   * The array of plugin config.
   *
   * Only used for export and to populate the $tipsCollection.
   *
   * @var array
   */
  protected $tips = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);

    $this->tipsCollection = new TipsPluginCollection(\Drupal::service('plugin.manager.tour.tip'), $this->tips);
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutes(): array {
    return $this->routes;
  }

  /**
   * {@inheritdoc}
   */
  public function getModule(): string {
    return $this->module;
  }

  /**
   * {@inheritdoc}
   */
  public function getTip(string $id): TipPluginInterface {
    return $this->tipsCollection->get($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getTips(): array {
    $tips = [];
    foreach ($this->tips as $id => $tip) {
      $tips[$id] = $this->getTip($id);
    }

    \Drupal::moduleHandler()->alter('tour_tips', $tips, $this);
    return $tips;
  }

  /**
   * {@inheritdoc}
   */
  public function hasMatchingRoute(string $route_name, array $route_params): bool {
    if (!isset($this->keyedRoutes)) {
      $this->keyedRoutes = [];
      foreach ($this->getRoutes() as $route) {
        $this->keyedRoutes[$route['route_name']] = $route['route_params'] ?? [];
      }
    }
    if (!isset($this->keyedRoutes[$route_name])) {
      // We don't know about this route.
      return FALSE;
    }
    if (empty($this->keyedRoutes[$route_name])) {
      // We don't need to worry about route params, the route name is enough.
      return TRUE;
    }
    foreach ($this->keyedRoutes[$route_name] as $key => $value) {
      // If a required param is missing or doesn't match, return FALSE.
      if (empty($route_params[$key]) || $route_params[$key] !== $value) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    foreach ($this->tipsCollection as $instance) {
      $definition = $instance->getPluginDefinition();
      $this->addDependency('module', $definition['provider']);
    }

    // When the TourForm is saved set the dependencies based on user input.
    $modules_array = explode(',', str_replace(' ', '', $this->getModule()));
    if (is_array($modules_array) && isset($this->form_id)) {
      asort($modules_array);
      $this->dependencies['enforced']['module'] = $modules_array;
    }

    return $this;
  }

}
