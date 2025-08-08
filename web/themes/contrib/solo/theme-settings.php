<?php

/**
 * @file
 * Solo Theme.
 *
 * Filename:     theme-settings
 * Website:      http://www.flashwebcenter.com
 * Description:  template
 * Author:       Alaa Haddad http://www.alaahaddad.com.
 */

use Drupal\Core\Config\Config;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function solo_form_system_theme_settings_alter(&$form, FormStateInterface $form_state) {
  $form['#validate'][] = 'solo_theme_settings_validate';
  $form['logo']['#weight'] = 97;
  $form['favicon']['#open'] = FALSE;
  $form['favicon']['#weight'] = 98;
  $form['theme_settings']['#open'] = FALSE;
  $form['theme_settings']['#weight'] = 99;

  $form['#attached']['library'][] = 'solo/solo-form-theme-settings';
  $d_s = date('j  F,  Y');
  $d_m = date('D F d, o');
  $d_l = date('g:i A T, D F d, o');
  $updated_regions = _get_updated_regions();
  $counts = _count_regions();
  $attributes = _get_region_attributes();

  $layout_region_override_toggles = [
    'enable_per_type_layout_top' => 0,
    'enable_per_type_layout_main' => 0,
    'enable_per_type_layout_bottom' => 0,
    'enable_per_type_layout_footer' => 0,
  ];

  foreach ($layout_region_override_toggles as $setting_key => $default_value) {
    if (!isset($form_state->getValues()[$setting_key])) {
      $form_state->setValue($setting_key, $default_value);
    }
  }

  // Theme settings files.
  require_once __DIR__ . '/includes/_theme_settings_blueprint.inc';
  require_once __DIR__ . '/includes/_theme_settings_global_misc.inc';
  require_once __DIR__ . '/includes/_theme_settings_libraries_fonts.inc';
  require_once __DIR__ . '/includes/_theme_settings_search_results.inc';
  require_once __DIR__ . '/includes/_theme_settings_predefined_themes.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_page_wrapper.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_highlighted.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_popup_login_block.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_fixed_search_block.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_header.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_primary_sidebar_menu.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_primary_menu.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_welcome_text.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_top.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_system_messages.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_page_title.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_breadcrumb.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_main.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_bottom.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_footer.inc';
  require_once __DIR__ . '/includes/_theme_settings_layout_footer_menu.inc';
  require_once __DIR__ . '/includes/_theme_settings_sm_icons.inc';
  require_once __DIR__ . '/includes/_theme_settings_credit_copyright.inc';

  $form['#submit'][] = '_solo_theme_settings_submit';
}

/**
 * Validation handler for the Solo system_theme_settings form.
 */
function solo_theme_settings_validate($form, FormStateInterface $form_state) {
  $url = $form_state->getValue('footer_link');
  $text = $form_state->getValue('footer_link_text');

  if ($url !== '' && !UrlHelper::isValid($url, TRUE)) {
    $form_state->setErrorByName('footer_link', t('The URL %url is not valid.', [
      '%url' => $url,
    ]));
  }

  // Validate that text is provided if URL is provided.
  if (!empty($url) && empty($text)) {
    $form_state->setErrorByName('footer_link_text', t('You must enter link text if you provide a URL.'));
  }
}

/**
 * Sets or clears a layout config value based on global comparison.
 *
 * @param \Drupal\Core\Config\Config $config
 *   The editable theme config object.
 * @param string $key
 *   The per-content-type config key (e.g. 'solo_layout_main_2col_article').
 * @param mixed $value
 *   The user-submitted value.
 * @param mixed $global
 *   The global fallback value.
 */
function _solo_set_or_clear_layout(Config $config, $key, $value, $global) {
  if ($value !== NULL && $value !== $global) {
    $config->set($key, $value);
  }
  else {
    $config->clear($key);
  }
}

/**
 * Form submit handler for the Solo theme settings form.
 *
 * Saves layout configuration values for grouped regions (`top`, `main`,
 * `bottom`, `footer`),
 * including global layout selections and optional per-content-type overrides
 * for 2-column, 3-column, and 4-column layouts.
 *
 *
 * If per-content-type layout overrides are enabled for a region, the handler
 * compares each value to the global default and saves only the differing ones.
 * If overrides are disabled, any previously stored overrides for that region
 * and content type are cleared.
 *
 * This ensures that all layout-related settings remain clean and fallback to
 * the global configuration when no override is present.
 *
 * @param array $form
 *   The complete form structure.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The current state of the submitted form.
 *
 * @see _solo_set_or_clear_layout()
 */
function _solo_theme_settings_submit($form, FormStateInterface $form_state) {
  // Get the theme whose settings are being altered.
  $theme = $form_state->getBuildInfo()['args'][0];
  $config = \Drupal::configFactory()->getEditable("$theme.settings");

  $content_types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
  $regions = ['top', 'main', 'bottom', 'footer'];

  // Handle per-content-type width cleanup when disabled.
  $custom_widths_enabled = (bool) $form_state->getValue('enable_custom_widths', FALSE);
  $config->set('enable_custom_widths', $custom_widths_enabled);

  if (!$custom_widths_enabled) {
    foreach ($content_types as $type) {
      $key = "site_width_{$type->id()}";
      if ($config->get($key) !== NULL) {
        $config->clear($key);
      }
    }
  }

  foreach ($regions as $region) {
    $enable_key = "enable_per_type_layout_$region";
    $enabled = (bool) $form_state->getValue($enable_key, FALSE);
    $config->set($enable_key, $enabled);

    // Get global layout values for this region.
    $global_2col = $form_state->getValue("{$region}_2col");
    $global_3col = $form_state->getValue("{$region}_3col");
    $global_4col = $form_state->getValue("{$region}_4col");

    foreach ($content_types as $type_id => $type) {
      $key_2col = "solo_layout_{$region}_2col_$type_id";
      $key_3col = "solo_layout_{$region}_3col_$type_id";
      $key_4col = "solo_layout_{$region}_4col_$type_id";
      if ($enabled) {
        foreach ([2, 3, 4] as $col) {
          $key = "solo_layout_{$region}_{$col}col_$type_id";
          $val = $form_state->getValue($key);
          $global = $form_state->getValue("{$region}_{$col}col");
          _solo_set_or_clear_layout($config, $key, $val, $global);
        }
      }
      else {
        // On disable, always clear overrides (they will fallback to global).
        if ($config->get($key_2col) !== NULL) {
          $config->clear($key_2col);
        }
        if ($config->get($key_3col) !== NULL) {
          $config->clear($key_3col);
        }
        if ($config->get($key_4col) !== NULL) {
          $config->clear($key_4col);
        }
      }
    }
  }
  \Drupal::configFactory()->reset($theme . '.settings');
  $config->save();
}
