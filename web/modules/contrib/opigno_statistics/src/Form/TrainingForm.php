<?php

namespace Drupal\opigno_statistics\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_statistics\StatisticsPageTrait;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\group\Entity\Group;

/**
 * Implements the training statistics page.
 */
class TrainingForm extends FormBase {

  use StatisticsPageTrait;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Time.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The user storage exception.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $userStorage = NULL;

  /**
   * TrainingForm constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    Connection $database,
    TimeInterface $time,
    DateFormatterInterface $date_formatter,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->database = $database;
    $this->time = $time;
    $this->dateFormatter = $date_formatter;
    try {
      $this->userStorage = $entity_type_manager->getStorage('user');
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_statistics_exception', $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_statistics_training_form';
  }

  /**
   * Builds training progress.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param array $period
   *   Min/Max period of time.
   * @param array $expired_uids
   *   Users uids with the training expired certification.
   *
   * @return array
   *   Render array.
   *
   * @throws \Exception
   */
  protected function buildTrainingsProgress(GroupInterface $group, array $period, array $expired_uids = []): array {
    $members_ids = [];
    $group_bundle = $group->bundle();
    $lp_ids = [];

    if ($group_bundle === 'learning_path') {
      $lp_ids = [$group->id()];
    }
    elseif ($group_bundle === 'opigno_class') {
      // Get the list of the training related to the class.
      $query_class = $this->database->select('group_content_field_data', 'g_c_f_d')
        ->fields('g_c_f_d', ['gid'])
        ->condition('entity_id', $group->id())
        ->condition('type', 'group_content_type_27efa0097d858')
        ->execute()
        ->fetchAll();

      foreach ($query_class as $result_ids) {
        $lp_ids[] = $result_ids->gid;
      }
      if (empty($lp_ids)) {
        $lp_ids = [0];
      }

      // Get class members.
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
    }

    $data = $this->getTrainingStistics($period, $lp_ids, $members_ids, $expired_uids);

    return [
      'drupalSettings' => [
        'opignoCharts' => [
          'trainingProgress' => $this->buildDonutChart((float) $data['progress'], '#training-progress-chart'),
          'trainingCompletion' => $this->buildDonutChart((float) $data['completion'], '#training-completion-chart'),
        ],
      ],
    ];
  }

