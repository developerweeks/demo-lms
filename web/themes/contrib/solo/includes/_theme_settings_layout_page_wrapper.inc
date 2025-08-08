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

// Page wrapper settings.
$form['solo_settings']['settings_page_wrapper'] = [
  '#type' => 'details',
  '#title' => t("<strong class='status'>Page Wrapper</strong> Settings - Solo Theme"),
  '#collapsible' => TRUE,
  '#collapsed' => FALSE,
  '#description'   => t("The Page Wrapper serves as the encompassing div for all regions within the site. Any settings applied to it will have a global effect. It's important to note that the CSS should probably be provided by a trusted user, such as an admin or developer."),
];

$form['solo_settings']['settings_page_wrapper']['classes_page_wrapper'] = [
  '#type' => 'textfield',
  '#title' => t('Custom CSS Classes'),
  '#description' => t('
    <strong>Use predefined utility classes to control layout, spacing, and behavior.</strong><br><br>

    <u>Type 1: Element-only classes</u>
    These apply styles directly to the element only (not its children). Ideal for WYSIWYG fields, layout builder, and block settings.
    <ul>
      <li><code>dis-box-shadow</code>: Removes box shadow for a flat look</li>
      <li><code>dis-border</code>: Removes border</li>
      <li><code>dis-border-radius</code>: Removes rounded corners</li>
      <li><code>dis-margin</code>: Removes all margins</li>
      <li><code>dis-padding</code>: Removes all padding</li>
      <li><code>dis-text-transform</code>: Preserves original text case</li>
      <li><code>solo-align-left</code>: Aligns element left using margin auto</li>
      <li><code>solo-align-center</code>: Centers element horizontally</li>
      <li><code>solo-align-right</code>: Aligns element right using margin auto</li>
      <li><code>solo-display-none</code>: Hides the element entirely</li>
      <li><code>solo-visually-hidden</code>: Hides visually but remains accessible to screen readers</li>
      <li><code>solo-no-wrap</code>: Prevents text wrapping</li>
      <li><code>solo-break-word</code>: Allows long words to break</li>
      <li><code>solo-truncate</code>: Truncates text with ellipsis</li>
      <li><code>solo-overflow-hidden</code>: Hides overflowing content</li>
      <li><code>solo-overflow-auto</code>: Enables scroll if content overflows</li>
      <li><code>solo-mobile-hide</code>: Hides on small screens</li>
      <li><code>solo-desktop-hide</code>: Hides on larger screens</li>
      <li><code>inline-radio-checkbox</code>: Displays checkboxes and radio buttons inline rather than stacked, improving compactness in forms.</li>
    </ul>
    <br>
    <u>Type 2: Region-wide text alignment</u>
    These apply <code>text-align</code> to all child elements. Best used for regions or wrappers in theme settings.
    <ul>
      <li><code>solo-text-left</code>: Aligns all nested text left</li>
      <li><code>solo-text-center</code>: Aligns all nested text center</li>
      <li><code>solo-text-right</code>: Aligns all nested text right</li>
    </ul>
   <br>
    <u>Type 3: Disable theme features</u>
    Useful for disabling built-in animations or styling behavior.
    <ul>
      <li><code>image-auto</code>: Resets linked images to original size</li>
      <li><code>dis-anim-link</code>: Disables hover animation on menu links</li>
      <li><code>dis-anim-submit</code>: Disables button wiggle animation</li>
    </ul>
    <br>
    <strong>Usage Tips:</strong>
    - Use these classes in the "CSS Classes" field of regions, blocks, views, layout sections, or paragraphs.<br>
    - Most classes apply <code>!important</code> to ensure consistency across themes and modules.<br>
    - For text alignment, use <code>solo-text-*</code> for children, and <code>solo-align-*</code> for block alignment.<br>

    <strong>Note:</strong> Avoid overusing utility classes in deeply nested structures to maintain maintainability and design clarity.
  '),
  '#default_value' => theme_get_setting('classes_page_wrapper'),
];

$form['solo_settings']['settings_page_wrapper']['opacity_page_wrapper'] = [
  '#type' => 'checkbox',
  '#title' => t('Enable Smooth Page Load Animation'),
  '#description' => t('Activates a smooth transition effect for the entire page when it is loaded, enhancing the user experience by gradually increasing the page’s visibility from transparent to fully opaque. Ideal for creating a more engaging and visually appealing website entrance.'),

  '#default_value' => theme_get_setting('opacity_page_wrapper'),
];

foreach ($attributes as $attribute_key => $attribute_label) {
  $region = 'page_wrapper';
  $label = 'Page Wrapper';
  $form['solo_settings']["settings_{$region}"]["settings_{$region}_{$attribute_key}"] = _generate_form_element($region, $label, $attribute_key, $attribute_label);
}
