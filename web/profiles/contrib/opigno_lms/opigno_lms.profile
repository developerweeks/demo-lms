<?php

/**
 * @file
 * Enables modules and site configuration for a opigno_lms site installation.
 */

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;

/**
 * Implements hook_preprocess_template().
 */
function opigno_lms_preprocess_install_page(&$variables) {
  $variables['site_version'] = '3.2.7';
}

/**
 * Implements hook_install_tasks().
 */
function opigno_lms_install_tasks(&$install_state) {
  $tasks = [
    'opigno_lms_dashboard_blocks_update' => [
      'display_name' => t('Dashboard blocks update'),
      'display' => FALSE,
      'type' => 'normal',
      'function' => 'opigno_dashboard_blocks_update',
    ],
    'opigno_lms_clear_cache' => [
      'display_name' => t('Clear cache'),
      'display' => FALSE,
      'type' => 'normal',
      'function' => 'opigno_lms_clear_cache',
    ],
  ];
  return $tasks;
}

/**
 * Implements hook_form_FORM_ID_alter() for install_configure_form().
 *
 * Allows the profile to alter the site configuration form.
 */
function opigno_lms_form_install_configure_form_alter(&$form, FormStateInterface $form_state) {
  $messenger = \Drupal::messenger();

  // Check if Tincan PHP library is installed.
  $has_library = class_exists('TinCan\Version');
  if (!$has_library) {
    $messenger->addWarning(Markup::create('Please install the TinCanPHP library using Composer, with the command: <em>"composer require opigno/tincan:^1.1</em>"'));
  }
  else {
    // Check if the LRS settings are set.
    $config = \Drupal::config('opigno_tincan_api.settings');
    $endpoint = $config->get('opigno_tincan_api_endpoint');
    $username = $config->get('opigno_tincan_api_username');
    $password = $config->get('opigno_tincan_api_password');

    if (empty($endpoint) || empty($username) || empty($password)) {
      $link_settings = Link::createFromRoute('settings page', 'opigno_tincan_api.settings_form');
      $url_settings = $link_settings->toString();
      $messenger->addWarning(t(
        'Please configure the LRS connection in the @setting_page.',
        [
          '@setting_page' => $url_settings,
        ]
      ));
      return;
    }
  }
}

/**
 * Check if new Opigno LMS release is available.
 *
 * @return bool
 *   TRUE if new release is available, FALSE otherwise.
 */
function opigno_lms_check_opigno_lms_updates() {
  // Get all available updates.
  $available = update_get_available();

  if (!isset($available['opigno_lms'])) {
    return FALSE;
  }

  $all_releases = array_keys($available['opigno_lms']['releases']);
  $last_release = $all_releases[0];
  $current_release = opigno_lms_get_current_opigno_lms_release();

  return $last_release !== $current_release;
}

/**
 * Get current Opigno LMS release version.
 *
 * @return string|bool
 *   The current Opigno LMS release version or FALSE if not Opigno LMS profile.
 */
function opigno_lms_get_current_opigno_lms_release() {
  $profile = \Drupal::installProfile();
  if ($profile !== 'opigno_lms') {
    return FALSE;
  }

  $info = \Drupal::service('extension.list.module')->getExtensionInfo($profile);

  return $info['version'] ?? FALSE;
}

/**
 * Clear cache on installation.
 */
function opigno_lms_clear_cache() {
  drupal_flush_all_caches();
}
