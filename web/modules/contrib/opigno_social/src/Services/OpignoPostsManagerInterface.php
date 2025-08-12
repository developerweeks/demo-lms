<?php

namespace Drupal\opigno_social\Services;

use Drupal\opigno_social\Entity\OpignoPostInterface;

/**
 * Defines the general interface for the posts/comments manager services.
 *
 * @package Drupal\opigno_social\Services
 */
interface OpignoPostsManagerInterface {

  /**
   * Gets the link to hide/show the post comments.
   *
   * @param int $pid
   *   The post ID to get the comments link for.
   * @param bool $is_close_link
   *   If the link should close a comment section.
   *
   * @return array
   *   The rendered link to hide/show post comments.
   */
  public function getCommentsLink(int $pid, bool $is_close_link = FALSE): array;

  /**
   * Get the list of available post/comment action links.
   *
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface $post
   *   The post/comment entity to get actions for.
   *
   * @return array
   *   The render array of available action links.
   */
  public function getActionLinks(OpignoPostInterface $post): array;

  /**
   * Gets the AJAX link to generate the comment form.
   *
   * @param int $pid
   *   The post ID to get the comment form link for.
   * @param bool $is_close_link
   *   If the link should close a comment section.
   *
   * @return array
   *   The link to generate the comment form.
   */
  public function getCommentFormLink(int $pid, bool $is_close_link = FALSE): array;

  /**
   * Generates the load more comments link.
   *
   * @param int $pid
   *   The post ID to get comments for.
   * @param int $amount
   *   The number of comments to load.
   * @param int $from
   *   The index of the comment to load more.
   *
   * @return array
   *   The render array to display the load more comments link.
   */
  public function loadMoreCommentsLink(int $pid, int $amount, int $from = 0): array;

  /**
   * Get the post ID that is the last viewed by the current user.
   *
   * @return int
   *   The ID of the last post viewed by the current user.
   */
  public function getLastViewedPostId(): int;

}
