<?php

/**
 * @file
 * Contains views-related hooks and functions.
 */

/**
 * Implements hook_views_data_alter().
 */
function opigno_statistics_views_data_alter(array &$data) {
  // Add a view relation between group_content_field_data and
  // opigno_learning_path_achievements tables.
  $data['group_content_field_data']['opigno_learning_path_achievements']['relationship'] = [
    'title' => t('Opigno User LP Achievements'),
    'label' => t('Opigno User LP Achievements'),
    'group' => t('Opigno statistics'),
    'provider' => 'opigno_statistics',
    'id' => 'standard',
    'base' => 'opigno_learning_path_achievements',
    'base field' => 'gid',
    'field' => 'gid',
    'extra' => [
      [
        'left_field' => 'entity_id',
        'field' => 'uid',
      ],
    ],
  ];

  // Add the latest LP Status field.
  $data['group_content_field_data']['latest_user_lp_status'] = [
    'title' => t('Latest user LP status'),
    'provider' => 'opigno_statistics',
    'field' => [
      'title' => t('Latest user LP status'),
      'help' => t('Returns the latest user LP status.'),
      'id' => 'opigno_statistics_latest_user_lp_status',
    ],
  ];
}

/**
 * Implements hook_views_data().
 */
function opigno_statistics_views_data() {
  // Integrate opigno_learning_path_achievements with views.
  $data['opigno_learning_path_achievements'] = [
    'table' => [
      'group' => t('Opigno User LP Achievements'),
      'provider' => 'opigno_statistics',
      'base' => [
        'field' => 'id',
        'title' => t('Opigno User LP Achievements'),
      ],
    ],
    'score' => [
      'title' => t('User LP achievement score'),
      'field' => [
        'id' => 'opigno_statistics_user_achievement_score',
      ],
    ],
    'status' => [
      'title' => t('User LP achievement status'),
      'field' => [
        'id' => 'standard',
      ],
    ],
    'time' => [
      'title' => t('User LP achievement time spent'),
      'field' => [
        'id' => 'opigno_statistics_user_achievement_time',
      ],
    ],
    'registered' => [
      'title' => t('User LP achievement registered date'),
      'sort' => [
        'id' => 'date',
      ],
    ],
  ];

  return $data;
}
