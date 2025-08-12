<?php

namespace Drupal\opigno_statistics\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\opigno_statistics\Services\UserAchievementManager;
use Drush\Commands\DrushCommands;

/**
 * A Drush command file.
 */
class StatisticsCommands extends DrushCommands {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The achievements update batch size.
   */
  const BATCH_SIZE = 50;

  /**
   * The user achievement manager service.
   *
   * @var \Drupal\opigno_statistics\Services\UserAchievementManager
   */
  private UserAchievementManager $userAchievementManager;

  /**
   * The DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(UserAchievementManager $user_achievement_manager, Connection $database) {
    parent::__construct();
    $this->userAchievementManager = $user_achievement_manager;
    $this->database = $database;
  }

  /**
   * Makes update of trainings statistics.
   *
   * @param array $options
   *   Command options.
   *
   * @usage drush statistics-update --uid --gid
   *   - Removes statistics records for user with id [uid] and a training with
   *   id [gid] and re-creates them.
   * @usage drush statistics-update --uid=12 --gid=23
   *   - Removes statistics records for user with id 12 and a training with id
   *   23 and re-creates them.
   * @usage drush statistics-update --not-finished-only
   *   - Re-creates all statistics records for those trainings where user's
   *   latest attempt is not completed (status differs from "passed" or
   *   "failed").
   * @usage drush statistics-update
   *   - Removes all the trainings statistics records and re-creates them.
   *
   * @command statistics-update
   * @aliases stup
   * @option uid User entity ID.
   * @option gid Training group entity ID.
   *
   * @throws \Exception
   */
  public function updateStatistics(array $options = [
    'uid' => NULL,
    'gid' => NULL,
    'not-finished-only' => FALSE,
  ]) {
    $uid = $options['uid'] ?: NULL;
    $gid = $options['gid'] ?: NULL;
    $not_finished = $options['not-finished-only'] ?? FALSE;

    if ($not_finished || !($uid && $gid)) {
      $this->recalculateMultiple($not_finished, $uid, $gid);
      return;
    }

    $this->userAchievementManager->updateStatistics($uid, $gid, [$this, 'log']);
  }

  /**
   * Callable log wrapper.
   */
  public function log($message = NULL) {
    $this->output()->writeln($message);
  }

  /**
   * Re-calculates multiple statistics records.
   *
   * @param bool $not_finished
   *   If only not-finished records should be recalculated.
   * @param int|null $user_id
   *   User ID to recalculate records for. Ignored by default.
   * @param int|null $group_id
   *   LP group ID to recalculate records for. Ignored by default.
   */
  public function recalculateMultiple(bool $not_finished = FALSE, ?int $user_id = NULL, ?int $group_id = NULL): void {
    // Get all group IDs.
    $gids_query = $this->database->select('groups', 'g')
      ->fields('g', ['id'])
      ->condition('g.type', 'learning_path');

    if ($group_id) {
      $gids_query->condition('g.id', $group_id);
    }

    $gids = $gids_query->execute()
      ->fetchCol();

    if (!$gids) {
      return;
    }

    $data = [];
    // For each group get users' latest training attempts.
    foreach ($gids as $gid) {
      $query = $this->database->select('user_lp_status', 'uls');
      $query->addExpression('MAX(uls.id)', 'latest_id');
      $lp_ids = $query->condition('uls.gid', $gid)
        ->groupBy('uid')
        ->execute()
        ->fetchCol();

      if (!$lp_ids) {
        continue;
      }

      // Select user IDs only with not-finished latest attempts (if needed).
      $uids_query = $this->database->select('user_lp_status', 'uls')
        ->fields('uls', ['uid'])
        ->condition('uls.id', $lp_ids, 'IN');

      if ($not_finished) {
        // Get not finalized attempts OR finished ones that have broken records
        // in achievements table (marked as finalized in user_lp_status table
        // but with status "pending" in achievements table).
        $uids_query->join(UserAchievementManager::ACHIEVEMENTS_TABLE, 'olpa', 'olpa.uid = uls.uid AND olpa.gid = uls.gid');
        $broken_finalized_condition = $uids_query->andConditionGroup()
          ->condition('olpa.status', 'pending')
          ->condition('uls.finalized', 1);
        $or_condition = $uids_query->orConditionGroup()
          ->condition($broken_finalized_condition)
          ->condition('uls.finalized', 0);
        $uids_query->condition($or_condition);
      }
      if ($user_id) {
        $uids_query->condition('uls.uid', $user_id);
      }

      $uids = $uids_query->execute()
        ->fetchCol();

      // Update data for batch.
      if ($uids) {
        foreach ($uids as $uid) {
          $data[] = [
            'gid' => $gid,
            'uid' => $uid,
          ];
        }
      }
    }

    $operations = [];
    $chunks = array_chunk($data, static::BATCH_SIZE);
    foreach ($chunks as $chunk) {
      $operations[] = [
        static::class . '::updateAchievementsBatchCallback',
        [$chunk],
      ];
    }

    // Provide all the operations and the finish callback to our batch.
    $batch = [
      'title' => $this->t('Updating user achievements data...'),
      'operations' => $operations,
      'finished' => static::class . '::updateAchievementsBatchFinishCallback',
    ];

    batch_set($batch);
    drush_backend_batch_process();
  }

  /**
   * Batch execution callback to update achievements records.
   *
   * @param array $data
   *   Batch data.
   * @param \DrushBatchContext $context
   *   Batch context.
   */
  public static function updateAchievementsBatchCallback(array $data, \DrushBatchContext &$context): void {
    foreach ($data as $item) {
      UserAchievementManager::updateStatistics($item['uid'], $item['gid']);
      $context['results'][] = $item;
    }

    $context['message'] = \Drupal::translation()->formatPlural(
      count($data),
      '1 record has been updated.',
      '@count records have been updated.'
    );
  }

  /**
   * Batch finish callback.
   *
   * @param bool $success
   *   If batch finished successfully or not.
   * @param array $results
   *   Batch results.
   * @param array $operations
   *   The list of batch operations.
   */
  public static function updateAchievementsBatchFinishCallback(bool $success, array $results, array $operations): void {
    if ($success) {
      $msg = \Drupal::translation()->formatPlural(
        count($results),
        'Statistics update successfully finished. 1 record in @table table has been updated.',
        'Statistics update successfully finished. @count records in @table table have been updated.',
        ['@table' => UserAchievementManager::ACHIEVEMENTS_TABLE]
      );
      \Drupal::logger('opigno_statistics')->info($msg);
      \Drupal::messenger()->addStatus($msg);
    }
    else {
      $msg = t('Statistics update failed.');
      \Drupal::logger('opigno_statistics')->error($msg);
      \Drupal::messenger()->addError($msg);
    }
  }

}
