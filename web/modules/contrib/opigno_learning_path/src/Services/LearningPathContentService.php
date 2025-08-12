<?php

namespace Drupal\opigno_learning_path\Services;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\GeneratedLink;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_group_manager\Controller\OpignoGroupManagerController;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_group_manager\OpignoGroupContentTypesManager;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_learning_path\LearningPathAccess;
use Drupal\opigno_learning_path\LPStatusInterface;
use Drupal\opigno_learning_path\Traits\LearningPathAchievementTrait;
use Drupal\opigno_module\Entity\OpignoModule;
use Drupal\opigno_module\Entity\OpignoModuleInterface;
use Drupal\opigno_module\Entity\UserModuleStatusInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Defines the LP content service.
 *
 * @package Drupal\opigno_learning_path\Services
 */
class LearningPathContentService {

  use StringTranslationTrait;
  use LearningPathAchievementTrait;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The Opigno group content types manager service.
   *
   * @var \Drupal\opigno_group_manager\OpignoGroupContentTypesManager
   */
  protected OpignoGroupContentTypesManager $contentTypesManager;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The block manager service.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected BlockManagerInterface $blockManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Defines if the step actions should be displayed or not.
   *
   * @var bool
   */
  protected bool $displayStepActions = FALSE;

  /**
   * LearningPathContentService constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\opigno_group_manager\OpignoGroupContentTypesManager $content_types_manager
   *   The Opigno group content types manager service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    AccountInterface $account,
    LanguageManagerInterface $language_manager,
    OpignoGroupContentTypesManager $content_types_manager,
    RouteMatchInterface $route_match,
    BlockManagerInterface $block_manager,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->currentUser = $account;
    $this->languageManager = $language_manager;
    $this->contentTypesManager = $content_types_manager;
    $this->routeMatch = $route_match;
    $this->blockManager = $block_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Returns step score cell.
   *
   * @param array $step
   *   Step data array.
   *
   * @return string
   *   Score number.
   */
  protected function buildStepScoreLabel(array $step): string {
    if (in_array($step['typology'], ['Module', 'Course', 'Meeting', 'ILT'])) {
      $display_current = $step['is_last_attempt_not_finished'] ?? FALSE;
      $score = $display_current ? $step['current attempt score'] : $step['best score'];

      return $score . '%';
    }

    return '0%';
  }

  /**
   * Returns step state cell.
   *
   * @param array $step
   *   The LP step.
   *
   * @return array
   *   The step state label cell.
   */
  protected function buildStepStateLabel(array $step): array {
    $uid = $this->currentUser->id();

    assert($this->routeMatch->getParameter('group'));
    $status = opigno_learning_path_get_step_status($step, $uid, TRUE);
    return [
      'class' => $status,
    ];
  }

