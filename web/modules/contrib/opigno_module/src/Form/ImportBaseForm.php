<?php

namespace Drupal\opigno_module\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\file\Entity\File;
use Drupal\h5p\Entity\H5PContent;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5peditor\H5PEditor\H5PEditorUtilities;
use Drupal\media\Entity\Media;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drupal\opigno_module\Entity\OpignoActivityInterface;
use Drupal\opigno_module\H5PImportClasses\H5PEditorAjaxImport;
use Drupal\opigno_module\H5PImportClasses\H5PStorageImport;
use Drupal\opigno_module\Traits\FileSecurity;
use Drupal\opigno_module\Traits\UnsafeFileValidation;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Defines the best class for Opigno LP entities import.
 *
 * @package Drupal\opigno_module\Form
 */
abstract class ImportBaseForm extends FormBase {

  use UnsafeFileValidation;
  use FileSecurity;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected SerializerInterface $serializer;

  /**
   * The H5P config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Temporary folder uri.
   *
   * @var string
   */
  protected string $tmp = 'public://opigno-import';

  /**
   * Path to the temporary folder.
   *
   * @var string
   */
  protected string $folder = DRUPAL_ROOT . '/sites/default/files/opigno-import';

  /**
   * ImportActivityForm constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    FileSystemInterface $file_system,
    TimeInterface $time,
    Connection $database,
    SerializerInterface $serializer,
    ConfigFactoryInterface $config_factory,
    MessengerInterface $messenger
  ) {
    $this->fileSystem = $file_system;
    $this->time = $time;
    $this->database = $database;
    $this->serializer = $serializer;
    $this->config = $config_factory->get('h5p.settings');
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('datetime.time'),
      $container->get('database'),
      $container->get('serializer'),
      $container->get('config.factory'),
      $container->get('messenger')
    );
  }

  /**
   * Creates Opigno Activity entity from the imported data.
   *
   * @param array $activity_content
   *   List of settings from imported file.
   * @param string $type
   *   The activity type to be created.
   *
   * @return \Drupal\opigno_module\Entity\OpignoActivityInterface|null
   *   The created Opigno activity entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function importActivity(array $activity_content, string $type): ?OpignoActivityInterface {
    $new_activity = OpignoActivity::create([
      'type' => $type,
    ]);

    $new_activity->setName($activity_content['name'][0]['value']);
    $new_activity->set('langcode', $activity_content['langcode'][0]['value']);
    $new_activity->set('status', $activity_content['status'][0]['value']);

    switch ($type) {
      case 'opigno_long_answer':
        $new_activity->set('opigno_body', [
          'value' => $activity_content['opigno_body'][0]['value'] ?? NULL,
          'format' => $activity_content['opigno_body'][0]['format'] ?? 'plain_text',
        ]);

        $eval_method = $activity_content['opigno_evaluation_method'][0]['value'] ?? 0;
        $new_activity->set('opigno_evaluation_method', $eval_method);
        break;

      case 'opigno_file_upload':
        $new_activity->set('opigno_body', [
          'value' => $activity_content['opigno_body'][0]['value'] ?? '',
          'format' => $activity_content['opigno_body'][0]['format'] ?? 'plain_text',
        ]);
        $eval_method = $activity_content['opigno_evaluation_method'][0]['value'] ?? 0;
        $new_activity->set('opigno_evaluation_method', $eval_method);
        $new_activity->set('opigno_allowed_extension', $activity_content['opigno_allowed_extension'][0]['value']);
        break;

      case 'opigno_scorm':
      case 'opigno_tincan':
        $this->importExternalPackageActivity($activity_content, $new_activity, $type);
        break;

      case 'opigno_slide':
        $this->importSlideActivity($activity_content, $new_activity);
        break;

      case 'opigno_video':
        $this->importVideoActivity($activity_content, $new_activity);
        break;

      case 'opigno_h5p':
        $this->importH5pActivity($activity_content, $new_activity);
        break;

      default:
        return NULL;
    }

    $new_activity->save();

    return $new_activity;
  }

  /**
   * Imports SCORM/Tincan activity data.
   *
   * @param array $activity_content
   *   The activity content data to be imported.
   * @param \Drupal\opigno_module\Entity\OpignoActivityInterface $new_activity
   *   Created activity entity to add info to.
   * @param string $type
   *   Activity type ("opigno_scorm" or "opigno_tincan").
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function importExternalPackageActivity(array $activity_content, OpignoActivityInterface $new_activity, string $type): void {
    switch ($type) {
      case 'opigno_scorm':
        $dest_folder = 'opigno_scorm';
        $field_name = 'opigno_scorm_package';
        break;

      case 'opigno_tincan':
        $dest_folder = 'opigno_tincan';
        $field_name = 'opigno_tincan_package';
        break;

      default:
        // Do nothing if the activity type differs from external package.
        return;
    }

    foreach ($activity_content['files'] as $file_key => $file_content) {
      $tincan_file_path = $this->tmp . '/' . $file_key;
      $uri = $this->copyFile(
        $tincan_file_path,
        'public://' . $dest_folder . '/' . $file_content['file_name'],
        'public://' . $dest_folder);

      if (!empty($uri)) {
        $file = File::create([
          'uri' => $uri,
          'uid' => $this->currentUser()->id(),
          'status' => is_array($file_content['status']) ? $file_content['status'][0]['value'] : $file_content['status'],
        ]);
        $file->save();

        $new_activity->set($field_name, [
          'target_id' => $file->id(),
          'display' => 1,
        ]);
      }
    }
  }

  /**
   * Imports slide activity data.
   *
   * @param array $activity_content
   *   The activity content data to be imported.
   * @param \Drupal\opigno_module\Entity\OpignoActivityInterface $new_activity
   *   Created activity entity to add info to.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function importSlideActivity(array $activity_content, OpignoActivityInterface $new_activity): void {
    $new_activity->set('opigno_body', [
      'value' => $activity_content['opigno_body'][0]['value'] ?? '',
      'format' => $activity_content['opigno_body'][0]['format'] ?? 'plain_text',
    ]);

    if (!empty($activity_content['files']) && is_array($activity_content['files'])) {
      foreach ($activity_content['files'] as $file_key => $file_content) {
        $slide_file_path = $this->tmp . '/' . $file_key;
        $current_timestamp = $this->time->getCurrentTime();
        $date = date('Y-m', $current_timestamp);

        $uri = $this->copyFile(
          $slide_file_path,
          'public://' . $date . '/' . $file_content['file_name'],
          'public://' . $date
        );

        if (empty($uri)) {
          continue;
        }

        $file = File::create([
          'uri' => $uri,
          'uid' => $this->currentUser()->id(),
          'status' => is_array($file_content['status']) ? $file_content['status'][0]['value'] : $file_content['status'],
        ]);
        $file->save();

        $media = Media::create([
          'bundle' => $file_content['bundle'],
          'name' => $file_content['file_name'],
          'field_media_file' => [
            'target_id' => $file->id(),
          ],
        ]);

        $media->save();

        $new_activity->set('opigno_slide_pdf', [
          'target_id' => $media->id(),
          'display' => 1,
        ]);
      }
    }
  }

  /**
   * Imports video activity data.
   *
   * @param array $activity_content
   *   The activity content data to be imported.
   * @param \Drupal\opigno_module\Entity\OpignoActivityInterface $new_activity
   *   Created activity entity to add info to.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function importVideoActivity(array $activity_content, OpignoActivityInterface $new_activity): void {
    foreach ($activity_content['files'] as $file_key => $file_content) {
      $video_file_path = $this->tmp . '/' . $file_key;
      $current_timestamp = $this->time->getCurrentTime();
      $date = date('Y-m', $current_timestamp);

      $uri = $this->copyFile(
        $video_file_path,
        'public://video-thumbnails/' . $date . '/' . $file_content['file_name'],
        'public://video-thumbnails/' . $date
      );

      if (!empty($uri)) {
        $file = File::create([
          'uri' => $uri,
          'uid' => $this->currentUser()->id(),
          'status' => $file_content['status'],
        ]);
        $file->save();

        $new_activity->set('field_video', ['target_id' => $file->id()]);
      }
    }
  }

  /**
   * Imports h5p activity data.
   *
   * @param array $activity_content
   *   The activity content data to be imported.
   * @param \Drupal\opigno_module\Entity\OpignoActivityInterface $new_activity
   *   Created activity entity to add info to.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function importH5pActivity(array $activity_content, OpignoActivityInterface $new_activity): void {
    $h5p_content_id = $activity_content['opigno_h5p'][0]['h5p_content_id'];
    $file = $this->folder . "/interactive-content-{$h5p_content_id}.h5p";
    $interface = H5PDrupal::getInstance();

    if (!file_exists($file)) {
      return;
    }

    $dir = $this->fileSystem->realpath($this->tmp . '/h5p');
    $interface->getUploadedH5pFolderPath($dir);
    $interface->getUploadedH5pPath($file);

    $editor = H5PEditorUtilities::getInstance();
    $h5pEditorAjax = new H5PEditorAjaxImport($editor->ajax->core, $editor, $editor->ajax->storage);

    if (!$h5pEditorAjax->isValidPackage()) {
      return;
    }

    // Add new libraries from file package.
    $storage = new H5PStorageImport($h5pEditorAjax->core->h5pF, $h5pEditorAjax->core);

    // Serialize metadata array in libraries.
    if (!empty($storage->h5pC->librariesJsonData)) {
      foreach ($storage->h5pC->librariesJsonData as &$library) {
        if (array_key_exists('metadataSettings', $library) && is_array($library['metadataSettings'])) {
          $metadataSettings = serialize($library['metadataSettings']);
          $library['metadataSettings'] = $metadataSettings;
        }
      }
    }

    $storage->saveLibraries();

    $h5p_json = $dir . '/h5p.json';
    $real_path = $this->fileSystem->realpath($h5p_json);
    $h5p_json = file_get_contents($real_path);

    $format = 'json';
    $h5p_json = $this->serializer->decode($h5p_json, $format);
    $dependencies = $h5p_json['preloadedDependencies'];

    // Get ID of main library.
    foreach ($h5p_json['preloadedDependencies'] as $dependency) {
      if ($dependency['machineName'] == $h5p_json['mainLibrary']) {
        $h5p_json['majorVersion'] = $dependency['majorVersion'];
        $h5p_json['minorVersion'] = $dependency['minorVersion'];
      }
    }

    $query = $this->database->select('h5p_libraries', 'h_l');
    $query->condition('machine_name', $h5p_json['mainLibrary'], '=');
    $query->condition('major_version', $h5p_json['majorVersion'], '=');
    $query->condition('minor_version', $h5p_json['minorVersion'], '=');
    $query->fields('h_l', ['library_id']);
    $query->orderBy('patch_version', 'DESC');
    $main_library_id = $query->execute()->fetchField();

    if (!$main_library_id) {
      $query = $this->database->select('h5p_libraries', 'h_l');
      $query->condition('machine_name', $h5p_json['mainLibrary'], '=');
      $query->fields('h_l', ['library_id']);
      $query->orderBy('major_version', 'DESC');
      $query->orderBy('minor_version', 'DESC');
      $query->orderBy('patch_version', 'DESC');
      $main_library_id = $query->execute()->fetchField();
    }

    $content_json = $dir . '/content/content.json';
    $real_path = $this->fileSystem->realpath($content_json);
    $content_json = file_get_contents($real_path);

    $fields = [
      'library_id' => $main_library_id,
      'title' => $h5p_json['title'],
      'parameters' => $content_json,
      'filtered_parameters' => $content_json,
      'disabled_features' => 0,
      'authors' => '[]',
      'changes' => '[]',
      'license' => 'U',
    ];

    $h5p_content = H5PContent::create($fields);
    $h5p_content->save();
    $new_activity->set('opigno_h5p', $h5p_content->id());

    $h5p_dest_path = $this->config->get('h5p_default_path');
    $h5p_dest_path = $h5p_dest_path ?: 'h5p';

    $dest_folder = DRUPAL_ROOT . '/sites/default/files/' . $h5p_dest_path . '/content/' . $h5p_content->id();
    $source_folder = DRUPAL_ROOT . '/sites/default/files/opigno-import/h5p/content';
    $this->fileSystem->prepareDirectory(
      $dest_folder,
      FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY
    );
    $this->fileSystem->delete($dest_folder . '/content.json');
    $symfony_fs = new Filesystem();
    $symfony_fs->mirror($source_folder, $dest_folder);

    // Clean up.
    $h5_file = $h5pEditorAjax->core->h5pF->getUploadedH5pFolderPath();

    if (file_exists($h5_file)) {
      $h5pEditorAjax->storage->removeTemporarilySavedFiles($h5_file);
    }

    foreach ($dependencies as $dependency_key => $dependency) {
      $query = $this->database->select('h5p_libraries', 'h_l');
      $query->condition('machine_name', $dependency['machineName'], '=');
      $query->condition('major_version', $dependency['majorVersion'], '=');
      $query->condition('minor_version', $dependency['minorVersion'], '=');
      $query->fields('h_l', ['library_id']);
      $query->orderBy('patch_version', 'DESC');
      $library_id = $query->execute()->fetchField();

      if (!$library_id) {
        $query = $this->database->select('h5p_libraries', 'h_l');
        $query->condition('machine_name', $dependency['machineName'], '=');
        $query->fields('h_l', ['library_id']);
        $query->orderBy('major_version', 'DESC');
        $query->orderBy('minor_version', 'DESC');
        $query->orderBy('patch_version', 'DESC');
        $library_id = $query->execute()->fetchField();
      }

      if ($h5p_json['mainLibrary'] == $dependency['machineName']) {
        $main_library_values = [
          'content_id' => $h5p_content->id(),
          'library_id' => $library_id,
          'dependency_type' => 'preloaded',
          'drop_css' => 0,
          'weight' => count($dependencies) + 1,
        ];

        continue;
      }

      if ($library_id) {
        $this->database->insert('h5p_content_libraries')
          ->fields([
            'content_id',
            'library_id',
            'dependency_type',
            'drop_css',
            'weight',
          ])
          ->values([
            'content_id' => $h5p_content->id(),
            'library_id' => $library_id,
            'dependency_type' => 'preloaded',
            'drop_css' => 0,
            'weight' => $dependency_key + 1,
          ])
          ->execute();
      }
    }

    if (!empty($main_library_values)) {
      $this->database->insert('h5p_content_libraries')
        ->fields([
          'content_id',
          'library_id',
          'dependency_type',
          'drop_css',
          'weight',
        ])
        ->values($main_library_values)
        ->execute();
    }
  }

  /**
   * Prepare directories and copy needed files.
   *
   * @param string $file_source
   *   Source file.
   * @param string $destination
   *   Destination file.
   * @param string $dest_folder
   *   Destination folder.
   *
   * @return string
   *   The uri of the copied files directory.
   */
  protected function copyFile(string $file_source, string $destination, string $dest_folder): string {
    $this->fileSystem->prepareDirectory(
      $dest_folder,
      FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY
    );
    $uri = '';
    try {
      $uri = $this->fileSystem->copy($file_source, $destination, FileSystemInterface::EXISTS_RENAME);
    }
    catch (\Exception $e) {
      $this->logger('opigno_module')->error($e->getMessage());
    }

    return $uri;
  }

  /**
   * Checks that the directory exists and is writable.
   *
   * Public directories will be protected by adding an .htaccess.
   *
   * @param string $directory
   *   A string reference containing the name of a directory path or URI.
   *
   * @return bool
   *   TRUE if the directory exists (or was created), is writable and is
   *   protected (if it is public). FALSE otherwise.
   */
  protected function prepareDirectory(string $directory): bool {
    if (!$this->fileSystem->prepareDirectory(
      $directory,
      FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY)
    ) {
      return FALSE;
    }

    return !str_starts_with($directory, 'public://') || static::writeHtaccess($directory);
  }

}
