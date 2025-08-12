<?php

namespace Drupal\opigno_statistics\Services;

use Drupal\group\Entity\Group;
use Drupal\group\GroupMembership;
use Drupal\opigno_learning_path\LearningPathContent;
use Drupal\user\Entity\User;

/**
 * Defines user achievements manager service to re-calculate statistics.
 *
 * All methods in this service are static to make them callable in drush batch.
 *
 * @package Drupal\opigno_statistics\Services
 */
class UserAchievementManager {

  /**
   * User LP achievements table name.
   */
  const ACHIEVEMENTS_TABLE = 'opigno_learning_path_achievements';

  /**
   * User LP step achievements table name.
   */
  const ACHIEVEMENTS_STEPS_TABLE = 'opigno_learning_path_step_achievements';

  /**
   * Stores training achievements data.
   */
  public static function opignoLearningPathSaveAchievements(int $gid, int $user_uid) {
    try {
      opigno_learning_path_save_achievements($gid, $user_uid);
    }
    catch (\Exception $e) {
      \Drupal::logger('opigno_statistics')->error($e->getMessage());
      \Drupal::messenger()->addError($e->getMessage());
    }
  }

  /**
   * Builds up a full list of all the steps in a group for a user.
   */
  public static function getLearningPathGetAllSteps(int $gid, int $user_uid) {
    if ($steps = LearningPathContent::getAllStepsOnlyModules($gid, $user_uid, TRUE)) {
      foreach ($steps as $step) {
        // Each training steps.
        try {
          // Save current step parent achievements.
          $parent_id = 0;
          if (isset($step['parent'])) {
            $parent_id = opigno_learning_path_save_step_achievements($gid, $user_uid, $step['parent']);
          }
          opigno_learning_path_save_step_achievements($gid, $user_uid, $step, $parent_id);
        }
        catch (\Exception $e) {
          \Drupal::logger('opigno_statistics')->error($e->getMessage());
          \Drupal::messenger()->addError($e->getMessage());
        }
      }
    }
  }

  /**
   * Truncate achievements' table helper by uid and gid.
   */
  public static function dropAllTables($uid, $gid) {
    $tables = [
      self::ACHIEVEMENTS_TABLE,
      self::ACHIEVEMENTS_STEPS_TABLE,
    ];
    $database = \Drupal::database();

    if ($uid && !$gid) {
      foreach ($tables as $table) {
        $database->delete($table)
          ->condition('uid', $uid)
          ->execute();
      }
    }
    elseif ($gid && !$uid) {
      foreach ($tables as $table) {
        $database->delete($table)
          ->condition('gid', $gid)
          ->execute();
      }
    }
    elseif ($uid && $gid) {
      foreach ($tables as $table) {
        $database->delete($table)
          ->condition('uid', $uid)
          ->condition('gid', $gid)
          ->execute();
      }
    }
    else {
      foreach ($tables as $table) {
        $database->truncate($table)->execute();
      }
    }
  }

  /**
   * Makes update of trainings statistics.
   */
  public static function updateStatistics($uid = NULL, $gid = NULL, ?callable $closure = NULL) {
    $group = Group::load($gid);
    if (!$group) {
      static::dropAllTables($uid, $gid);
      return;
    }

    $user = User::load($uid);
    if (!$user) {
      static::dropAllTables($uid, $gid);
      return;
    }

    static::log($closure, 'Group (' . $gid . ') - "' . $group->label() . '"');
    if (($members = $group->getMember($user)) && $members instanceof GroupMembership) {
      static::dropAllTables($uid, $gid);
      static::log($closure, ' - user (' . $uid . ') - "' . $user->getDisplayName() . '"');
      static::opignoLearningPathSaveAchievements($gid, $uid);
      static::getLearningPathGetAllSteps($gid, $uid);
    }
  }

  /**
   * Callable log wrapper.
   */
  private static function log(?callable $closure, string $string) {
    $closure ? call_user_func($closure, $string) : NULL;
  }

}
