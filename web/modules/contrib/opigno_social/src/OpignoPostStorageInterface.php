<?php

namespace Drupal\opigno_social;

use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Defines an interface for Opigno post entity storage classes.
 *
 * @package Drupal\opigno_social
 */
interface OpignoPostStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets the main route property from the request.
   *
   * @param string $prop
   *   The name of the property to get from the request.
   * @param mixed|null $default
   *   The default value that will be returned if the property doesn't exist.
   *
   * @return mixed
   *   The current route property. For ajax requests will be returned the
   *   property of the referer route object.
   */
  public function getMainRoutePropertyFromRequest(string $prop, mixed $default = NULL): mixed;

}
