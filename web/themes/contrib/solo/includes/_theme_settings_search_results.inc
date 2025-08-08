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

// Check if the Search module is enabled.
if (\Drupal::moduleHandler()->moduleExists('search')) {

  $form['solo_settings']['settings_search'] = [
    '#type' => 'details',
    '#title' => t("<strong class='purple'>Search Results</strong> Settings - Solo Theme"),
    '#open' => TRUE,
  ];

  // Add checkboxes to enable/disable search filters.
  $form['solo_settings']['settings_search']['disable_search_filters'] = [
    '#type' => 'checkboxes',
    '#title' => t('Disable advanced search filters'),
    '#description' => t('Choose the filters to disable in the advanced search form. If all filters are disabled, the "Advanced" section will be hidden. By default, all filters are enabled.'),
    '#options' => [
      'disable_keywords' => t('Disable Keywords filter'),
      'disable_types' => t('Disable Types filter'),
      'disable_language' => t('Disable Language filter'),
    ],
    '#default_value' => theme_get_setting('disable_search_filters') ?? [],
  ];

  // Checkbox for enabling teaser mode.
  $form['solo_settings']['settings_search']['enable_teaser_mode'] = [
    '#type' => 'checkbox',
    '#title' => t('Use Teaser Mode for Search Results'),
    '#default_value' => theme_get_setting('enable_teaser_mode') ?? 0,
    '#description' => t('Enable this option to display search results using the teaser view mode.'),
  ];

  // List of content types for filtering search results conditional visibility.
  $form['solo_settings']['settings_search']['search_result_content_types'] = [
    '#type' => 'checkboxes',
    '#title' => t('Content Types to Display in Search Results'),
    '#options' => _solo_get_content_types(),
    '#default_value' => theme_get_setting('search_result_content_types') ?? [],
    '#description' => t('Allow site builder to select which content types can be searched across the site. The chosen content types will apply to both the search form on the page and the Drupal Core Search Blocks.'),
    '#states' => [
      // Show this field only if "Disable Types" is selected.
      'visible' => [
        ':input[name="disable_search_filters[disable_types]"]' => ['checked' => TRUE],
      ],
    ],
  ];

  // Add a submit handler to process the values.
  $form['#submit'][] = 'solo_settings_search_submit';
}
