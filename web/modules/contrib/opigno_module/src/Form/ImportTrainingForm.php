<?php

namespace Drupal\opigno_module\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\file\Entity\File;
use Drupal\group\Entity\Group;
use Drupal\media\Entity\Media;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedLink;
use Drupal\opigno_module\Controller\OpignoModuleController;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drupal\opigno_module\Entity\OpignoActivityInterface;
use Drupal\opigno_module\Entity\OpignoModule;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Import Course form.
 */
class ImportTrainingForm extends ImportBaseForm {

  /**
   * The Opigno module service.
   *
   * @var \Drupal\opigno_module\Controller\OpignoModuleController
   */
  protected $moduleService;

  /**
   * {@inheritdoc}
   */
  public function __construct(OpignoModuleController $module_service, ...$default) {
    parent::__construct(...$default);
    $this->moduleService = $module_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('opigno_module.opigno_module'),
      $container->get('file_system'),
      $container->get('datetime.time'),
      $container->get('database'),
      $container->get('serializer'),
      $container->get('config.factory'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'import_training_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $mode = NULL) {
    $form['training_opi'] = [
      '#title' => $this->t('Training'),
      '#type' => 'file',
      '#description' => $this->t('Here you can import training. Allowed extension: opi'),
    ];

    $ajax_id = "ajax-form-entity-external-package";
    $form['#attributes']['class'][] = $ajax_id;
    $form['#attached']['library'][] = 'opigno_module/ajax_form';

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validation is optional.
    $files = $this->getRequest()->files->get('files', []);
    $uploaded = $files['training_opi'] ?? NULL;

    if (!$uploaded instanceof UploadedFile || !$uploaded->getClientOriginalName()) {
      // Only need to validate if the field actually has a file.
      $form_state->setError(
        $form['training_opi'],
        $this->t("The file was not uploaded.")
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Prepare folder.
    if (!$this->prepareTemporary()) {
      $this->messenger()->addMessage($this->t('Failed to create directory.'), 'error');
      return;
    }
    $files = $this->getImportFiles();

    if (in_array('list_of_files.json', $files)) {
      $file_path = $this->tmp . '/list_of_files.json';
      $real_path = $this->fileSystem->realpath($file_path);
      $file = file_get_contents($real_path);

      $format = 'json';
      $ids = [];
      try {
        $content = $this->serializer->decode($file, $format);
      }
      catch (\Exception $e) {
        $this->messenger->addMessage($e->getMessage(), 'error');
        return;
      }
      $prev_id = 0;
      if (empty($content['training'])) {
        $this->messenger->addMessage($this->t('Incorrect archive structure.'), 'error');
        return;
      }
      $file_path = $this->tmp . '/' . $content['training'];
      $real_path = $this->fileSystem->realpath($file_path);
      $file = file_get_contents($real_path);
      try {
        $training_content_array = $this->serializer->decode($file, $format);
      }
      catch (\Exception $e) {
        $this->messenger()->addMessage($e->getMessage(), 'error');
        return;
      }
      $training_content = reset($training_content_array);
      $new_training = $this->importTraining($training_content);

      if (!empty($content['courses'])) {
        foreach ($content['courses'] as $course_path) {
          $file_path = $this->tmp . '/' . $course_path;
          $real_path = $this->fileSystem->realpath($file_path);
          $file = file_get_contents($real_path);
          try {
            $course_content_array = $this->serializer->decode($file, $format);
          }
          catch (\Exception $e) {
            $this->messenger->addMessage($e->getMessage(), 'error');
            continue;
          }

          $course_content = reset($course_content_array);

          $new_course = $this->importCourse($course_content);

          $ids['course'][$course_content['id'][0]['value']] = $new_course->id();

          $managed_content = $course_content['managed_content'];
          $new_training->addContent($new_course, 'subgroup:opigno_course');

          $new_content = OpignoGroupManagedContent::createWithValues(
            $new_training->id(),
            'ContentTypeCourse',
            $new_course->id(),
            $course_content['managed_content']['success_score_min'][0]['value'] ?? 0,
            $course_content['managed_content']['is_mandatory'][0]['value'],
            $course_content['managed_content']['coordinate_x'][0]['value'],
            $course_content['managed_content']['coordinate_y'][0]['value']
          );

          $new_content->save();
          $ids['link'][$managed_content['id'][0]['value']] = $new_content->id();
          $ids['link_child'][$course_content['id'][0]['value']] = $new_content->id();
        }
      }

      if (!empty($content['modules'])) {
        foreach ($content['modules'] as $module_name => $module_path) {
          $file_path = $this->tmp . '/' . $module_path;
          $real_path = $this->fileSystem->realpath($file_path);
          $file = file_get_contents($real_path);
          $module_content_array = $this->serializer->decode($file, $format);
          $module_content = reset($module_content_array);

          $new_module = $this->importModule($module_content);

          $ids['module'][$module_content['id'][0]['value']] = $new_module->id();
          $managed_content = $module_content['managed_content'];
          $parent_links = $module_content['parent_links'];

          $parent_group_id = $new_training->id();

          if (isset($module_content['course_rel'])) {
            $parent_group_id = $ids['course'][$module_content['course_rel']];
            $course = Group::load($parent_group_id);
            $course->addContent($new_module, 'opigno_module_group');
          }
          else {
            $new_training->addContent($new_module, 'opigno_module_group');
          }

          $new_content = OpignoGroupManagedContent::createWithValues(
            $parent_group_id,
            $managed_content['group_content_type_id'][0]['value'],
            $new_module->id(),
            $managed_content['success_score_min'][0]['value'] ?? 0,
            $managed_content['is_mandatory'][0]['value'],
            $managed_content['coordinate_x'][0]['value'],
            $managed_content['coordinate_y'][0]['value']
          );

          $new_content->save();
          $ids['link'][$managed_content['id'][0]['value']] = $new_content->id();

          foreach ($content['activities'][$module_name] as $activity_file_name) {
            $file_path = $this->tmp . '/' . $activity_file_name;
            $real_path = $this->fileSystem->realpath($file_path);
            $file = file_get_contents($real_path);
            $activity_content_array = $this->serializer->decode($file, $format);
            $activity_content = reset($activity_content_array);

            $type = $activity_content['type'][0]['target_id'] ?? '';
            $new_activity = $this->importActivity($activity_content, $type);

            $ids['activities'][$activity_content['id'][0]['value']] = $new_activity->id();

            $max_score = $activity_content['max_score'] ?? 10;
            $this->moduleService->activitiesToModule([$new_activity], $new_module, NULL, $max_score);
          }

          foreach ($parent_links as $link) {
            if ($link['required_activities']) {
              foreach ($link['required_activities'] as $key_req => $require_string) {
                $require = explode('-', $require_string);
                $link['required_activities'][$key_req] = str_replace($require[0], $ids['activities'][$require[0]], $link['required_activities'][$key_req]);
              }

              $link['required_activities'] = serialize($link['required_activities']);
            }
            else {
              $link['required_activities'] = NULL;
            }

            $new_content_id = $new_content->id();
            $new_parent_id = $ids['link'][$link['parent_content_id']];

            if ($new_content_id === $ids['link'][$link['parent_content_id']] && !empty($prev_id)) {
              $new_parent_id = $prev_id;
            }

            if (!empty($ids['link'][$link['parent_content_id']])) {
              OpignoGroupManagedLink::createWithValues(
                $parent_group_id,
                $new_parent_id,
                $new_content_id,
                $link['required_score'],
                $link['required_activities']
              )->save();

              $prev_id = $new_content_id;
            }
          }
        }
      }

      // Set links for courses.
      if (!empty($content['courses'])) {
        foreach ($content['courses'] as $course_path) {
          $file_path = $this->tmp . '/' . $course_path;
          $real_path = $this->fileSystem->realpath($file_path);
          $file = file_get_contents($real_path);
          $course_content_array = $this->serializer->decode($file, $format);
          $course_content = reset($course_content_array);
          $parent_links = $course_content['parent_links'];

          foreach ($parent_links as $link) {
            if ($link['required_activities']) {
              foreach ($link['required_activities'] as $key_req => $require_string) {
                $require = explode('-', $require_string);
                $link['required_activities'][$key_req] = str_replace($require[0], $ids['activities'][$require[0]], $link['required_activities'][$key_req]);
              }
            }

            OpignoGroupManagedLink::createWithValues(
              $new_training->id(),
              $ids['link'][$link['parent_content_id']],
              $ids['link_child'][$link['child_content_id']],
              $link['required_score'],
              serialize($link['required_activities'])
            )->save();
          }
        }
      }

      // Import documents library.
      $tids_relationships = [];
      $main_tid = $new_training->get('field_learning_path_folder')->getString();
      $tids_relationships[$training_content['field_learning_path_folder'][0]['target_id']] = $main_tid;

      if (!empty($content['terms'])) {
        foreach ($content['terms'] as $term_file_name) {
          $file_path = $this->tmp . '/library/' . $term_file_name;
          $real_path = $this->fileSystem->realpath($file_path);
          $file = file_get_contents($real_path);
          $term_content = $this->serializer->decode($file, $format);

          $parent_id = $tids_relationships[$term_content['parent'][0]['target_id']];

          $new_term = Term::create([
            'name' => $term_content['name'][0]['value'],
            'langcode' => $term_content['langcode'][0]['value'],
            'vid' => 'tft_tree',
            'parent' => $parent_id,
          ]);

          $new_term->save();

          $tids_relationships[$term_content['tid'][0]['value']] = $new_term->id();
        }
      }

      if (!empty($content['files'])) {
        foreach ($content['files'] as $document) {
          $file_path = $this->tmp . '/library/' . $document['file'];
          $real_path = $this->fileSystem->realpath($file_path);
          $file = file_get_contents($real_path);
          $file_content = $this->serializer->decode($file, $format);

          $current_timestamp = $this->time->getCurrentTime();
          $date = date('Y-m', $current_timestamp);
          $file_source = $this->tmp . '/library/' . $file_content['fid'][0]['value'] . '-' . $file_content['filename'][0]['value'];
          $dest_folder = 'public://' . $date;
          $destination = $dest_folder . '/' . $file_content['filename'][0]['value'];

          $uri = $this->copyFile($file_source, $destination, $dest_folder);

          if (!empty($uri)) {
            $file = File::create([
              'uri' => $uri,
              'uid' => $this->currentUser()->id(),
              'status' => is_array($file_content['status']) ? $file_content['status'][0]['value'] : $file_content['status'],
            ]);

            $file->save();
            $fid = $file->id();

            $file_path = $this->tmp . '/library/' . $document['media'];
            $real_path = $this->fileSystem->realpath($file_path);
            $file = file_get_contents($real_path);
            $file_content = $this->serializer->decode($file, $format);
          }

          if (!empty($file_content['file_name'])) {
            $media = Media::create([
              'bundle' => $file_content['bundle'],
              'name' => $file_content['file_name'],
              'tft_file' => [
                'target_id' => $fid,
              ],
              'tft_folder' => [
                'target_id' => $tids_relationships[$file_content['tft_folder'][0]['target_id']],
              ],
            ]);

            $media->save();
          }
        }
      }

      $route_parameters = [
        'group' => $new_training->id(),
      ];

      $this->messenger->addMessage($this->t('Imported training %training', [
        '%training' => Link::createFromRoute(
          $new_training->label(),
          'entity.group.canonical',
          $route_parameters
        )->toString(),
      ]));

      $form_state->setRedirect('entity.group.collection');
    }
    else {
      $this->messenger->addMessage($this->t('Incorrect archive structure.'), 'error');
    }
  }

  /**
   * Prepare temporary folder.
   */
  protected function prepareTemporary() {
    // Prepare folder.
    $this->fileSystem->deleteRecursive($this->tmp);
    return $this->prepareDirectory($this->tmp);
  }

  /**
   * Prepare imported files.
   */
  protected function getImportFiles() {
    // Prepare file validators.
    $extensions = ['opi'];
    $validators = [
      'file_validate_extensions' => $extensions,
    ];
    $files = [];
    $file = file_save_upload('training_opi', $validators, $this->tmp, NULL, FileSystemInterface::EXISTS_REPLACE);

    if (!empty($file[0])) {
      $path = $this->fileSystem->realpath($file[0]->getFileUri());

      $zip = new \ZipArchive();
      $result = $zip->open($path);

      if (!static::validate($zip)) {
        $this->messenger->addError($this->t('Unsafe files detected.'));
        $zip->close();
        $this->fileSystem->delete($path);
        $this->fileSystem->deleteRecursive($this->tmp);

        return [];
      }

      if ($result === TRUE) {
        $zip->extractTo($this->folder);
        $zip->close();
      }

      $this->fileSystem->delete($path);
      $files = scandir($this->folder);
    }
    return $files;
  }

  /**
   * Create training entity.
   *
   * @param array $training_content
   *   List of settings from imported file.
   *
   * @return \Drupal\group\Entity\Group
   *   A group entity.
   *
   * @throws \Exception
   */
  protected function importTraining(array $training_content): Group {
    $training = $this->buildEntityOptions($training_content, 'learning_path');
    $new_training = Group::create($training);

    if (!empty($training_content['field_learning_path_description'][0])) {
      $new_training->field_learning_path_description->format = $training_content['field_learning_path_description'][0]['format'];
    }

    // Create media for training image.
    $image = $this->importTrainingImage($training_content);

    if (!empty($image)) {
      $new_training->field_learning_path_media_image->target_id = $image->id();
    }

    $new_training->save();

    return $new_training;
  }

  /**
   * Create Media Image for training entity.
   *
   * @param array $training_content
   *   List of settings from imported file.
   *
   * @return \Drupal\media\Entity\Media|bool
   *   The created training image.
   *
   * @throws \Exception
   */
  protected function importTrainingImage(array $training_content) {
    if (!empty($training_content['field_learning_path_media_image']['media'])) {
      $file_info = $training_content['field_learning_path_media_image'];
      $media_image = $file_info['media'];

      $slide_file_path = $this->tmp . '/' . $file_info['media'][0]['target_id'] . '-' . $file_info['file_name'];
      $current_timestamp = $this->time->getCurrentTime();
      $date = date('Y-m', $current_timestamp);

      // Save image file.
      $uri = $this->copyFile($slide_file_path, 'public://' . $date . '/' . $file_info['file_name'], 'public://' . $date);
      if (!empty($uri)) {
        $file = File::create([
          'uri' => $uri,
          'uid' => $this->currentUser()->id(),
          'status' => !empty($file_info['status']) ? $file_info['status'] : 1,

        ]);

        $file->save();

        // Create Media Entity.
        $media_image[0]['target_id'] = $file->id();
        $media = Media::create([
          'bundle' => $file_info['bundle'],
          'name' => $file_info['file_name'],
          'field_media_image' => $media_image,
        ]);

        $media->save();

        return $media;
      }
    }

    return FALSE;
  }

  /**
   * Create Course entity.
   *
   * @param array $course_content
   *   List of settings from imported file.
   *
   * @return \Drupal\group\Entity\Group
   *   A group entity.
   *
   * @throws \Exception
   */
  protected function importCourse(array $course_content): Group {
    $course = $this->buildEntityOptions($course_content, 'opigno_course');
    $new_course = Group::create($course);

    if (!empty($course_content['field_course_description'][0])) {
      $new_course->field_course_description->format = $course_content['field_course_description'][0]['format'];
    }

    $new_course->save();

    return $new_course;
  }

  /**
   * Create Opigno Module entity.
   *
   * @param array $module_content
   *   List of settings from imported file.
   *
   * @return \Drupal\opigno_module\Entity\OpignoModule
   *   The imported module.
   *
   * @throws \Exception
   */
  protected function importModule(array $module_content): OpignoModule {
    $module = $this->buildEntityOptions($module_content, 'opigno_module');
    $new_module = OpignoModule::create($module);

    if (!empty($module_content['description'][0])) {
      $new_module->set('description', $module_content['description'][0]);
    }

    $new_module->save();

    return $new_module;
  }

  /**
   * Build Array of settings to create entity.
   *
   * @param array $source
   *   List of values from imported file.
   * @param string $type
   *   Type of entity.
   *
   * @return array
   *   The array of settings to create an entity.
   */
  protected function buildEntityOptions(array $source, string $type): array {
    $build = ['type' => $type];
    $fields = $this->fieldCollections($type);
    foreach ($fields as $field) {
      if (!empty($source[$field][0]['value'])) {
        $build[$field] = $source[$field][0]['value'];
      }
    }

    return $build;
  }

  /**
   * Build Array of settings to create entity.
   *
   * @param \Drupal\opigno_module\Entity\OpignoActivity $activity
   *   OpignoActivity entity.
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   OpignoModule entity.
   * @param array $activity_content
   *   List of values from imported file.
   */
  protected function setMaxScore(OpignoActivity $activity, OpignoModule $module, array $activity_content): void {
    // Set max score.
    if (!empty($activity_content['max_score'])) {
      unset($activity_content['max_score']['omr_id']);
      $max_score = $activity_content['max_score'];
      $max_score['parent_id'] = $module->id();
      $max_score['child_id'] = $activity->id();
      $max_score['parent_vid'] = $module->get('vid')->getValue()[0]['value'];
      $max_score['child_vid'] = $activity->get('vid')->getValue()[0]['value'];

      try {
        $this->database->insert('opigno_module_relationship')
          ->fields($max_score)
          ->execute();
      }
      catch (\Exception $e) {
        $this->logger('opigno_groups_migration')
          ->error($e->getMessage());
      }
    }
  }

  /**
   * List of fields.
   *
   * @param string $type
   *   Entity type.
   *
   * @return array
   *   The list of fields.
   */
  protected function fieldCollections(string $type): array {
    return match ($type) {
      'learning_path' => [
        'langcode',
        'label',
        'field_guided_navigation',
        'field_learning_path_enable_forum',
        'field_learning_path_published',
        'field_learning_path_visibility',
        'field_learning_path_duration',
        'field_requires_validation',
        'field_learning_path_description',
      ],
      'opigno_course' => [
        'langcode',
        'label',
        'badge_active',
        'badge_criteria',
        'field_guided_navigation',
        'badge_name',
        'badge_description',
        'field_course_description',
      ],
      'opigno_module' => [
        'langcode',
        'name',
        'status',
        'random_activity_score',
        'allow_resume',
        'backwards_navigation',
        'randomization',
        'random_activities',
        'takes',
        'show_attempt_stats',
        'keep_results',
        'hide_results',
        'badge_active',
        'badge_criteria',
        'badge_name',
        'badge_description',
        'description',
      ],
      default => [],
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function importActivity(array $activity_content, string $type): ?OpignoActivityInterface {
    opigno_module_prepare_directory_structure_for_import();
    return parent::importActivity($activity_content, $type);
  }

}
