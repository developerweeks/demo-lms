<?php

/**
 * @file
 * Solo Theme Alter.
 */

use Drupal\comment\CommentInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\NodeType;

/**
 * Custom validation handler to apply hidden content types to the search query.
 */
function solo_apply_hidden_content_types(array &$form, FormStateInterface $form_state): void {
  $hidden_content_types = $form['advanced']['hidden_content_types']['#value'] ?? [];
  if (!empty($hidden_content_types)) {
    $current_criteria = $form_state->getValue('type') ?? [];
    $updated_criteria = array_merge($current_criteria, $hidden_content_types);
    $form_state->setValue('type', $updated_criteria);
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function solo_form_node_preview_form_select_alter(&$form, FormStateInterface $form_state, $form_id): void {
  $form['backlink']['#options']['attributes']['class'][] = 'button';
  $form['backlink']['#options']['attributes']['class'][] = 'button--small';
  $form['backlink']['#options']['attributes']['class'][] = 'button--icon-back';
  $form['backlink']['#options']['attributes']['class'][] = 'button--primary';
  $form['view_mode']['#attributes']['class'][] = 'form-element--small';
}

/**
 * Implements hook_form_FORM_ID_alter() for node_form.
 */
function solo_form_node_form_alter(array &$form, FormStateInterface $form_state, $form_id): void {
  // Set the theme for the form.
  $form['#theme'] = ['node_edit_form'];

  // Define the 'advanced' and 'meta' containers.
  $form['advanced']['#type'] = 'container';
  $form['meta']['#type'] = 'container';

  // Set access for the 'meta' container.
  $form['meta']['#access'] = TRUE;

  // Add 'container-inline' class to 'changed' and 'author' wrappers.
  $form['meta']['changed']['#wrapper_attributes']['class'][] = 'container-inline';
  $form['meta']['author']['#wrapper_attributes']['class'][] = 'container-inline';

  // Define the 'revision_information' container and group it under 'meta'.
  $form['revision_information']['#type'] = 'container';
  $form['revision_information']['#group'] = 'meta';

}

/**
 * Implements hook_theme_suggestions_node_edit_form_alter().
 *
 * Adds per-content-type template suggestions for node edit forms.
 * E.g., node_edit_form__article → node-edit-form--article.html.twig.
 */
function solo_theme_suggestions_node_edit_form_alter(array &$suggestions, array $variables): void {
  $bundle = NULL;

  // Attempt to retrieve the bundle from the form's #node property (edit form).
  if (
    isset($variables['form']['#node']) &&
    $variables['form']['#node'] instanceof NodeInterface
  ) {
    $bundle = $variables['form']['#node']->bundle();
  }

  // Fallback for add form (/node/add/{bundle}).
  elseif ($route = \Drupal::routeMatch()) {
    // Check if route provides a node type object.
    if (($type = $route->getParameter('node_type')) && $type instanceof NodeType) {
      $bundle = $type->id();
    }
    // Check if route provides a node entity (some contrib modules do this).
    elseif (($node = $route->getParameter('node')) && $node instanceof NodeInterface) {
      $bundle = $node->bundle();
    }
  }

  // Add the suggestion if bundle is known.
  if ($bundle) {
    $suggestions[] = 'node_edit_form__' . $bundle;
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function solo_form_comment_form_alter(&$form, FormStateInterface $form_state, $form_id): void {
  $is_comment = $form_state->getFormObject()->getEntity();

  if ($is_comment instanceof CommentInterface) {
    $form['actions']['submit']['#value'] = $is_comment->hasParentComment() ? t('Reply to comment') : t('Post comment');
  }
}

/**
 * Implements hook_form_views_exposed_form_alter().
 */
function solo_form_views_exposed_form_alter(&$form): void {
  $form['#attributes']['class'][] = 'form--inline';
}

/**
 * Implements hook_form_FORM_ID_alter() for search_block_form.
 */
function solo_form_search_form_alter(&$form, FormStateInterface $form_state): void {
  // Remove the help link if it exists.
  if (isset($form['help_link'])) {
    unset($form['help_link']);
  }

  // Add a primary button class to forms with fewer than 3 actions.
  if (!empty($form['actions']['submit']) && isset($form['actions']) && count($form['actions']) <= 2) {
    $form['actions']['submit']['#attributes']['class'][] = 'button--primary';
  }

  // Add placeholder text to the keys input, if it exists.
  if (isset($form['keys'])) {
    $form['keys']['#attributes']['placeholder'] = t('Search by keyword or phrase.');
  }

  // Add a primary button class to basic and advanced submit buttons if existed.
  if (isset($form['basic']['submit'])) {
    $form['basic']['submit']['#attributes']['class'][] = 'button--primary';
  }
  if (isset($form['advanced']['submit'])) {
    $form['advanced']['submit']['#attributes']['class'][] = 'button--primary';
  }

  // Retrieve theme settings for filters and content types.
  $disabled_filters = theme_get_setting('disable_search_filters') ?? [];
  $always_applied_content_types = array_filter(theme_get_setting('search_result_content_types') ?? []);

  // Check if all filters are disabled.
  if (
    in_array('disable_keywords', $disabled_filters, TRUE) &&
    in_array('disable_types', $disabled_filters, TRUE) &&
    in_array('disable_language', $disabled_filters, TRUE)
  ) {
    // Remove the entire "Advanced" section.
    unset($form['advanced']);
    // No need to process further if all filters are disabled.
    return;
  }

  // Remove individual filters if specified.
  $filter_fieldsets = [
    'disable_keywords' => 'keywords-fieldset',
    'disable_language' => 'lang-fieldset',
  ];

  foreach ($filter_fieldsets as $filter_key => $fieldset_key) {
    if (isset($form['advanced'][$fieldset_key]) && in_array($filter_key, $disabled_filters, TRUE)) {
      unset($form['advanced'][$fieldset_key]);
    }
  }

  // Handle the "Types" filter specifically.
  if (isset($form['advanced']['types-fieldset']) && in_array('disable_types', $disabled_filters, TRUE)) {
    if (!empty($always_applied_content_types)) {
      // Hide the "Types" filter and add hidden content types.
      unset($form['advanced']['types-fieldset']);

      $form['advanced']['hidden_content_types'] = [
        '#type' => 'value',
        '#value' => $always_applied_content_types,
      ];

      // Add validation to apply these content types to the search query.
      $form['#validate'][] = 'solo_apply_hidden_content_types';
    }
    else {
      // Remove the "Types" filter if no content types are specified.
      unset($form['advanced']['types-fieldset']);
    }
  }

}

/**
 * Implements hook_form_FORM_ID_alter() for search_block_form.
 */
function solo_form_search_block_form_alter(&$form, FormStateInterface $form_state): void {
  // Add placeholder text to keys input.
  $form['keys']['#attributes']['placeholder'] = t('Search by keyword or phrase.');

  // Add a class to the submit button.
  $form['actions']['submit']['#attributes']['class'][] = 'search-button-block';

  // Fetch the content types from theme settings.
  $content_types = array_filter(theme_get_setting('search_result_content_types') ?? []);

  // Attach the content types to drupalSettings.
  $form['#attached']['drupalSettings']['solo'] = [
    'searchContentTypes' => $content_types,
  ];

  // Attach the custom JS library.
  $form['#attached']['library'][] = 'solo/solo-search-settings';

}
