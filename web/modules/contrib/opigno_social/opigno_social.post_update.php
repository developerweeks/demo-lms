<?php

/**
 * @file
 * Post update functions for Opigno Social.
 */

/**
 * Sets menu social link.
 */
function opigno_social_post_update_set_menu_social_link() {
  $social_enable = \Drupal::configFactory()->get('opigno_class.socialsettings')->get('enable_social_features');
  _opigno_social_set_menu_social_link($social_enable);
}
