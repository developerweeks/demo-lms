<?php

namespace Drupal\opigno_module\Traits;

use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides validation security functions.
 */
trait UnsafeFileValidation {

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected static EventDispatcherInterface $event_dispatcher;

  /**
   * The allowed extensions.
   *
   * The TinCan and scorm require js files to work correctly, so we allow js files,
   * but we don't allow other extensions.
   *
   * @var string[]
   *
   * @see \Drupal\Core\File\FileSystemInterface::INSECURE_EXTENSIONS
   */
  public static array $allowed_extensions = [
    'js',
  ];

  /**
   * Checks if there are any files start with ".".
   *
   * @param \ZipArchive $zip
   *   The .zip archive to be validated.
   *
   * @return bool
   *   Returns TRUE if pass validation, otherwise FALSE.
   */
  public static function validate(\ZipArchive $zip): bool {
    // Get all files list.
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $filename = $zip->getNameIndex($i);

      // This is a common security measure to prevent the extraction of hidden or system files,
      // which are often named that start with a period(.) or an underscore(_).
      if (preg_match('/(^[\._]|\/[\._])/', $filename) !== 0) {
        return false;
      }

      // Ensure that the file names within the ZIP archive do not contain path traversal
      // patterns like "../" could lead to files being extracted to unintended directories.
      if (str_contains($filename, '../') || str_contains($filename, '..\\')) {
        return false;
      }

      $basename = basename($filename);
      if(!$basename) {
        return FALSE;
      }
      $event = new FileUploadSanitizeNameEvent($basename, static::getAllowedExtensions());
      static::getEventDispatcher()->dispatch($event);
      if($event->isSecurityRename() === TRUE) {
        return FALSE;
      }
    }

    return TRUE;
  }
 
  /**
   * Gets the allowed extensions.
   *
   * @return string
   *  The allowed extensions.
   */
  private static function getAllowedExtensions(): string {
    return implode(' ', static::$allowed_extensions);
  }

  /**
   * Gets the event dispatcher service.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher service.
   */
  private static function getEventDispatcher(): EventDispatcherInterface {
    if(!isset(static::$event_dispatcher)) {
      static::$event_dispatcher = \Drupal::service('event_dispatcher');
    }
    return static::$event_dispatcher;
  }

}
