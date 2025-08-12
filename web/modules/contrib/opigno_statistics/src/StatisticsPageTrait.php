<?php

namespace Drupal\opigno_statistics;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\group\Entity\Group;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_module\Entity\OpignoModule;

/**
 * Common helper methods for a statistics pages.
 */
trait StatisticsPageTrait {

  /**
   * Builds circle indicator for a value.
   *
   * @param float $value
   *   From 0 to 1.
   *
   * @return array
   *   Render array.
   */
  protected function buildCircleIndicator($value) {
    $width = 100;
    $height = 100;
    $cx = $width / 2;
    $cy = $height / 2;
    $radius = min($width / 2, $height / 2);
    $value_rad = $value * 2 * 3.14159 - 3.14159 / 2;

    return [
      '#theme' => 'opigno_statistics_circle_indicator',
      '#width' => $width,
      '#height' => $height,
      '#cx' => $cx,
      '#cy' => $cy,
      '#radius' => $radius,
      '#x' => round($cx + $radius * cos($value_rad), 2),
      '#y' => round($cy + $radius * sin($value_rad), 2),
      '#val_rad' => $value_rad < 3.14159 / 2,
    ];
  }

  /**
   * Builds value for the training progress block.
   *
   * @param string $label
   *   Value label.
   * @param string $value
   *   Value.
   * @param string $help_text
   *   Help text.
   *
   * @return array
   *   Render array.
   */
  protected function buildValue($label, $value, $help_text = NULL) {
    return [
      '#theme' => 'opigno_statistics_circle_indicator_value',
      '#value' => $value,
      '#label' => $label,
      '#help_text' => $help_text,
    ];
  }

