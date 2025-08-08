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

if ($updated_regions['main']) {

  $region_label = implode(", ", $updated_regions['main']);

  $form['solo_settings']["settings_main_container"] = [
    '#type' => 'details',
    '#title' => t("<strong class='status'>Main Containers</strong> Settings - Solo Theme"),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
    '#description'   => t("<p>The settings for @region_label.</p>", ['@region_label' => $region_label]),
  ];

  foreach ($updated_regions['main'] as $region => $label) {

    if ($region == 'container') {
      $region = 'main_container';
      $layout_region = 'main';
      $form['solo_settings']["settings_main_container"]["main_2col"] = _create_region_layout_radio_two($layout_region, $variables);
      $form['solo_settings']["settings_main_container"]["main_3col"] = _create_region_layout_radio_three($layout_region);
      $form['solo_settings']['settings_main_container']['enable_per_type_layout_main'] = [
        '#type' => 'checkbox',
        '#title' => t('Enable per-content-type layouts for Main region'),
        '#default_value' => theme_get_setting('enable_per_type_layout_main'),
      ];

      foreach ($content_types as $type_id => $type_label) {
        $form['solo_settings']['settings_main_container']['solo_layout_main_per_type'][$type_id] = [
          '#type' => 'details',
          '#title' => t('Layout for @label', ['@label' => $type_label]),
          '#open' => FALSE,
          '#states' => [
            'visible' => [
              ':input[name="enable_per_type_layout_main"]' => ['checked' => TRUE],
            ],
          ],
        ];

        // 2-column override.
        $layout_2col = _create_region_layout_radio_two($layout_region, $variables);
        $layout_2col['#title'] = t('Two-column layout (override)');
        $layout_2col['#default_value'] = _solo_get_layout_default($layout_region, '2col', $type_id);
        $form['solo_settings']['settings_main_container']['solo_layout_main_per_type'][$type_id]["solo_layout_main_2col_$type_id"] = $layout_2col;

        // 3-column override.
        $layout_3col = _create_region_layout_radio_three($layout_region);
        $layout_3col['#title'] = t('Three-column layout (override)');
        $layout_3col['#default_value'] = _solo_get_layout_default($layout_region, '3col', $type_id);
        $form['solo_settings']['settings_main_container']['solo_layout_main_per_type'][$type_id]["solo_layout_main_3col_$type_id"] = $layout_3col;
      }

      $form['solo_settings']["settings_main_container"]["site_regions_main_rounded"] = _rounded_region($layout_region);
      $form['solo_settings']['settings_main_container']['site_mobile_layout_order'] = [
        '#type' => 'select',
        '#title' => t('Select the layout order for mobile view'),
        '#description' => t('<p>This setting allows you to choose the order in which the main content and sidebars are displayed on smaller screens.</p>'),
        '#options' => _get_mobile_layout_options(),
        '#default_value' => theme_get_setting('site_mobile_layout_order'),
      ];
      $form['solo_settings']["settings_main_container"]["site_regions_main_border"] = _border_region($layout_region);

      $form['solo_settings']["settings_main_container"]['skip_main_content'] = [
        '#type' => 'checkbox',
        '#title' => t('Enable skip to main content link'),
        '#default_value' => theme_get_setting('skip_main_content'),
      ];

      $form['solo_settings']["settings_main_container"]["classes_{$region}"] = _generate_css_classes($region, $label);

      foreach ($attributes as $attribute_key => $attribute_label) {
        $form['solo_settings']["settings_main_container"]["settings_{$region}_{$attribute_key}"] = _generate_form_element($region, $label, $attribute_key, $attribute_label);
      }
    }
    else {
      $form['solo_settings']['settings_main_container']["settings_{$region}"] = _generate_form_details($label);
      foreach ($attributes as $attribute_key => $attribute_label) {
        $form['solo_settings']['settings_main_container']["settings_{$region}"]["classes_{$region}"] = _generate_css_classes($region, $label);
        $form['solo_settings']['settings_main_container']["settings_{$region}"]["align_{$region}"] = [
          '#title' => t("Region Block Alignment Option"),
          '#type' => 'select',
          '#options' => _alignment_region($region),
          '#default_value' => theme_get_setting("align_{$region}") ?? 'region-left',
          '#description' => t("<p>By default, block content is left-aligned. This feature allows theme users to align blocks to the center or right within specific regions through region-level configuration. (Large screen only).</p>"),
        ];
        $form['solo_settings']['settings_main_container']["settings_{$region}"]["settings_{$region}_{$attribute_key}"] = _generate_form_element($region, $label, $attribute_key, $attribute_label);
      }
    }
  }

}
