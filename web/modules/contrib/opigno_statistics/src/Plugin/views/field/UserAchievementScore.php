<?php

namespace Drupal\opigno_statistics\Plugin\views\field;

use Drupal\Core\Database\Connection;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_statistics\StatisticsPageTrait;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to display user LP achievement score.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("opigno_statistics_user_achievement_score")
 */
class UserAchievementScore extends NumericField {

  use StatisticsPageTrait;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $database, ...$default) {
    parent::__construct(...$default);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('database'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $score = parent::render($values);
    // Get the group entity.
    $entity = $values->_entity;
    $group = $entity instanceof GroupContentInterface ? $entity->getGroup() : NULL;
    if (!$entity instanceof GroupContentInterface || !$group instanceof GroupInterface) {
      return $score;
    }

    // Get users with the training expired certification.
    $expired_uids = $this->getExpiredUsers($group);
    $uid = $entity->get('entity_id')->getString() ?? 0;
    $expired = !empty($expired_uids) && in_array($uid, $expired_uids);
    $score = $score && !$expired ? $score : 0;

    return $this->buildScore((int) $score);
  }

}
