<?php

/**
 * @file
 * Solo Theme.
 *
 * Filename:     solo.theme
 * Website:      http://www.flashwebcenter.com
 * Description:  template
 * Author:       Alaa Haddad http://www.alaahaddad.com.
 */

// Breadcrumb region.
if (array_key_exists('breadcrumb', $updated_regions['mix'])) {

  $region = 'breadcrumb';
  $label = $updated_regions['mix'][$region];

  $form['solo_settings']["settings_{$region}"] = _generate_form_details($label);
  $form['solo_settings']["settings_{$region}"]["classes_{$region}"] = _generate_css_classes($region, $label);
  $form['solo_settings']["settings_{$region}"]['site_breadcrumb_pagetitle_off'] = [
    '#type' => 'checkbox',
    '#title' => t('Exclude the page title from the Breadcrumb.'),
    '#description' => t('By default, Drupal does not include the page title in the breadcrumb. Solo adds it as a custom feature, but you can disable it to revert to the default behavior. Enable this option to prevent the page title from being added to the breadcrumb trail. This setting applies to all content types, Views pages, taxonomy terms, and user profile pages.'),
    '#default_value' => theme_get_setting('site_breadcrumb_pagetitle_off'),
  ];

  $form['solo_settings']["settings_{$region}"]['site_breadcrumb_scroll'] = [
    '#type' => 'checkbox',
    '#title' => t('Tick this box to enable Breadcrumb scrolling (Prevent Wrap)'),
    '#default_value' => theme_get_setting('site_breadcrumb_scroll'),
  ];

  foreach ($attributes as $attribute_key => $attribute_label) {
    $form['solo_settings']["settings_{$region}"]["settings_{$region}_{$attribute_key}"] = _generate_form_element($region, $label, $attribute_key, $attribute_label);
  }

}
