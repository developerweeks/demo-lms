<?php

namespace Drupal\opigno_social\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface to define an Opigno post type entity.
 *
 * @package Drupal\opigno_social\Entity
 */
interface PostTypeInterface extends ConfigEntityInterface {

  /**
   * Gets the post bundle description.
   *
   * @return string
   *   The description of the post bundle.
   */
  public function getDescription(): string;

}
