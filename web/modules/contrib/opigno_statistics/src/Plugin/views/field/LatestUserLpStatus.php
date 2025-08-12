<?php

namespace Drupal\opigno_statistics\Plugin\views\field;

use Drupal\Core\Database\Connection;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_statistics\StatisticsPageTrait;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a field handler to display the latest user LP status.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("opigno_statistics_latest_user_lp_status")
 */
class LatestUserLpStatus extends FieldPluginBase {

  use StatisticsPageTrait;

  /**
   * The DB connection service.
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
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity ?? NULL;
    $status = 'no status';

    if (!$entity instanceof GroupContentInterface) {
      return $this->buildStatus($status);
    }

    $uid = $entity->get('entity_id')->getString();
    $gid = $entity->get('gid')->getString();
    if (!$uid || !$gid) {
      return $this->buildStatus($status);
    }

    $group = $entity->getGroup();
    if ($group instanceof GroupContentInterface && LPStatus::isCertificateExpired($group, $uid)) {
      $status = 'expired';
    }
    else {
      $status = $this->database->select('user_lp_status', 'uls')
        ->fields('uls', ['status'])
        ->condition('uls.uid', $uid)
        ->condition('uls.gid', $gid)
        ->orderBy('uls.id', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();
    }

    return $this->buildStatus($status);
  }

}
