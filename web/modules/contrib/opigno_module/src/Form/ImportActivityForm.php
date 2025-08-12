<?php

namespace Drupal\opigno_module\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\opigno_module\Entity\OpignoActivityInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Defines the form for the activity import.
 *
 * @package Drupal\opigno_module\Form
 */
class ImportActivityForm extends ImportBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'import_activity_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $mode = NULL) {
    $is_ppt = $mode && $mode == 'ppt';
    if ($is_ppt) {
      $form_state->set('mode', $mode);
    }

    $form['activity_opi'] = [
      '#title' => $this->t('Activity'),
      '#type' => 'file',
      '#description' => $this->t('Here you can import activity. Allowed extension: opi'),
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
    $uploaded = $files['activity_opi'] ?? NULL;

    if (!$uploaded instanceof UploadedFile || !$uploaded->getClientOriginalName()) {
      // Only need to validate if the field actually has a file.
      $form_state->setError(
        $form['activity_opi'],
        $this->t("The file was not uploaded.")
      );
    }
  }

  /**
   * Prepare temporary folder.
   */
  protected function prepareTemporary(): bool {
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
      $this->messenger()->addMessage($this->t('Failed to create directory.'), 'error');
      return;
    }

    // Prepare file validators.
    $extensions = ['opi'];
    $validators = [
      'file_validate_extensions' => $extensions,
    ];
    $files = [];
    $file = file_save_upload('activity_opi', $validators, $this->tmp, NULL, FileSystemInterface::EXISTS_REPLACE);

    if (!empty($file[0])) {
      $path = $this->fileSystem->realpath($file[0]->getFileUri());

      $zip = new \ZipArchive();
      $result = $zip->open($path);

      if (!static::validate($zip)) {
        $this->messenger->addMessage($this->t('Unsafe files detected.'), 'error');
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

    if (in_array('export-opigno_activity.json', $files)) {
      $file_path = $this->tmp . '/export-opigno_activity.json';
      $real_path = $this->fileSystem->realpath($file_path);
      $file = file_get_contents($real_path);

      $format = 'json';
      try {
        $activity_content_array = $this->serializer->decode($file, $format);
      }
      catch (\Exception $e) {
        $this->messenger->addMessage($e->getMessage(), 'error');
        return;
      }
      $content = reset($activity_content_array);

      if (empty($content['bundle'])) {
        $this->messenger->addError($this->t('Incorrect archive structure.'));
        return;
      }

      $type = $content['bundle'];
      $new_activity = $this->importActivity($content, $type);

      if (!$new_activity instanceof OpignoActivityInterface) {
        $this->logger('opigno_module')->error($this->t('Activity was not imported, check initial data.'));
        $this->messenger->addError($this->t('Activity was not imported, check initial data.'));
        return;
      }

      $this->messenger->addMessage($this->t('Imported activity %activity', [
        '%activity' => Link::createFromRoute(
          $new_activity->label(),
          'entity.opigno_activity.canonical',
          ['opigno_activity' => $new_activity->id()]
        )->toString(),
      ]));

      $form_state->setRedirect('entity.opigno_activity.collection');
    }
  }

}
