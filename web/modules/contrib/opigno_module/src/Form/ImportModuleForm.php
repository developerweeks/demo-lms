<?php

namespace Drupal\opigno_module\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\opigno_module\Controller\OpignoModuleController;
use Drupal\opigno_module\Entity\OpignoActivityInterface;
use Drupal\opigno_module\Entity\OpignoModule;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Defines the form for the module import.
 *
 * @package Drupal\opigno_module\Form
 */
class ImportModuleForm extends ImportBaseForm {

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
    return 'import_module_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $mode = NULL) {
    $form['module_opi'] = [
      '#title' => $this->t('Module'),
      '#type' => 'file',
      '#description' => $this->t('Here you can import module. Allowed extension: opi'),
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
    $uploaded = $files['module_opi'] ?? NULL;

    if (!$uploaded instanceof UploadedFile || !$uploaded->getClientOriginalName()) {
      // Only need to validate if the field actually has a file.
      $form_state->setError(
        $form['module_opi'],
        $this->t("The file was not uploaded.")
      );
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Prepare folder.
    if (!$this->prepareTemporary()) {
      $this->messenger->addError($this->t('Failed to create directory.'));
      return;
    }

    // Prepare file validators.
    $extensions = ['opi'];
    $validators = [
      'file_validate_extensions' => $extensions,
    ];

    $files = [];
    $file = file_save_upload('module_opi', $validators, $this->tmp, NULL, FileSystemInterface::EXISTS_REPLACE);
    if (!empty($file[0])) {
      $path = $this->fileSystem->realpath($file[0]->getFileUri());

      $zip = new \ZipArchive();
      $result = $zip->open($path);

      if (!static::validate($zip)) {
        $this->messenger->addError($this->t('Unsafe files detected.'));
        $zip->close();
        $this->fileSystem->delete($path);
        $this->fileSystem->deleteRecursive($this->tmp);
        return;
      }

      if ($result === TRUE) {
        $zip->extractTo($this->folder);
        $zip->close();
      }

      $this->fileSystem->delete($path);
      $files = scandir($this->folder);
    }
    if (in_array('list_of_files.json', $files)) {
      $file_path = $this->tmp . '/list_of_files.json';
      $real_path = $this->fileSystem->realpath($file_path);
      $file = file_get_contents($real_path);

      $format = 'json';
      try {
        $content = $this->serializer->decode($file, $format);
      }
      catch (\Exception $e) {
        $this->messenger->addMessage($e->getMessage(), 'error');
        return;
      }

      if (empty($content['module'])) {
        $this->messenger->addError($this->t('Incorrect archive structure.'));
        return;
      }

      $file_path = $this->tmp . '/' . $content['module'];
      $real_path = $this->fileSystem->realpath($file_path);
      $file = file_get_contents($real_path);
      $module_content_array = $this->serializer->decode($file, $format);
      $module_content = reset($module_content_array);

      $new_module = OpignoModule::create([
        'type' => 'opigno_module',
        'langcode' => $module_content['langcode'][0]['value'],
        'name' => $module_content['name'][0]['value'],
        'status' => $module_content['status'][0]['value'],
        'random_activity_score' => $module_content['random_activity_score'][0]['value'],
        'allow_resume' => $module_content['allow_resume'][0]['value'],
        'backwards_navigation' => $module_content['backwards_navigation'][0]['value'],
        'randomization' => $module_content['randomization'][0]['value'],
        'random_activities' => $module_content['random_activities'][0]['value'],
        'takes' => $module_content['takes'][0]['value'],
        'show_attempt_stats' => $module_content['show_attempt_stats'][0]['value'],
        'keep_results' => $module_content['keep_results'][0]['value'] ?? NULL,
        'hide_results' => $module_content['hide_results'][0]['value'] ?? NULL,
        'badge_active' => $module_content['badge_active'][0]['value'] ?? NULL,
        'badge_criteria' => $module_content['badge_criteria'][0]['value'] ?? NULL,
      ]);

      if ($module_content['badge_active'][0]['value'] == 1) {
        $new_module->badge_name->value = $module_content['badge_name'][0]['value'];
        $new_module->badge_description->value = $module_content['badge_description'][0]['value'];
      }

      if (!empty($module_content['description'][0]['value'])) {
        $new_module->set('description', $module_content['description'][0]);
      }

      $new_module->save();

      $add_activities = [];

      opigno_module_prepare_directory_structure_for_import();

      foreach ($content['activities'] as $activity_file_name) {
        $file_path = $this->tmp . '/' . $activity_file_name;
        $real_path = $this->fileSystem->realpath($file_path);
        $file = file_get_contents($real_path);

        try {
          $activity_content_array = $this->serializer->decode($file, $format);
          $activity_content = reset($activity_content_array);
        }
        catch (\Exception $e) {
          $this->logger('opigno_module')->error($e->getMessage());
          continue;
        }

        $type = $activity_content['type'][0]['target_id'] ?? '';
        $new_activity = $this->importActivity($activity_content, $type);
        if ($new_activity instanceof OpignoActivityInterface) {
          $add_activities[] = $new_activity;
        }
      }

      $this->moduleService->activitiesToModule($add_activities, $new_module);
      $route_parameters = [
        'opigno_module' => $new_module->id(),
      ];

      $this->messenger->addMessage($this->t('Imported module %module', [
        '%module' => Link::createFromRoute($new_module->label(), 'entity.opigno_module.canonical', $route_parameters)->toString(),
      ]));

      $form_state->setRedirect('entity.opigno_module.collection');
    }
    $this->fileSystem->deleteRecursive($this->tmp);
  }

}
