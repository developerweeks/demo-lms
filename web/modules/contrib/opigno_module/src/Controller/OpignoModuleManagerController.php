<?php

namespace Drupal\opigno_module\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\h5p\Entity\H5PContent;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5peditor\H5PEditor\H5PEditorUtilities;
use Drupal\opigno_learning_path\LearningPathAccess;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drupal\opigno_module\Entity\OpignoActivityType;
use Drupal\opigno_module\Entity\OpignoModule;
use Drupal\opigno_module\Entity\OpignoModuleInterface;
use Opis\JsonSchema\Validator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for all the actions of the Opigno module manager.
 */
class OpignoModuleManagerController extends ControllerBase {

  /**
   * H5P Activities Details.
   *
   * @var array|object|null
   */
  protected $h5pActivitiesDetails;

  /**
   * A DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * A route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * The group membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected GroupMembershipLoaderInterface $membershipLoader;

  /**
   * The requested cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * Opigno module service.
   *
   * @var \Drupal\opigno_module\Controller\OpignoModuleController
   */
  protected OpignoModuleController $moduleController;

  /**
   * OpignoModuleManagerController constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The current request.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module list extension service.
   * @param \Drupal\group\GroupMembershipLoaderInterface $group_membership_loader
   *   The group membership service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The requested cache bin.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   * @param \Drupal\opigno_module\Controller\OpignoModuleController $module_controller
   *   The Opigno module service.
   */
  public function __construct(
    Connection $database,
    RouteMatchInterface $routeMatch,
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request,
    EntityFormBuilderInterface $entity_form_builder,
    ModuleExtensionList $module_extension_list,
    GroupMembershipLoaderInterface $group_membership_loader,
    CacheBackendInterface $cache,
    ConfigFactoryInterface $config,
    OpignoModuleController $module_controller
  ) {
    $this->h5pActivitiesDetails = $this->getH5pActivitiesDetails();
    $this->database = $database;
    $this->routeMatch = $routeMatch;
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request->getCurrentRequest();
    $this->entityFormBuilder = $entity_form_builder;
    $this->moduleExtensionList = $module_extension_list;
    $this->membershipLoader = $group_membership_loader;
    $this->cache = $cache;
    $this->configFactory = $config;
    $this->moduleController = $module_controller;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('entity.form_builder'),
      $container->get('extension.list.module'),
      $container->get('group.membership_loader'),
      $container->get('cache.default'),
      $container->get('config.factory'),
      $container->get('opigno_module.opigno_module')
    );
  }

  /**
   * Check the access for the activity create/edit form.
   */
  public function accessItemForm(OpignoModuleInterface $opigno_module, AccountInterface $account) {
    // Allow access if the user is a platform-level content manager.
    if ($account->hasPermission('manage group content in any group')) {
      return AccessResult::allowed();
    }

    $item = $this->routeMatch->getParameter('item');
    if (isset($item) && $item > 0) {
      // If it is edit form, check activity update access.
      $activity = OpignoActivity::load($item);
      return $activity->access('update', $account, TRUE);
    }

    // Check activity create access.
    $create_access = $this->entityTypeManager
      ->getAccessControlHandler('opigno_activity')
      ->createAccess('opigno_activity', $account);
    if ($create_access) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * Method called when the manager needs a create or edit form.
   */
  public function getItemForm(OpignoModuleInterface $opigno_module, $type = NULL, $item = 0) {
    // Get the good form from the corresponding content type.
    if ($item > 0) {
      $entity = OpignoActivity::load($item);
      if (!$entity->access('update')) {
        throw new AccessDeniedHttpException();
      }
    }
    else {
      $entity = OpignoActivity::create([
        'type' => $type,
      ]);
    }

    $form_build = $this->entityFormBuilder->getForm($entity);
    $form_build['#attached']['library'][] = 'opigno_module/ajax_form';

    // Returns the form.
    return $form_build;
  }

  /**
   * Returns activity preview.
   */
  public function getActivityPreview(OpignoActivity $opigno_activity) {
    $view_builder = $this->entityTypeManager->getViewBuilder($opigno_activity->getEntityTypeId());
    return $view_builder->view($opigno_activity, 'activity');
  }

  /**
   * Get activities bank for module with 'add' operation.
   *
   * This method does the same think as
   * \Drupal\opigno_module\Controller\LearningPathController::activitiesBank()
   * but is using in Training context (on Learning Path Manager page).
   */
  public function activitiesBankLpm() {
    // Output activities bank view.
    $build['activities_bank'] = views_embed_view('opigno_activities_bank_lp_interface');

    return $build;
  }

  /**
   * Ajax callback.
   */
  public function ajaxCheckedActivities() {
    $request_data = $this->request->get('data', '');
    if (!empty($request_data)) {
      $data = self::jsonDecodeValidated($request_data);
      $schema = json_decode(file_get_contents(
        $this->moduleExtensionList->getPath('opigno_module') . '/json-schema/schema.json'
      ));

      if (empty($data)) {
        die;
      }

      $validator = new Validator();
      $result = $validator->validate($data, $schema);
      if (!$result->isValid()) {
        die;
      }

      $checkboxes_ids = !empty($_SESSION['activities_bank']['checkboxes_ids']) ? $_SESSION['activities_bank']['checkboxes_ids'] : [];
      $activities_ids = !empty($_SESSION['activities_bank']['activities_ids']) ? $_SESSION['activities_bank']['activities_ids'] : [];

      if (!empty($data->checked) && array_search($data->checked, $checkboxes_ids) === FALSE) {
        $checkboxes_ids[] = $data->checked;
        $activities_ids[] = $data->activityID;
      }
      elseif (!empty($data->unchecked)) {
        $key = array_search($data->unchecked, $checkboxes_ids);
        if ($key !== FALSE) {
          unset($checkboxes_ids[$key]);
        }
        $key = array_search($data->activityID, $activities_ids);
        if ($key !== FALSE) {
          unset($activities_ids[$key]);
        }
      }

      $_SESSION['activities_bank'] = [
        'checkboxes_ids' => $checkboxes_ids,
        'activities_ids' => $activities_ids,
      ];
    }

    die;
  }

  /**
   * Check access.
   */
  public function ajaxCheckedActivitiesAccess() {
    $account = $this->currentUser();

    $is_content_manager = LearningPathAccess::memberHasRole('content_manager', $account);

    if ($is_content_manager > 0 || $account->hasPermission('administer module entities')) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::forbidden();
    }
  }

  /**
   * Ajax form callback.
   */
  public static function ajaxFormEntityCallback(&$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If errors, returns the form with errors and messages.
    if ($form_state->hasAnyErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['error_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -1000,
      ];
      $form['#sorted'] = FALSE;
      $response->addCommand(new HtmlCommand('#activity-wrapper', $form));

      return $response;
    }

    $entity = $form_state->getBuildInfo()['callback_object']->getEntity();

    $item = [];
    $item['id'] = $entity->id();
    $item['name'] = $entity->getName();

    $command = new SettingsCommand([
      'formValues' => $item,
      'messages' => \Drupal::messenger()->all(),
    ], TRUE);

    \Drupal::messenger()->deleteAll();

    $response->addCommand($command);
    return $response;
  }

  /**
   * Submit handler added in the form by opigno_module_form_alter().
   *
   * @see opigno_module_form_alter()
   */
  public static function ajaxFormEntityFormSubmit($form, FormState &$form_state) {
    // Gets back the content type and module id.
    $build_info = $form_state->getBuildInfo();
    $params = \Drupal::routeMatch()->getParameters();

    $module = $params->get('opigno_module');
    $type_id = $params->get('type');
    $item_id = $params->get('item');

    // If one information missing, return an error.
    if (!isset($module) || !isset($type_id)) {
      \Drupal::logger('opigno_module')->error(t('Required parameter is missing from request.'));
      return;
    }

    // Get the newly or edited entity.
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $build_info['callback_object']->getEntity();

    // Clear user input.
    $input = $form_state->getUserInput();
    // We should not clear the system items from the user input.
    $clean_keys = $form_state->getCleanValueKeys();
    $clean_keys[] = 'ajax_page_state';

    foreach ($input as $key => $item) {
      if (!in_array($key, $clean_keys)
        && substr($key, 0, 1) !== '_') {
        unset($input[$key]);
      }
    }

    // Store new entity for display in the AJAX callback.
    $input['entity'] = $entity;
    $form_state->setUserInput($input);

    // Rebuild the form state values.
    $form_state->setRebuild();
    $form_state->setStorage([]);

    // Assign activity to module if entity is new.
    if (!isset($item_id)) {
      /** @var \Drupal\opigno_module\Controller\OpignoModuleController $opigno_module_controller */
      $opigno_module_controller = \Drupal::service('opigno_module.opigno_module');
      $opigno_module_controller->activitiesToModule([$entity], $module);
    }
  }

  /**
   * Checks access for the activities overview.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current user.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Access result.
   */
  public function accessActivitiesOverview(AccountInterface $account) {
    // Allow access if the user is a platform-level content manager.
    if ($account->hasPermission('manage group content in any group')) {
      return AccessResult::allowed();
    }

    // Allow access if the user is a group-level content manager in any group.
    $memberships = $this->membershipLoader->loadByUser($account);
    foreach ($memberships as $membership) {
      $group = $membership->getGroup();
      if ($group->access('update', $account)) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::forbidden();
  }

  /**
   * Get the list of the existing activity types.
   */
  public function getActivityTypes() {
    // Get activity types.
    $types = $this->entityTypeManager->getStorage('opigno_activity_type')->loadMultiple();
    $types = array_filter($types, function ($type) {
      /** @var \Drupal\opigno_module\Entity\OpignoActivityType $type */
      return $type->id() !== 'opigno_h5p';
    });

    $types = array_map(function ($type) {
      /** @var \Drupal\opigno_module\Entity\OpignoActivityType $type */
      return [
        'bundle' => $type->id(),
        'name' => $type->label(),
        'description' => $this->getNonH5pDescription($type),
        'external_package' => FALSE,
      ];
    }, $types);

    // Get H5P libraries.
    /** @var \Drupal\h5p\H5PDrupal\H5PDrupal $interface */
    $interface = H5PDrupal::getInstance();
    $libraries = $interface->loadLibraries();

    // Flatten libraries array.
    $libraries = array_map(function ($library) {
      return end($library);
    }, $libraries);
    // Filter runnable libraries.
    $libraries = array_filter($libraries, function ($library) {
      return $library->runnable == 1;
    });

    // Get library data.
    $libraries = array_map(function ($library) {
      return [
        'bundle' => 'opigno_h5p',
        'library' => $library->name,
        'name' => $library->title,
        'description' => $this->getH5pDescription($library->name),
        'major_version' => $library->major_version,
        'minor_version' => $library->minor_version,
        'external_package' => FALSE,
      ];
    }, $libraries);

    $types = array_merge($types, $libraries);

    // Remove unwanted activities.
    $config = $this->configFactory->get('opigno_module.settings');
    $disabled = $config->get('disabled_h5p');
    foreach ($disabled as $item) {
      unset($types[$item]);
    }

    // Set external package types.
    $types['opigno_scorm']['external_package'] = TRUE;
    $types['opigno_tincan']['external_package'] = TRUE;

    if (self::getPptConvertAllow()) {
      $ppt = [
        'bundle' => 'external_package_ppt',
        'name' => $this->t('PPT(X) Course Presentation'),
        'description' => $this->t('This activity allows creation H5P Course Presentation content imported from PowerPoint files. Slides from PowerPoint presentation will be imported as images into H5P Course Presentation slider. Allowed file extensions are ppt/pptx.'),
      ];
      if (array_key_exists('H5P.CoursePresentation', $types)) {
        // Place new item after H5P.CoursePresentation element.
        $types_updated = [];
        foreach ($types as $key => $type) {
          $types_updated[$key] = $type;
          if ($key == 'H5P.CoursePresentation') {
            $types_updated['external_package_ppt'] = $ppt;
          }
        }
        $types = $types_updated;
      }
      else {
        // Place new item at the end of the types array.
        $types['external_package_ppt'] = $ppt;
      }
    }

    $this->arrayUnshiftAssoc($types, 'external_package', [
      'bundle' => 'external_package',
      'name' => $this->t('External package'),
      'description' => $this->t('This activity allows to easily load training packages created externally. The following formats are supported: SCORM 1.2 and 2004, TinCan and H5P.'),
    ]);

    return new JsonResponse($types, Response::HTTP_OK);
  }

  /**
   * Returns H5P Activities Details.
   */
  public function getH5pActivitiesDetails() {
    $editor = H5PEditorUtilities::getInstance();
    $content_types = $editor->ajaxInterface->getContentTypeCache();
    return $content_types;
  }

  /**
   * Returns non H5P description.
   *
   * @param \Drupal\opigno_module\Entity\OpignoActivityType $activity
   *   Activity.
   *
   * @return null|string
   *   Non H5P description.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNonH5pDescription(OpignoActivityType $activity): ?string {
    $html = NULL;

    $html .= '<p class="summary">' . $activity->getSummary() . '</p>';
    $html .= '<p class="description">' . $activity->getDescription() . '</p>';

    if ($image_id = $activity->getImageId()) {
      $image = $this->entityTypeManager->getStorage('file')->load($image_id);
      if ($image instanceof FileInterface) {
        $image_url = $this->entityTypeManager->getStorage('image_style')
          ->load('large')
          ->buildUrl($image->getFileUri());
        $html .= '<p class="images">';
        $html .= '<img src="' . $image_url . '" alt="" />';
        $html .= '</p>';
      }
    }

    return $html;
  }

  /**
   * Returns ppt convert allow flag.
   */
  public static function getPptConvertAllow() {
    static $allow;

    if (!isset($allow)) {
      $allow = FALSE;

      $module = FALSE;
      $moduleHandler = \Drupal::service('module_handler');
      if ($moduleHandler->moduleExists('h5p')) {
        $module = TRUE;
      }

      $libreoffice = FALSE;
      if (exec(ExternalPackageController::getLibreOfficeBinPath() . ' --version')) {
        $libreoffice = TRUE;
      }

      $imagemagick = FALSE;
      if (exec(ExternalPackageController::getImagemagickBinPath() . ' --version')) {
        $imagemagick = TRUE;
      }

      $h5p_library = FALSE;
      $editor = H5PEditorUtilities::getInstance();
      $content_types = $editor->ajaxInterface->getContentTypeCache();
      if ($content_types) {
        foreach ($content_types as $activity) {
          if ($activity->machine_name == 'H5P.CoursePresentation') {
            $h5p_library = TRUE;
            break;
          }
        }
      }

      if ($module && $libreoffice && $imagemagick && $h5p_library) {
        $allow = TRUE;
      }
    }

    return $allow;
  }

  /**
   * Returns H5P description.
   */
  public function getH5pDescription($libTitle) {
    $html = NULL;

    foreach ($this->h5pActivitiesDetails as $detail) {
      if ($detail->machine_name == $libTitle) {
        $html .= '<p class="summary">' . $detail->summary . '</p>';
        $html .= '<p class="description">' . $detail->description . '</p>';

        $screenshots = json_decode($detail->screenshots);
        if ($screenshots) {
          $html .= '<p class="images">';
          foreach ($screenshots as $screenshot) {
            $html .= '<img src="' . $screenshot->url . '" alt="" />';
          }
          $html .= '</p>';
        }
        break;
      }
    }

    return $html;
  }

  /**
   * Get the list of the existing activities.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getActivitiesList(OpignoModule $opigno_module) {
    $activities_storage = $this->entityTypeManager->getStorage('opigno_activity');
    $query = $activities_storage->getQuery();
    $query->accessCheck();

    if ($opigno_module->getSkillsActive()) {
      $query->condition('auto_skills', 1);

      if ($opigno_module->getTargetSkill() && $opigno_module->getTargetSkill() > 0) {
        $target_skill = $opigno_module->getTargetSkill();
        $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $skills_tree = $term_storage->loadTree('skills', $target_skill);
        $skills_ids = [];

        foreach ($skills_tree as $skill) {
          $skills_ids[] = $skill->tid;
        }

        if (!empty($skills_ids)) {
          $query->condition('skills_list', $skills_ids, 'IN');
        }
      }

      $activities_ids = $query->execute();
    }
    else {
      $group = $query->orConditionGroup()
        ->condition('auto_skills', 0)
        ->notExists('auto_skills');

      $activities_ids = $query->condition($group)->execute();
    }

    // Use cache because it's too hard upload all every time.
    $hash = md5(serialize($activities_ids));
    $list = $this->cache->get($hash);

    if (empty($list) && !empty($activities_ids)) {

      $activities = $activities_storage->loadMultiple($activities_ids);

      $list = array_map(function ($activity) {
        /** @var \Drupal\opigno_module\Entity\OpignoActivity $activity */
        $data = [];
        $data['name'] = $activity->label();
        $data['activity_id'] = $activity->id();
        $data['type'] = $activity->bundle();

        // If H5P content, add library info.
        if ($data['type'] === 'opigno_h5p') {
          $value = $activity->get('opigno_h5p')->getValue();
          if ($value && $activity->get('opigno_h5p')->getValue()[0]['h5p_content_id'] !== NULL) {
            $cid = $activity->get('opigno_h5p')->getValue()[0]['h5p_content_id'];

            if ($content = H5PContent::load($cid)) {
              $library = $content->getLibrary();
              if ($library) {
                $data['library'] = $library->name;
              }
            }
          }
        }

        return $data;
      }, $activities);

      $this->cache->set($hash, $list);
    }
    elseif (!empty($list)) {
      $list = $list->data;
    }

    return new JsonResponse($list, Response::HTTP_OK);
  }

  /**
   * Add existing activity to the module.
   */
  public function addActivityToModule(OpignoModule $opigno_module, OpignoActivity $opigno_activity, $group = NULL) {
    $this->moduleController->activitiesToModule([$opigno_activity], $opigno_module, $group);

    return new JsonResponse([], Response::HTTP_OK);
  }

  /**
   * Checks access for the activity update weight.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current user.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Access result.
   */
  public function accessActivityUpdateWeight(AccountInterface $account) {
    $datas = json_decode($this->request->getContent());

    // Check that current user can edit all parent Opigno modules.
    $omr_ids = array_map(function ($value) {
      return $value->omr_id;
    }, $datas->acitivies_weight);
    $module_ids = $this->database
      ->select('opigno_module_relationship', 'omr')
      ->fields('omr', ['parent_id'])
      ->condition('omr_id', $omr_ids, 'IN')
      ->groupBy('parent_id')
      ->execute()
      ->fetchCol();
    $modules = OpignoModule::loadMultiple($module_ids);
    foreach ($modules as $module) {
      /** @var \Drupal\opigno_module\Entity\OpignoModule $module */
      if (!$module->access('update')) {
        return AccessResult::forbidden();
      }
    }

    return AccessResult::allowed();
  }

  /**
   * Update activity weight.
   */
  public function activityUpdateWeight(Request $request) {
    $datas = json_decode($request->getContent());
    if (empty($datas->acitivies_weight)) {
      return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
    }

    foreach ($datas->acitivies_weight as $value) {
      $this->database->merge('opigno_module_relationship')
        ->keys([
          'omr_id' => $value->omr_id,
        ])
        ->fields([
          'weight' => $value->weight,
        ])
        ->execute();
    }
    return new JsonResponse(NULL, Response::HTTP_OK);
  }

  /**
   * Array unshift.
   */
  public function arrayUnshiftAssoc(&$arr, $key, $val) {
    $arr = array_reverse($arr, TRUE);
    $arr[$key] = $val;
    $arr = array_reverse($arr, TRUE);
    return $arr;
  }

  /**
   * Decoding JSON data with the length and errors validation.
   *
   * @param string $data
   *   Decoded string of JSON data.
   * @param int $limit
   *   Limit to check a file size.
   * @param int $flags
   *   Bitmask of JSON decode options, see json_decode().
   * @param int $depth
   *   User specified recursion depth, see json_decode().
   *
   * @return mixed|null
   *   A valid JSON or empty string in case of error.
   */
  public static function jsonDecodeValidated(string $data, int $limit = 1, int $flags = JSON_THROW_ON_ERROR, int $depth = 512) {
    $size_is_valid = (!isset($data[$limit * 1024 * 1024]));
    try {
      if (!$size_is_valid) {
        throw new \Exception('Invalid data size.');
      }
      return json_decode($data, FALSE, $depth, $flags);
    }
    catch (\Exception | \JsonException $e) {
      return NULL;
    }
  }

}