  /**
   * Builds one block for the user metrics.
   *
   * @param string $label
   *   Label.
   * @param string $value
   *   Value.
   * @param string $help_text
   *   Help text.
   *
   * @return array
   *   Render array.
   */
  protected function buildUserMetric($label, $value, $help_text = NULL) {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['user-metric'],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['user-metric-value'],
        ],
        '#value' => $value,
        ($help_text) ? [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => ['popover-help'],
            'data-toggle' => 'popover',
            'data-content' => $help_text,
          ],
          '#value' => '?',
        ] : NULL,
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['user-metric-label'],
        ],
        '#value' => $label,
      ],
    ];
  }

  /**
   * Builds user metrics.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   *
   * @return array
   *   Render array.
   */
  protected function buildUserMetrics(GroupInterface $group) {
    if ($group->bundle() == 'opigno_class') {
      $condition = 'AND gc.type IN (\'opigno_class-group_membership\')';
    }
    else {
      $condition = 'AND gc.type IN (\'learning_path-group_membership\', \'opigno_course-group_membership\')';
    }

    $query = $this->database->select('users', 'u');
    $query->innerJoin(
      'group_content_field_data',
      'gc',
      "gc.entity_id = u.uid
" . $condition . "
AND gc.gid = :gid",
      [
        ':gid' => $group->id(),
      ]
    );
    $users = $query
      ->condition('u.uid', 0, '<>')
      ->countQuery()
      ->execute()
      ->fetchField();

    $now = $this->time->getRequestTime();
    // Last 7 days.
    $period = 60 * 60 * 24 * 7;

    $query = $this->database->select('users_field_data', 'u');
    $query->innerJoin(
      'group_content_field_data',
      'gc',
      "gc.entity_id = u.uid
" . $condition . "
AND gc.gid = :gid",
      [
        ':gid' => $group->id(),
      ]
    );
    $new_users = $query
      ->condition('u.uid', 0, '<>')
      ->condition('u.created', $now - $period, '>')
      ->countQuery()
      ->execute()
      ->fetchField();

    $query = $this->database->select('users_field_data', 'u');
    $query->innerJoin(
      'group_content_field_data',
      'gc',
      "gc.entity_id = u.uid
" . $condition . "
AND gc.gid = :gid",
      [
        ':gid' => $group->id(),
      ]
    );
    $active_users = $query
      ->condition('u.uid', 0, '<>')
      ->condition('u.login', $now - $period, '>')
      ->countQuery()
      ->execute()
      ->fetchField();

    return [
      '#theme' => 'opigno_statistics_user_metrics',
      'users' => $this->buildUserMetric($this->t('Users'), $users),
      'new_users' => $this->buildUserMetric($this->t('New users'), $new_users),
      'active_users' => $this->buildUserMetric($this->t('Recently active users'), $active_users),
    ];
  }

  /**
   * Builds training content.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param mixed $expired_uids
   *   Users uids with the training expired certification.
   *
   * @return array
   *   Render array.
   *
   * @throws \Exception
   */
  protected function buildTrainingContent(GroupInterface $group, $expired_uids = NULL) {
    $gid = $group->id();

    $query = $this->database->select('users', 'u');
    $query->innerJoin(
      'group_content_field_data',
      'gc',
      "gc.entity_id = u.uid
AND gc.type IN ('learning_path-group_membership', 'opigno_course-group_membership')
AND gc.gid = :gid",
      [
        ':gid' => $gid,
      ]
    );
    $query->fields('u', ['uid']);
    $users = $query
      ->condition('u.uid', 0, '<>')
      ->execute()
      ->fetchAll();

    $users_number = count($users);

    // Filter users with expired certificate.
    $users = array_filter($users, function ($user) use ($expired_uids) {
      return !in_array($user->uid, $expired_uids);
    });

    $table = [
      '#type' => 'table',
      '#attributes' => [
        'class' => [
          'statistics-table',
          'training-content-list',
          'table-striped',
        ],
      ],
      '#prefix' => '<div class="training-content-wrapper">',
      '#suffix' => '</div>',
      '#header' => [
        ['data' => $this->t('Step'), 'class' => 'step'],
        ['data' => $this->t('% Completed'), 'class' => 'completed'],
        ['data' => $this->t('Avg score'), 'class' => 'score'],
        ['data' => $this->t('Avg time spent'), 'class' => 'time'],
      ],
      '#rows' => [],
    ];

    $rows = [];
    if (LPStatus::isCertificateExpireSet($group)) {
      // Calculate training content in condition of certification.
      $contents = OpignoGroupManagedContent::loadByGroupId($gid);
      if ($users && $contents) {
        foreach ($contents as $content) {
          $content_id = $content->id();
          $rows[$content_id] = new \stdClass();

          $rows[$content_id]->name = '';
          $rows[$content_id]->completed = 0;
          $rows[$content_id]->score = 0;
          $rows[$content_id]->time = 0;

          $statistics = $this->getGroupContentStatisticsCertificated($group, $users, $content);

          if (!empty($statistics['name'])) {
            // Step name.
            $rows[$content_id]->name = $statistics['name'];
            // Step average completed.
            $rows[$content_id]->completed = $statistics['completed'];
            // Step average score.
            $rows[$content_id]->score = $statistics['score'] / $users_number;
            // Step average score.
            $rows[$content_id]->time = round($statistics['time'] / $users_number);
          }
        }
      }
    }
    else {
      // Calculate training content without certification condition.
      $rows = $this->getTrainingContentStatistics($gid);
    }

    if ($rows) {
      foreach ($rows as $row) {
        if (!empty($row->name)) {
          $completed = round(100 * $row->completed / $users_number);
          $score = round($row->score);
          $time_spent = (int) $row->time;
          $time = $time_spent > 0
            ? $this->dateFormatter->formatInterval($time_spent)
            : '-';

          $table['#rows'][] = [
            ['data' => $row->name, 'class' => 'step'],
            ['data' => $completed . '%', 'class' => 'completed'],
            ['data' => $score . '%', 'class' => 'score'],
            ['data' => $time, 'class' => 'time'],
          ];
        }
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['training-content', 'content-box'],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => [
          'class' => ['training-content-title'],
        ],
        '#value' => $this->t('Training Content'),
      ],
      'list' => $table,
    ];
  }

  /**
   * Builds users results for Learning paths.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   *
   * @return array
   *   Render array.
   */
  protected function buildUsersResultsLp(GroupInterface $group) {
    $gid = $group->id();

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['users-results', 'content-box'],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => [
          'class' => ['users-results-title'],
        ],
        '#value' => $this->t('Users results'),
      ],
      'list' => Views::getView('training_user_results')->executeDisplay('block', [$gid]),
    ];
  }

  /**
   * Builds users results for Classes.
   */
  protected function buildUsersResultsClass(GroupInterface $group, $lp_id = NULL) {
    if (!$lp_id) {
      return;
    }

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

    $membership_types = [
      'learning_path-group_membership',
      'opigno_course-group_membership',
    ];
    $query = $this->database->select('users_field_data', 'u')
      ->fields('u', ['uid', 'name']);
    $query->condition('u.uid', $members_ids, 'IN');
    $query->condition('u.uid', 0, '<>');
    $query->innerJoin('group_content_field_data', 'g_c', 'g_c.entity_id = u.uid');
    $query->condition('g_c.type', $membership_types, 'IN');
    $query->condition('g_c.gid', $lp_id);
    $query->leftJoin('opigno_learning_path_achievements', 'a', 'a.gid = g_c.gid AND a.uid = u.uid');
    $query->fields('a', ['status', 'score', 'time', 'gid']);
    $query->orderBy('u.name', 'ASC');
    $statistic = $query->execute()->fetchAll();

    $table = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['statistics-table', 'users-results-list', 'table-striped'],
      ],
      '#prefix' => '<div class="user-results-class-wrapper">',
      '#suffix' => '</div>',
      '#header' => [
        ['data' => $this->t('User'), 'class' => 'user'],
        ['data' => $this->t('Score'), 'class' => 'score'],
        ['data' => $this->t('Passed'), 'class' => 'passed'],
        ['data' => $this->t('Time spent'), 'class' => 'time'],
        ['data' => $this->t('Details'), 'class' => 'hidden'],
      ],
      '#rows' => [],
    ];

    $options = [
      'attributes' => [
        'class' => ['btn', 'btn-rounded', 'details'],
      ],
    ];

    foreach ($statistic as $row) {
      $score = $row->score ?? 0;
      $score = [
        'data' => $this->buildScore($score),
      ];

      $status = $row->status ?? 'pending';
      $status = [
        'data' => $this->buildStatus($status),
      ];

      $time_spent = (int) $row->time;
      $time = $time_spent > 0
        ? $this->dateFormatter->formatInterval($time_spent)
        : '-';
      $details_link = Link::createFromRoute($this->t('Details'), 'opigno_statistics.user', ['user' => $row->uid], $options)->toRenderable();

      $table['#rows'][] = [
        ['data' => $row->name, 'class' => 'user'],
        ['data' => $score, 'class' => 'score'],
        ['data' => $status, 'class' => 'passed'],
        ['data' => $time, 'class' => 'time'],
        ['data' => $details_link, 'class' => 'details'],
      ];
    }

    // Hide links on detail user pages if user don't have permissions.
    $account = $this->currentUser();
    if (!$account->hasPermission('view module results')) {
      unset($table['#header'][4]);
      foreach ($table['#rows'] as $key => $table_row) {
        unset($table['#rows'][$key][4]);
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['users-results', 'content-box'],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => [
          'class' => ['users-results-title'],
        ],
        '#value' => Group::load($lp_id)->label(),
      ],
      'list' => $table,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $group = NULL) {
    $form['#title'] = $this->t('Training statistics - @training', [
      '@training' => $group->label(),
    ]);

    if ($group->bundle() == 'opigno_class') {
      $query_class = $this->database->select('group_content_field_data', 'g_c_f_d')
        ->fields('g_c_f_d', ['gid'])
        ->condition('entity_id', $group->id())
        ->condition('type', 'group_content_type_27efa0097d858')
        ->execute()
        ->fetchAll();

      $lp_ids = [];
      foreach ($query_class as $result_ids) {
        $lp_ids[] = $result_ids->gid;
      }
    }
    else {
      $lp_ids[] = $group->id();
    }

    if (empty($lp_ids)) {
      $lp_ids[] = 0;
    }

    // Prepare the list of available years.
    $query = $this->database->select('opigno_learning_path_achievements', 'a');
    $query->addExpression('YEAR(a.registered)', 'year');
    $query->condition('a.gid', $lp_ids, 'IN');
    $data = $query->groupBy('year')
      ->orderBy('year', 'DESC')
      ->execute()
      ->fetchAll();

    $year_select = $this->createYearSelect($data, $form_state);
    $year_selected = (int) $year_select['#default_value'];

    // Prepare the list of months.
    $query = $this->database->select('opigno_learning_path_achievements', 'a');
    $query->addExpression('MONTH(a.registered)', 'month');
    $query->addExpression('YEAR(a.registered)', 'year');
    $query->condition('a.gid', $lp_ids, 'IN');
    $data = $query->groupBy('month')
      ->groupBy('year')
      ->orderBy('month')
      ->execute()
      ->fetchAll();

    $month_select = $this->createMonthSelect($data, $year_selected, $form_state);
    $month_selected = (int) $month_select['#default_value'];

    $period = $this->timePeriod($year_selected, $month_selected);

    // Get users with the training expired certification.
    $expired_uids = $this->getExpiredUsers($group);

    $form['trainings_progress'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'statistics-training-progress',
      ],
      // H2 Need for correct structure.
      [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Training statistics'),
        '#attributes' => [
          'class' => ['sr-only'],
        ],
      ],
      'year' => $year_select,
      'month' => $month_select,
      '#attached' => $this->buildTrainingsProgress($group, $period, $expired_uids),
    ];

    if ($group->bundle() === 'opigno_class') {
      $form['trainings_progress']['user_metrics'] = [
        '#type' => 'container',
        'users' => $this->buildUserMetrics($group),
      ];

      foreach ($lp_ids as $lp_id) {
        $form[] = [
          'training_class_results_' . $lp_id => $this->buildUsersResultsClass($group, $lp_id),
        ];
      }
    }
    else {
      $form['trainings_progress']['user_metrics'] = [
        '#type' => 'container',
        'users' => $this->buildUserMetrics($group),
      ];

      $form['training_content'] = $this->buildTrainingContent($group, $expired_uids);
      $form['user_results'] = $this->buildUsersResultsLp($group);
    }

    $form['#attached'] = [
      'library' => ['opigno_statistics/opigno_charts'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The form is submitted with AJAX.
  }

}
