<?php

namespace Drupal\opigno_social;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\opigno_social\Entity\PostTypeInterface;

/**
 * Defines a list builder handler for Opigno post types.
 *
 * @package Drupal\opigno_social
 */
class PostBundleListBuilder extends ConfigEntityListBuilder {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = $this->t('Name');
    $header['machine_name'] = $this->t('Machine name');
    $header['description'] = [
      'data' => $this->t('Description'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
    ];

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    if (!$entity instanceof PostTypeInterface) {
      return parent::buildRow($entity);
    }

    $row = [
      'title' => [
        'data' => $entity->label(),
        'class' => ['menu-label'],
      ],
      'machine_name' => [
        'data' => $entity->id(),
      ],
      'description' => [
        'data' => ['#markup' => $entity->getDescription()],
      ],
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    // Place the edit operation after the operations added by field_ui.module
    // which have the weights 15, 20, 25.
    if (isset($operations['edit'])) {
      $operations['edit']['weight'] = 30;
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('There are no post types available.');

    return $build;
  }

}
