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

// Highlighted region.
if (array_key_exists('highlighted', $updated_regions['mix'])) {

  $region = 'highlighted';
  $label = $updated_regions['mix'][$region];

  $form['solo_settings']["settings_{$region}"] = _generate_form_details($label);
  $form['solo_settings']["settings_{$region}"]["site_regions_highlighted_disable"] = _disable_region('highlighted');
  $form['solo_settings']["settings_{$region}"]["enable_aria_highlighted"] = [
    '#type' => 'checkbox',
    '#title' => t('Enable ARIA Landmark for Highlighted Region'),
    '#default_value' => theme_get_setting('enable_aria_highlighted') ?? 0,
    '#description' => t('Adds <code>role="region"</code> and <code>aria-label</code> to the highlighted region for improved accessibility. Enable only if this region consistently contains important, navigable content.'),
  ];
  $form['solo_settings']["settings_{$region}"]["classes_{$region}"] = _generate_css_classes($region, $label);

  foreach ($attributes as $attribute_key => $attribute_label) {
    $form['solo_settings']["settings_{$region}"]["settings_{$region}_{$attribute_key}"] = _generate_form_element($region, $label, $attribute_key, $attribute_label);
  }

}
