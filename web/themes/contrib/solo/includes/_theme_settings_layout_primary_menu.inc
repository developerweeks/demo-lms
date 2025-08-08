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

// Primary Menu region.
if (array_key_exists('primary_menu', $updated_regions['mix'])) {

  $region = 'primary_menu';
  $label = $updated_regions['mix'][$region];

  $form['solo_settings']["settings_{$region}"] = _generate_form_details($label);
  $form['solo_settings']["settings_{$region}"]["classes_{$region}"] = _generate_css_classes($region, $label);

  // Determine current alignment setting, with backward compatibility.
  $menu_alignment = theme_get_setting('primary_menu_alignment');

  if (!isset($menu_alignment) || $menu_alignment === '') {
    if (theme_get_setting('primary_menu_align_center')) {
      $menu_alignment = 'center';
    }
    elseif (theme_get_setting('primary_menu_justify_content')) {
      $menu_alignment = 'justify';
    }
    else {
      // Default to left-aligned.
      $menu_alignment = 'none';
    }
  }
  $form['solo_settings']["settings_{$region}"]['primary_menu_depth_info'] = [
    '#type' => 'item',
    '#title' => t('Menu depth support'),
    '#markup' => t('The Solo theme supports <strong>up to 4 levels of nested menu items</strong> in the primary menu block.
      Menus behave consistently across all levels and support both hover and click interactions on large screens.<br><br>
      <strong>Example structure:</strong><br>
      - Fruit<br>
      &nbsp;&nbsp;- Apple<br>
      &nbsp;&nbsp;&nbsp;&nbsp;- Red Apple<br>
      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- Gala<br>
      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- Delish<br>
      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- Green<br><br>'),
  ];
  // Define the select field with full descriptions.
  $form['solo_settings']["settings_{$region}"]['primary_menu_alignment'] = [
    '#type' => 'select',
    '#title' => t('Main menu block alignment (large screens only)'),
    '#description' => t('Controls how the entire main menu block is aligned within its parent container on large screens. It is aligned to the left by default. This feature is intended to align the entire block, not its children.'),
    '#options' => [
      'none' => t('Left aligned (default): Positions the entire menu block to the left side of the container.'),
      'center' => t('Center aligned: Centers the entire menu block horizontally within the container.'),
      'justify' => t('Justify content: Stretches the menu block to evenly fill the container width.'),
      'right' => t('Right aligned: Aligns the entire menu block to the right edge of the container.'),
    ],
    '#default_value' => $menu_alignment,
  ];

  $form['solo_settings']["settings_{$region}"]['skip_navigation_content'] = [
    '#type' => 'checkbox',
    '#title' => t('Enable skip to main navigation link'),
    '#default_value' => theme_get_setting('skip_navigation_content'),
  ];

  $form['solo_settings']["settings_{$region}"]['primary_menu_on_hover'] = [
    '#type' => 'checkbox',
    '#title' => t('Tick this box if you prefer to show the main menu dropdowns when hovering over them with the mouse, instead of clicking. (Large screen only)'),
    '#default_value' => theme_get_setting('primary_menu_on_hover'),
  ];

  $form['solo_settings']["settings_{$region}"]['primary_menu_arrow_hover'] = [
    '#type' => 'checkbox',
    '#title' => t('Tick this box to hide dropdown arrows on hover menus. (Large screen only)'),
    '#description' => t('When enabled, the main menu expands dropdowns on hover. This option removes the arrow icons from parent menu items, reducing visual clutter and saving space, especially for menus with many items.'),
    '#default_value' => theme_get_setting('primary_menu_arrow_hover'),
    '#states' => [
      'visible' => [
        ':input[name="primary_menu_on_hover"]' => ['checked' => TRUE],
      ],
    ],
  ];

  $form['solo_settings']["settings_{$region}"]['primary_menu_border'] = [
    '#type' => 'checkbox',
    '#title' => t('Tick this box if you prefer to add border for each menu item'),
    '#default_value' => theme_get_setting('primary_menu_border'),
  ];

  $form['solo_settings']["settings_{$region}"]['primary_menu_branding'] = [
    '#type' => 'checkbox',
    '#title' => t('Tick this box to display a clickable logo next to the Main Menu. To also display the site name, add the class show-sitename to the field (CSS Classes Only).'),
    '#default_value' => theme_get_setting('primary_menu_branding'),
  ];

  $form['solo_settings']["settings_{$region}"]['primary_menu_expand_left'] = [
    '#type' => 'checkbox',
    '#title' => t('For LTR languages, tick this box to make the dropdown menu open on the left instead of the default right. For RTL languages, ticking this box will make the dropdown open on the right instead of the default left, adjusting to the language direction. (Large screen only)'),
    '#default_value' => theme_get_setting('primary_menu_expand_left'),
  ];

  $form['solo_settings']["settings_{$region}"]['primary_menu_keyboard'] = [
    '#type' => 'checkbox',
    '#title' => t('Tick this box to enable Keyboard Compatibility for the main menu.'),
    '#default_value' => theme_get_setting('primary_menu_keyboard'),
  ];

  $form['solo_settings']["settings_{$region}"]['primary_menu_megamenu'] = [
    '#type' => 'checkbox',
    '#title' => t('Tick this box to transform the main menu dropdowns into a Mega Menu.'),
    '#description' => t('Megamenus necessitate the implementation of hierarchical three-level menus. In this structure, the second level of menu items is instrumental in defining the "columns" for the megamenu. If the option "Tick this box to display the second level as headers" is selected, the second level will be exhibited as a header for the third-level menu items.'),
    '#default_value' => theme_get_setting('primary_menu_megamenu'),
  ];

  $form['solo_settings']["settings_{$region}"]['primary_menu_megamenu_layout'] = _create_megamenu_layout();

  $form['solo_settings']["settings_{$region}"]['primary_menu_submenu_header'] = [
    '#type' => 'checkbox',
    '#title' => t('Tick this box to display the second level as headers.'),
    '#default_value' => theme_get_setting('primary_menu_submenu_header'),
    '#states' => [
      'visible' => [
        ':input[name="primary_menu_megamenu"]' => ['checked' => TRUE],
      ],
    ],
  ];

  foreach ($attributes as $attribute_key => $attribute_label) {
    $form['solo_settings']["settings_{$region}"]["settings_{$region}_{$attribute_key}"] = _generate_form_element($region, $label, $attribute_key, $attribute_label);
  }

}