  /**
   * Get training content info.
   *
   * @return array
   *   Training content data array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function trainingContent(): array {
    $build = [
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
      '#theme' => 'opigno_learning_path_training_content',
    ];
    $group = $this->routeMatch->getParameter('group');
    if (!($group instanceof GroupInterface)) {
      // On of  case an anonymous user hasn't an access to the group.
      return $build;
    }
    $user = $this->currentUser;

    // Get training certificate expiration flag.
    $latest_cert_date = LPStatus::getTrainingStartDate($group, $user->id());

    // If not a member.
    if (!$group->getMember($user)
      || (!$user->isAuthenticated() && $group->get('field_learning_path_visibility')->value === 'semiprivate')) {
      return $build;
    }

    // Check if membership has status 'pending'.
    if (!LearningPathAccess::statusGroupValidation($group, $user)) {
      return $build;
    }

    $steps = $this->trainingContentSteps($build, $group, $user, $latest_cert_date);
    $this->trainingContentMain($build, $steps);
    $this->trainingContentDocuments($build, $group);
    $this->trainingContentForum($build, $group, $user);
    return $build;
  }

  /**
   * Get training content info.
   *
   * @param array $content
   *   Build data array.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group object instance.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   Account object instance.
   * @param null|string $latest_cert_date
   *   Latest certificate date.
   *
   * @return array
   *   Steps with group data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function trainingContentSteps(array &$content, GroupInterface $group, AccountInterface $user, $latest_cert_date): array {
    // Get training guided navigation option.
    $freeNavigation = !OpignoGroupManagerController::getGuidedNavigation($group);
    $uid = (int) $user->id();
    $gid = (int) $group->id();

    if ($freeNavigation) {
      // Get all steps for LP.
      $steps = opigno_learning_path_get_all_steps($gid, $uid, NULL, $latest_cert_date);
    }
    else {
      // Get guided steps.
      $steps = opigno_learning_path_get_steps($gid, $uid, NULL, $latest_cert_date);
    }

    $steps = array_filter($steps, static function ($step) use ($user) {
      return static::filterStep($step, $user);
    });
    $steps = array_values($steps);

    $steps_array = [];
    if ($steps) {
      $lp_attempt = OpignoModule::getLastTrainingAttempt($uid, $gid, TRUE);
      if ($lp_attempt) {
        $this->buildTrainingDateParams($content, $lp_attempt, $group);
      }

      foreach ($steps as $key => $step) {
        $start_date = $end_date = NULL;
        $sub_title = '';
        $link = NULL;
        $free_link = NULL;
        $score = $this->buildStepScoreLabel($step);
        $state = $this->buildStepStateLabel($step);

        if ($step['typology'] === 'Course') {
          if ($freeNavigation) {
            // Get all steps for LP.
            $course_steps = opigno_learning_path_get_all_steps($step['id'], $uid, NULL, $latest_cert_date);
          }
          else {
            // Get guided steps.
            $course_steps = opigno_learning_path_get_steps($step['id'], $uid, NULL, $latest_cert_date);
          }

          foreach ($course_steps as $course_step_key => &$course_step) {
            if ($course_step_key == 0) {
              // Load first step entity.
              $link = $this->getFirstStepLink($lp_attempt, $course_step, $course_step['name'], $user, $group, $latest_cert_date);
            }
            else {
              // Get link to module.
              $course_parent_content_id = $course_steps[$course_step_key - 1]['cid'];
              $link = $this->getStepLink($lp_attempt, $course_step, $course_step['name'], $user, $group, $course_parent_content_id, $latest_cert_date);
            }

            // Add compiled parameters to step array.
            $course_step['title'] = !empty($link) ? $link : $course_step['name'];
            $typology = $course_step['typology'] ?? '';

            $course_step['summary_details_table'] = [
              '#theme' => 'opigno_learning_path_training_content_step_summary_details_table',
              '#mandatory' => $step["mandatory"],
              '#type' => $typology,
              '#steps' => $course_step['title'],
              '#status' => $this->buildStepStateLabel($course_step),
              '#progress' => $this->buildStepScoreLabel($course_step),
              '#action' => $this->getStepAction($typology, $course_step['id'], $group),
            ];
          }

          $course_steps_array = array_map(function ($value) use ($group) {
            $value['colspan'] = $this->displayStepActions ? 7 : 6;
            return [
              '#theme' => 'opigno_learning_path_training_content_step',
              'step' => $value,
              '#group' => $group,
            ];
          }, $course_steps);
          $step['course_steps'] = $course_steps_array;
          $steps[$key]['course_steps'] = $course_steps_array;
        }
        elseif ($step['typology'] === 'Module') {
          $module = OpignoModule::load($step['id']);
          $langcode = $this->languageManager->getCurrentLanguage()->getId();
          $step['module'] = $module->hasTranslation($langcode) ? $module->getTranslation($langcode) : $module;
          $step['name'] = $step['module']->label();
        }

        $title = $step['name'];

        if ($step['typology'] === 'Meeting') {
          /** @var \Drupal\opigno_moxtra\MeetingInterface $meeting */
          $meeting = $this->entityTypeManager
            ->getStorage('opigno_moxtra_meeting')
            ->load($step['id']);
          $start_date = $meeting->getStartDate();
          $end_date = $meeting->getEndDate();
          if ($freeNavigation) {
            $free_link = Link::createFromRoute($title, 'opigno_moxtra.meeting', [
              'opigno_moxtra_meeting' => $step['id'],
            ])
              ->toString();
          }
        }
        elseif ($step['typology'] === 'ILT') {
          /** @var \Drupal\opigno_ilt\ILTInterface $ilt */
          $ilt = $this->entityTypeManager
            ->getStorage('opigno_ilt')
            ->load($step['id']);
          $start_date = $ilt->getStartDate();
          $end_date = $ilt->getEndDate();
          if ($freeNavigation) {
            $free_link = Link::createFromRoute($title, 'entity.opigno_ilt.canonical', [
              'opigno_ilt' => $step['id'],
            ])
              ->toString();
          }
        }

        if (isset($start_date) && isset($end_date)) {
          $start_date = DrupalDateTime::createFromFormat(
            DrupalDateTime::FORMAT,
            $start_date
          );
          $end_date = DrupalDateTime::createFromFormat(
            DrupalDateTime::FORMAT,
            $end_date
          );
          $end_date_format = $end_date->format('g:i A');
          if ($start_date->format('jS F Y') != $end_date->format('jS F Y')) {
            $end_date_format = $end_date->format('jS F Y - g:i A');
          }
          $title .= ' / ' . $this->t('@start to @end', [
            '@start' => $start_date->format('jS F Y - g:i A'),
            '@end' => $end_date_format,
          ]);
        }

        $keys = array_keys($steps);

        // Build link for first step.
        if ($key == $keys[0]) {
          if ($step['typology'] == 'Course') {
            $link = NULL;
          }
          else {
            // Load first step entity.
            $link = $this->getFirstStepLink($lp_attempt, $step, $title, $user, $group, $latest_cert_date);
          }
        }
        else {
          if ($step['typology'] == 'Course') {
            $link = NULL;
          }
          else {
            // Get link to module.
            if (!empty($free_link)) {
              $link = $free_link;
            }
            elseif (!empty($steps[$key - 1]['cid'])) {
              // Get previous step cid.
              if ($steps[$key - 1]['typology'] == 'Course') {
                // If previous step is course get it's last step.
                if (!empty($steps[$key - 1]['course_steps'])) {
                  $course_last_step = end($steps[$key - 1]['course_steps']);
                  if (!empty($course_last_step['step']['cid'])) {
                    $parent_content_id = $course_last_step['step']['cid'];
                  }
                }
              }
              else {
                // If previous step isn't a course.
                $parent_content_id = $steps[$key - 1]['cid'];
              }

              if (!empty($parent_content_id)) {
                $link = $this->getStepLink($lp_attempt, $step, $title, $user, $group, $parent_content_id, $latest_cert_date);
              }
            }
          }
        }

        // Add compiled parameters to step array.
        $step['title'] = !empty($link) ? $link : $title;
        $step['sub_title'] = $sub_title;
        $step['score'] = $score;
        $step['state'] = $state;
        $typology = $step['typology'] ?? '';

        $step['summary_details_table'] = [
          '#theme' => 'opigno_learning_path_training_content_step_summary_details_table',
          '#mandatory' => $step["mandatory"],
          '#type' => $typology,
          '#steps' => $step['title'],
          '#substeps' => (bool) ($step['course_steps'] ?? FALSE),
          '#status' => $state,
          '#progress' => $score,
          '#action' => $this->getStepAction($typology, $step['id'], $group),
          '#display_action' => $this->displayStepActions,
        ];

        $step['colspan'] = $this->displayStepActions ? 7 : 6;
        $steps_array[] = [
          '#theme' => 'opigno_learning_path_training_content_step',
          'step' => $step,
          '#group' => $group,
        ];
      }

      if ($steps_array) {
        $steps = $steps_array;
      }
    }
    return $steps;
  }

  /**
   * Add a timestamps to main data array.
   *
   * @param array $build
   *   Main build array.
   * @param \Drupal\opigno_learning_path\LPStatusInterface $lp_attempt
   *   Attempt object instance.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group object instance.
   */
  protected function buildTrainingDateParams(array &$build, LPStatusInterface $lp_attempt, GroupInterface $group): void {
    $build['started'] = $lp_attempt->getStarted();
    $build['finished'] = $lp_attempt->getFinished();

    $field_value = $group->get('field_certificate_expire_results')->getString();
    $field_definition = $group->getFieldDefinition('field_certificate_expire_results');
    $allowed_values = $field_definition->getSetting('allowed_values');
    if (array_key_exists($field_value, $allowed_values)) {
      $value = $allowed_values[$field_value];
      $build['valid'] = strtotime($value, $build['finished']);
    }
  }

  /**
   * Gets the LP step link (except for the 1st step).
   *
   * @param \Drupal\opigno_learning_path\LPStatusInterface|null $lp_attempt
   *   The last LP attempt entity.
   * @param array $step
   *   The current step data array.
   * @param string $title
   *   The LP step title.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account to generate the step link for.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The LP group.
   * @param string|int|null $parent_content_id
   *   The step parent content ID.
   * @param int|string|null $last_cert_date
   *   The latest certification date.
   *
   * @return \Drupal\Core\GeneratedLink|string
   *   The generated link.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getStepLink(
    ?LPStatusInterface $lp_attempt,
    array $step,
    string $title,
    AccountInterface $user,
    GroupInterface $group,
    $parent_content_id,
    $last_cert_date
  ) {
    $module = $this->getModuleFromStep($step);
    $gid = (int) $group->id();
    $status = opigno_learning_path_get_step_status($step, $user->id());
    if (!$lp_attempt instanceof LPStatusInterface ||
      $lp_attempt instanceof LPStatusInterface && $status === 'pending') {
      // The link to the next step if the LP attempt isn't finished yet.
      $link = Link::createFromRoute($title, 'opigno_learning_path.steps.next', [
        'group' => $gid,
        'parent_content' => $parent_content_id,
      ])->toString();
    }
    elseif ($module instanceof OpignoModuleInterface) {
      // The link to the result page if the module is finished.
      $link = $this->getModuleResultLink($module, $user, $title, $gid, $last_cert_date);
    }
    else {
      $link = $title;
    }

    return $link;
  }

  /**
   * Gets the LP 1st step link.
   *
   * @param \Drupal\opigno_learning_path\LPStatusInterface|null $lp_attempt
   *   The last LP attempt entity.
   * @param array $step
   *   The current step data array.
   * @param string $title
   *   The LP step title.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account to generate the step link for.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The LP group.
   * @param int|string|null $last_cert_date
   *   The latest certification date.
   *
   * @return \Drupal\Core\GeneratedLink|string
   *   The generated 1st step link.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getFirstStepLink(
    ?LPStatusInterface $lp_attempt,
    array $step,
    string $title,
    AccountInterface $user,
    GroupInterface $group,
    $last_cert_date
  ) {
    $module = $this->getModuleFromStep($step);
    $gid = (int) $group->id();
    $status = opigno_learning_path_get_step_status($step, $user->id());
    if (!$lp_attempt instanceof LPStatusInterface ||
      $lp_attempt instanceof LPStatusInterface && $status === 'pending') {
      $first_step = OpignoGroupManagedContent::load($step['cid']);
      $content_type = $this->contentTypesManager->createInstance($first_step->getGroupContentTypeId());
      $step_url = $content_type->getStartContentUrl($first_step->getEntityId(), $gid);
      $link = Link::createFromRoute($title, $step_url->getRouteName(), $step_url->getRouteParameters())
        ->toString();
    }
    elseif ($module instanceof OpignoModuleInterface) {
      // The link to the result page if the module is finished.
      $link = $this->getModuleResultLink($module, $user, $title, $gid, $last_cert_date);
    }
    else {
      $link = $title;
    }

    return $link;
  }

  /**
   * Gets the module result link.
   *
   * @param \Drupal\opigno_module\Entity\OpignoModuleInterface $module
   *   The module to get the result link for.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to get the module results link for.
   * @param string $title
   *   The link title.
   * @param int $gid
   *   The LP group ID the module belongs to.
   * @param int|string|null $last_cert_date
   *   The latest certification date.
   *
   * @return \Drupal\Core\GeneratedLink
   *   The generated module result link.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getModuleResultLink(
    OpignoModuleInterface $module,
    AccountInterface $user,
    string $title,
    int $gid,
    $last_cert_date,
  ): GeneratedLink {
    $module_id = (int) $module->id();
    // If the latest module attempt is finished, add the link to the results for
    // the target attempt (best or the latest, depending on the setting).
    $target_attempt = $module->getLastModuleAttempt((int) $user->id(), $gid, TRUE);
    if ($target_attempt instanceof UserModuleStatusInterface && $target_attempt->isFinished()) {
      $attempts = $module->getModuleAttempts($user, NULL, $last_cert_date);
      $target_attempt = $this->getTargetAttempt($attempts, $module);
    }

    return $target_attempt instanceof UserModuleStatusInterface && $target_attempt->isFinished()
      ? Link::createFromRoute($title, 'opigno_module.module_result', [
        'opigno_module' => $module_id,
        'user_module_status' => $target_attempt->id(),
      ], ['query' => ['skip-links' => TRUE]])->toString()
      : Link::createFromRoute($title, 'opigno_module.take_module', [
        'group' => $gid,
        'opigno_module' => $module_id,
      ])->toString();
  }

  /**
   * Gets the loaded module from the step data.
   *
   * @param array $step
   *   The step data array.
   *
   * @return \Drupal\opigno_module\Entity\OpignoModuleInterface|null
   *   The loaded module from the step, NULL if there is not a module step.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getModuleFromStep(array $step): ?OpignoModuleInterface {
    $typology = $step['typology'] ?? NULL;
    $id = $step['id'] ?? NULL;
    $module = NULL;
    if ($typology === 'Module' && $id) {
      $module = $this->entityTypeManager->getStorage('opigno_module')->load($id);
    }

    return $module instanceof OpignoModuleInterface ? $module : NULL;
  }

  /**
   * Gets the step action.
   *
   * @param string $typology
   *   The step typology.
   * @param string|int|null $id
   *   The step entity ID.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The related group.
   *
   * @return array
   *   The render array of the action.
   */
  protected function getStepAction(string $typology, $id, GroupInterface $group): array {
    return [];
  }

  /**
   * Training content main block.
   *
   * @param array $content
   *   Build main data array.
   * @param array $steps
   *   Built steps data array.
   */
  public function trainingContentMain(array &$content, array $steps): void {
    $content['tabs'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['lp_tabs', 'nav', 'mb-4']],
    ];

    $content['tabs']['training'] = [
      '#markup' => '<a class="lp_tabs_link active" href="#training-content">' . $this->t('Training Content') . '</a>',
    ];

    $content['tab_content'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tab-content']],
    ];

    $steps['display_actions'] = $this->displayStepActions;
    $content['tab_content']['training'] = [
      '#theme' => 'opigno_learning_path_training_content_steps',
      'steps' => $steps,
    ];

  }

  /**
   * Training document block.
   *
   * @param array $content
   *   Build main data array.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group object instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function trainingContentDocuments(array &$content, GroupInterface $group): void {
    $tft_url = Url::fromRoute('tft.group', ['group' => $group->id()])->toString();

    $content['tabs'][] = $tft_url = [
      '#markup' => '<div class="see-all"><a href="' . $tft_url . '">' . $this->t('See all') . '</a></div>',
    ];

    $block_render = $this->attachBlock('opigno_documents_last_group_block', ['group' => $group->id()]);
    $block_render["content"]['link'] = $tft_url;
    $content['tab_content']['documents'] = (isset($block_render["content"]["content"]) && !empty($block_render["content"]["content"])) ? [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'documents',
      ],
      'block' => [
        'content' => $block_render["content"],
      ],
    ] : [];
  }

  /**
   * Building forum block for sidebar.
   *
   * @param array $content
   *   Build main data array.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group object instance.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   User object instance.
   *
   * @return array
   *   Built content data array.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function trainingContentForum(array &$content, GroupInterface $group, AccountInterface $user): array {
    $has_enable_forum_field = $group->hasField('field_learning_path_enable_forum');
    $has_forum_field = $group->hasField('field_learning_path_forum');
    if ($has_enable_forum_field && $has_forum_field) {
      $enable_forum_field = $group->get('field_learning_path_enable_forum')->getValue();
      $forum_field = $group->get('field_learning_path_forum')->getValue();
      if (!empty($enable_forum_field) && !empty($forum_field)) {
        $enable_forum = $enable_forum_field[0]['value'];
        $forum_tid = $forum_field[0]['target_id'];
        if ($enable_forum && _opigno_forum_access($forum_tid, $user)) {
          $forum_url = Url::fromRoute('forum.page', ['taxonomy_term' => $forum_tid])->toString();
          $content['tabs'][] = $forum_url = [
            '#markup' => '<div class="see-all"><a href="' . $forum_url . '">' . $this->t('See all') . '</a></div>',
          ];
          $block_render = $this->attachBlock('opigno_forum_last_topics_block', ['taxonomy_term' => $forum_tid]);
          $block_render["content"]['link'] = $forum_url;
          $content['tab_content']['forum'] = $block_render["topics"] ? [
            '#type' => 'container',
            '#attributes' => [
              'id' => 'forum',
            ],
            'block' => [
              'content' => $block_render["content"],
            ],
          ] : [];
        }
      }
    }
    return $content;
  }

  /**
   * Attaches a block by block name.
   *
   * @param string $name
   *   Name of the block.
   * @param array $config
   *   Main block config array.
   *
   * @return array
   *   Returns built block render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function attachBlock(string $name, array $config = []): array {
    // You can hard code configuration or you load from settings.
    $plugin_block = $this->blockManager->createInstance($name, $config);
    // Some blocks might implement access check.
    $access_result = $plugin_block->access($this->currentUser);
    // Return empty render array if user doesn't have access.
    // $access_result can be boolean or an AccessResult class.
    if ((is_object($access_result) && $access_result->isForbidden()) || (is_bool($access_result) && !$access_result)) {
      // You might need to add some cache tags/contexts.
      return [];
    }

    // In some cases, you need to add the cache tags/context depending on
    // the block implemention. As it's possible to add the cache tags and
    // contexts in the render method and in ::getCacheTags and
    // ::getCacheContexts methods.
    return $plugin_block->build();
  }

  /**
   * Loads a group by LP forum term.
   *
   * @param \Drupal\taxonomy\Entity\Term $term
   *   Term object instance.
   *
   * @return mixed
   *   Loaded group or bool.
   */
  public static function loadGroupByForum(Term $term) {
    $query = \Drupal::entityQuery('group')
      ->accessCheck()
      ->condition('field_learning_path_forum.target_id', $term->id());
    $nids = $query->execute();
    return $nids ? Group::load(reset($nids)) : FALSE;
  }

  /**
   * Filters restricted step items.
   *
   * If user is member of the meeting/ilt or if meeting/ilt has no restriction
   * check that the user enrolled to the group.
   *
   * Legacy implementation was copied to avoid a duplication of code.
   *
   * @param array $step
   *   The step array of module/meeting/ilt.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user entity.
   *
   * @return bool
   *   TRUE if the user is member of the "step", FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function filterStep(array $step, AccountInterface $user): bool {
    if ($step['typology'] === 'Meeting') {
      // If the user have not the collaborative features' role.
      if (!$user->hasPermission('view meeting entities')) {
        return FALSE;
      }

      // If the user is not a member of the meeting.
      /** @var \Drupal\opigno_moxtra\Entity\Meeting $meeting */
      $meeting = \Drupal::entityTypeManager()
        ->getStorage('opigno_moxtra_meeting')
        ->load($step['id']);
      if (!$meeting->isMember($user->id())) {
        return FALSE;
      }
    }
    elseif ($step['typology'] === 'ILT') {
      // If the user is not a member of the ILT.
      /** @var \Drupal\opigno_ilt\ILTInterface $ilt */
      $ilt = \Drupal::entityTypeManager()
        ->getStorage('opigno_ilt')
        ->load($step['id']);
      if (!$ilt->isMember($user->id())) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