  /**
   * Builds value with a indicator for the training progress block.
   *
   * @param string $label
   *   Value label.
   * @param float $value
   *   From 0 to 1.
   * @param null|string $value_text
   *   Formatted value (optional).
   * @param string $help_text
   *   Help text.
   *
   * @return array
   *   Render array.
   */
  protected function buildValueWithIndicator($label, $value, $value_text = NULL, $help_text = NULL) {
    $value_text = $value_text ?? $this->t('@percent%', [
      '@percent' => round(100 * $value),
    ]);

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['value-indicator-wrapper'],
      ],
      'value' => $this->buildValue($label, $value_text, $help_text),
      'indicator' => $this->buildCircleIndicator($value),
    ];
  }

  /**
   * Prepare the settings array to build the donut chart.
   *
   * @param float $value
   *   The value of the statistics parameter.
   * @param string $id
   *   The canvas element ID to display the chart in.
   *
   * @return array
   *   Settings array to build the donut chart.
   */
  protected function buildDonutChart(float $value, string $id): array {
    $percent = round(100 * $value);

    // Get color palette.
    $theme = \Drupal::theme()->getActiveTheme()->getName();
    $theme_decorator = \Drupal::hasService('color.theme_decorator');
    if ($theme_decorator) {
      $info = color_get_info($theme);
      if (isset($info)) {
        $color_palette = \Drupal::service('color.theme_decorator')
          ->getPalette($theme);
      }
    }
    else {
      $color_palette = color_get_palette($theme);
    }

    $color = $color_palette['desktop_link'] ?? '#4ad3b0';

    return [
      'id' => $id,
      'type' => 'doughnut',
      'datasets' => [
        [
          'data' => [$percent, 100 - $percent],
          'backgroundColor' => [$color, '#d5d5d5'],
          'hoverBackgroundColor' => [$color, '#d5d5d5'],
          'borderWidth' => 0,
        ],
      ],
      'options' => [
        'aspectRatio' => 1,
        'cutout' => '85%',
        'elements' => [
          'arc' => ['roundedCornersFor' => 0],
          'center' => [
            'text' => "$percent%",
            'fontColor' => $color_palette['desktop_text'] ?? '#2F3758',
            'fontStyle' => '800',
            'fontFamily' => 'Montserrat,Geneva,Arial,Helvetica,sans-serif',
            'minFontSize' => 16,
            'maxFontSize' => 43,
            'lineHeight' => 30,
          ],
        ],
      ],
    ];
  }

  /**
   * Builds render array for a score value.
   *
   * @param int $value
   *   Score.
   *
   * @return array
   *   Render array.
   */
  protected function buildScore($value) {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['learning-path-progress d-flex flex-column'],
      ],
      'score' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['progress-content'],
        ],
        'score_value' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'class' => ['progress-value'],
          ],
          '#value' => $value . '%',
        ],
      ],
      'score_bar' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['progress-bar'],
        ],
        'score_bar_inner' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['progress-progress'],
            'style' => "width: $value%;",
          ],
        ],
      ],
    ];
  }

  /**
   * Builds render array for a status value.
   *
   * @param string $value
   *   Status.
   * @param bool $show_text
   *   TRUE if you need to add text.
   *
   * @return array
   *   Render array.
   */
  protected function buildStatus(string $value, bool $show_text = TRUE): array {
    switch (strtolower($value)) {
      default:
      case 'pending':
        $text = $show_text ? $this->t('Pending') : '';
        $status_icon = 'icon_state_pending';
        $status_text = Markup::create('<i class="fi fi-rr-menu-dots"></i>' . $text);
        break;

      case 'expired':
        $text = $show_text ? $this->t('Expired') : '';
        $status_icon = 'icon_state_expired';
        $status_text = Markup::create('<i class="fi fi-rr-cross-small"></i>' . $text);
        break;

      case 'failed':
        $text = $show_text ? $this->t('Failed') : '';
        $status_icon = 'icon_state_failed';
        $status_text = Markup::create('<i class="fi fi-rr-cross-small"></i>' . $text);
        break;

      case 'completed':
      case 'passed':
        $text = $show_text ? $this->t('Success') : '';
        $status_icon = 'icon_state_passed';
        $status_text = Markup::create('<i class="fi fi-rr-checknew"></i>' . $text);
        break;
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => [
        'class' => ['icon_state', $status_icon],
      ],
      '#value' => $status_text,
    ];
  }

  /**
   * Returns users with the training expired certification.
   *
   * They shouldn't have an attempts after expiration date.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   *
   * @return array
   *   Users IDs.
   */
  protected function getExpiredUsers(Group $group) {
    $output = [];
    $gid = $group->id();
    try {
      // Get users with the training expired certification.
      $output = $this->database->select('user_lp_status_expire', 'lpe')
        ->fields('lpe', ['uid'])
        ->condition('gid', $gid)
        ->condition('expire', time(), '<')
        ->execute()->fetchCol();
    }
    catch (\Exception $e) {
      \Drupal::logger('opigno_statistics')->error($e->getMessage());
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }

    if ($output) {
      // Filter users with no attempts after expiration date.
      $output = array_filter($output, function ($uid) use ($group) {
        $gid = $group->id();
        $expiration_set = LPStatus::isCertificateExpireSet($group);
        if ($expiration_set) {
          $expire_timestamp = LPStatus::getCertificateExpireTimestamp($gid, $uid);
          if ($expire_timestamp) {
            $result = $this->database->select('user_module_status', 'ums')
              ->fields('ums', ['id'])
              ->condition('learning_path', $gid)
              ->condition('user_id', $uid)
              ->condition('finished', $expire_timestamp, '>')
              ->execute()->fetchField();

            if ($result) {
              return FALSE;
            }
          }
        }

        return TRUE;
      });
    }

    return $output;
  }

  /**
   * Returns training content data by each step.
   *
   * @param int $gid
   *   Group ID.
   *
   * @return array
   *   Training content data by each step.
   */
  protected function getTrainingContentStatistics($gid) {
    $query = $this->database->select('opigno_learning_path_achievements', 'a');
    $query->leftJoin('opigno_learning_path_step_achievements', 's', 's.gid = a.gid AND s.uid = a.uid');
    $query->leftJoin('opigno_learning_path_step_achievements', 'sc', 'sc.id = s.id AND sc.completed IS NOT NULL');
    $query->addExpression('COUNT(sc.uid)', 'completed');
    $query->addExpression('AVG(s.score)', 'score');
    $query->addExpression('AVG(s.time)', 'time');
    $query->addExpression('MAX(s.entity_id)', 'entity_id');
    $query->addExpression('MAX(s.parent_id)', 'parent_id');
    $query->addExpression('MAX(s.position)', 'position');
    $query->addExpression('MAX(s.typology)', 'typology');
    $query->addExpression('MAX(s.id)', 'id');
    $query->condition('a.uid', 0, '<>');

    // Add only group members.
    $group = Group::load($gid);
    $members = $group->getMembers();
    foreach ($members as $member) {
      $user = $member->getUser();
      if ($user) {
        $members_ids[$user->id()] = $member->getUser()->id();
      }
    }
    if (empty($members_ids)) {
      $members_ids[] = 0;
    }

    $query->condition('a.uid', $members_ids, 'IN');

    $data = $query->fields('s', ['name'])
      ->condition('a.gid', $gid)
      ->groupBy('s.name')
      ->orderBy('position')
      ->orderBy('parent_id')
      ->execute()
      ->fetchAll();

    // Sort courses and modules.
    $rows = [];
    foreach ($data as $row) {
      if ($row->typology == 'Course') {
        $rows[] = $row;
      }
      elseif (($row->typology == 'Module' && $row->parent_id == 0)
        || $row->typology == 'ILT' || $row->typology == 'Meeting') {
        $rows[] = $row;
      }
    }

    return $rows;
  }

  /**
   * Returns group content average statistics for certificated training.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   * @param mixed $users
   *   Users IDs array.
   * @param \Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent $content
   *   Group content object.
   *
   * @return array
   *   Group content average statistics for certificated training.
   *
   * @throws \Exception
   */
  protected function getGroupContentStatisticsCertificated(Group $group, $users, OpignoGroupManagedContent $content) {
    $gid = $group->id();
    $entity_type_manager = \Drupal::entityTypeManager();

    $name = '';
    $completed = 0;
    $score = 0;
    $time = 0;

    foreach ($users as $user) {
      $uid = $user->uid;

      $id = $content->getEntityId();
      $type = $content->getGroupContentTypeId();
      $latest_cert_date = LPStatus::getTrainingStartDate($group, $uid);

      switch ($type) {
        case 'ContentTypeModule':
          if ($opigno_module = OpignoModule::load($id)) {
            $name = $opigno_module->getName();
            $step_info = opigno_learning_path_get_module_step($gid, $uid, $opigno_module, $latest_cert_date, FALSE);
            $step_score = $opigno_module->getKeepResultsOption() == 'newest' ? $step_info["current attempt score"] : $step_info["best score"];
          }
          break;

        case 'ContentTypeCourse':
          if ($course = Group::load($id)) {
            $name = $course->label();
            $step_info = opigno_learning_path_get_course_step($gid, $uid, $course, $latest_cert_date);
            $step_score = $step_info["best score"];
          }
          break;

        case 'ContentTypeMeeting':
          if ($meeting = $entity_type_manager->getStorage('opigno_moxtra_meeting')->load($id)) {
            $step_info = opigno_learning_path_get_meeting_step($gid, $uid, $meeting);
            $step_score = $step_info["best score"];
          }
          break;

        case 'ContentTypeILT':
          if ($ilt = $entity_type_manager->getStorage('opigno_ilt')->load($id)) {
            $name = $ilt->label();
            $step_info = opigno_learning_path_get_ilt_step($gid, $uid, $ilt);
            $step_score = $step_info["best score"];
          }
          break;
      }

      if (!empty($step_info["completed on"])) {
        $completed++;
      }

      if (!empty($step_score)) {
        $score = $score + $step_score;
      }

      if (!empty($step_info["time spent"])) {
        $time = $time + $step_info["time spent"];
      }
    }

    return [
      'name' => $name,
      'completed' => $completed,
      'score' => $score,
      'time' => $time,
    ];
  }

  /**
   * Create the year select with AJAX callback.
   *
   * @param array $data
   *   Rows data to get available years.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Render array to add the year select to the form.
   */
  protected function createYearSelect(array $data, FormStateInterface $form_state): array {
    $years = [0 => $this->t("All Years")];
    foreach ($data as $row) {
      $year = $row->year;
      if (!isset($years[$year])) {
        $years[$year] = $year;
      }
    }

    $year_selected = $form_state->getValue('year', 0);

    return [
      '#type' => 'select',
      '#title' => $this->t('Year'),
      '#title_display' => 'invisible',
      '#options' => $years,
      '#default_value' => $year_selected,
      '#ajax' => [
        'event' => 'change',
        'callback' => '::updateTrainingProgressAjax',
      ],
      '#attributes' => [
        'class' => ['selectpicker'],
      ],
    ];
  }

  /**
   * Create the month select with AJAX callback.
   *
   * @param array $data
   *   Rows data to get available months.
   * @param int $year_selected
   *   The previously selected year.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Render array to add the months select to the form.
   */
  protected function createMonthSelect(array $data, int $year_selected, FormStateInterface $form_state): array {
    $months = [0 => $this->t('All Months')];
    if (!empty($year_selected)) {
      foreach ($data as $row) {
        $month = $row->month;
        if (!isset($months[$month]) && $row->year == $year_selected) {
          $timestamp = mktime(0, 0, 0, $month, 1);
          $months[$month] = $this->dateFormatter->format($timestamp, 'custom', 'F');
        }
      }
    }

    $month = !empty($year_selected) ? $form_state->getValue('month', 0) : 0;

    return [
      '#type' => 'select',
      '#title' => $this->t('Month'),
      '#title_display' => 'invisible',
      '#options' => $months,
      '#default_value' => $month,
      '#ajax' => [
        'event' => 'change',
        'callback' => '::updateTrainingProgressAjax',
      ],
      '#attributes' => [
        'class' => ['selectpicker'],
      ],
    ];
  }

  /**
   * Ajax form submit callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The updated progress element.
   */
  public function updateTrainingProgressAjax(array &$form, FormStateInterface &$form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    // Select the 1st available month when the year is changed.
    if (isset($trigger['#name']) && $trigger['#name'] === 'year') {
      $months = $form['trainings_progress']['month']['#options'];
      $form['trainings_progress']['month']['#value'] = array_key_first($months);
    }

    // Update the training progress and inintialize the bootstrap selects.
    $id = $form['trainings_progress']['#attributes']['id'];
    $response = new AjaxResponse();
    $response->addCommand(new InvokeCommand('body', 'removeClass', ['charts-rendered']));
    $response->addCommand(new ReplaceCommand("#$id", $form['trainings_progress']));

    return $response;
  }

  /**
   * Get training progress.
   *
   * @param array $period
   *   Period of time.
   * @param array $lp_ids
   *   Learning path IDs.
   * @param mixed $members_ids
   *   User IDs that needed to get statistics.
   * @param mixed $expired_uids
   *   Users uids with the training expired certification.
   *
   * @return array
   *   Progress data.
   *
   * @throws \Exception
   */
  protected function getTrainingStistics(array $period, array $lp_ids = [], $members_ids = [], $expired_uids = []): array {
    $statistics = [
      'progress' => 0,
      'completion' => 0,
    ];

    // Get the number of users with expired certificates.
    $expired_users_number = count($expired_uids);

    // Build the main query.
    $query = $this->database->select('opigno_learning_path_achievements', 'a')
      ->groupBy('a.name');
    if (!empty($lp_ids)) {
      $query->condition('a.gid', $lp_ids, 'IN');
      $query->leftJoin('group_content_field_data', 'g_c_f_d', 'a.uid = g_c_f_d.entity_id AND g_c_f_d.gid = a.gid');
      $query->condition('g_c_f_d.type', 'learning_path-group_membership');
    }
    $query->condition('a.uid', 0, '<>');
    if (!empty($expired_uids)) {
      // Exclude users with the training expired certification.
      $query->condition('a.uid', $expired_uids, 'NOT IN');
    }
    if (!empty($members_ids)) {
      $query->condition('a.uid', $members_ids, 'IN');
    }

    // The query for the progress statistic.
    $progress_queue = clone($query);
    // The query for the completion statistic.
    $completion_queue = clone($query);

    // Get total statistic.
    $query->addExpression('COUNT(a.registered)', 'total');
    $total = $query->execute()->fetchAll();
    $total_count_achievements = 0;
    if (!empty($total)) {
      foreach ($total as $row) {
        $total_count_achievements += $row->total;
      }
    }

    // Stop calculation if no any achievements.
    if (empty($total_count_achievements)) {
      return $statistics;
    }

    $data = [];
    // Get process statistic.
    $progress_queue->addExpression('SUM(a.progress) / (COUNT(a.progress) + :expired_users_number) / 100', 'progress', [
      ':expired_users_number' => $expired_users_number,
    ]);
    $progress_queue->condition('a.registered', $period, 'BETWEEN');
    $data['progress'] = $progress_queue
      ->execute()
      ->fetchAll();

    // Get completion statistic.
    $completion_queue->addExpression('COUNT(a.completed) / (:total + :expired_users_number)', 'completion', [
      ':expired_users_number' => $expired_users_number,
      ':total' => $total_count_achievements,
    ]);
    $completion_queue->isNotNull('a.completed');
    $completion_queue->condition('a.completed', $period, 'BETWEEN');
    $data['completion'] = $completion_queue
      ->execute()
      ->fetchAll();

    // Calculate the results.
    foreach ($data as $key => $items) {
      $count = count($items);
      if ($count === 0) {
        continue;
      }
      foreach ($items as $row) {
        $statistics[$key] += $row->{$key};
      }
      $statistics[$key] /= $count;
    }

    return [
      'progress' => $statistics['progress'] ?? 0,
      'completion' => $statistics['completion'] ?? 0,
    ];
  }

  /**
   * Build min/max selected date time.
   *
   * @param int $year
   *   The needed year.
   * @param int $month
   *   The needed month.
   *
   * @return array
   *   Min/max date of the period.
   */
  public function timePeriod(int $year, int $month): array {
    // All possible time.
    if (empty($year)) {
      return [
        'min' => $this->prepareDate(0),
        'max' => $this->prepareDate(time()),
      ];
    }
    // The first and last day of the year.
    if (empty($month)) {
      return [
        'min' => $this->prepareDate(mktime(0, 0, 0, 1, 1, $year)),
        'max' => $this->prepareDate(mktime(0, 0, 0, 1, 1, $year + 1)),
      ];
    }
    // The first and last day of the month.
    return [
      'min' => $this->prepareDate(mktime(0, 0, 0, $month, 1, $year)),
      'max' => $this->prepareDate(mktime(0, 0, 0, $month + 1, 1, $year)),
    ];
  }

  /**
   * Convert timestamp to date.
   *
   * @param int $timestamp
   *   Unix timestamp.
   *
   * @return string
   *   Date 'Y-m-d H:i:s'
   */
  protected function prepareDate(int $timestamp): string {
    $datetime = DrupalDateTime::createFromTimestamp($timestamp);
    return $datetime->format(DrupalDateTime::FORMAT);
  }

}
