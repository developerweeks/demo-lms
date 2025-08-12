<?php

/**
 * @file
 * Contains module-related token callbacks.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\opigno_social\Entity\OpignoPostInterface;

/**
 * Implements hook_token_info().
 */
function opigno_social_token_info() {
  $info['types']['opigno_post'] = [
    'name' => t('Opigno post'),
    'description' => t('Tokens related to Opigno post entity.'),
    'needs-data' => 'opigno_post',
  ];

  $info['tokens']['opigno_post']['id'] = [
    'name' => t('Post ID'),
    'description' => t('The unique ID of the Opigno post entity.'),
    'module' => 'opigno_social',
  ];

  $info['tokens']['opigno_post']['entity_type'] = [
    'name' => t('Post entity type'),
    'description' => t('The machine name of the post entity.'),
    'module' => 'opigno_social',
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function opigno_social_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];
  $post = $data['opigno_post'] ?? NULL;

  if ($type !== 'opigno_post' || !$post instanceof OpignoPostInterface) {
    return $replacements;
  }

  foreach ($tokens as $name => $original) {
    switch ($name) {
      case 'id':
        $replacements[$original] = $post->id();
        break;

      case 'entity_type':
        $replacements[$original] = $post->getEntityTypeId();
        break;

      default:
        // Do nothing.
    }
  }

  return $replacements;
}
