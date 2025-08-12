<?php

namespace Drupal\opigno_social\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\opigno_like\Entity\LikeableEntityInterface;
use Drupal\opigno_social\Services\OpignoPostsManagerInterface;
use Drupal\user\UserInterface;

/**
 * Provides an interface to define the Opigno Post/Comment entity.
 *
 * @ingroup opigno_social
 */
interface OpignoPostInterface extends ContentEntityInterface, LikeableEntityInterface {

  /**
   * The post text format machine name.
   */
  const POST_TEXT_FORMAT = 'post_html';

  /**
   * The machine name of social posts bundle.
   */
  const SOCIAL_POST_BUNDLE = 'social';

  /**
   * Get the post author user ID.
   *
   * @return int
   *   The post author user ID.
   */
  public function getAuthorId(): int;

  /**
   * Get the post author user.
   *
   * @return \Drupal\user\UserInterface|null
   *   The post author user.
   */
  public function getAuthor(): ?UserInterface;

  /**
   * Set the post author user ID.
   *
   * @param int $uid
   *   The post author user ID.
   *
   * @return \Drupal\opigno_social\Entity\OpignoPostInterface
   *   The updated post entity.
   */
  public function setAuthorId(int $uid): OpignoPostInterface;

  /**
   * Get the post text.
   *
   * @param bool $as_array
   *   If FALSE, the value will be returned as formatted text; otherwise - as
   *   ['value', 'format'] array.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string|array
   *   The post text.
   */
  public function getText(bool $as_array = FALSE);

  /**
   * Set the post text.
   *
   * @param string $text
   *   The post text to be set.
   *
   * @return \Drupal\opigno_social\Entity\OpignoPostInterface
   *   The updated post entity.
   */
  public function setText(string $text): OpignoPostInterface;

  /**
   * Get the post creation timestamp.
   *
   * @return int
   *   The post creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the post creation timestamp.
   *
   * @param int $timestamp
   *   The post creation timestamp.
   *
   * @return \Drupal\opigno_social\Entity\OpignoPostInterface
   *   The updated post entity.
   */
  public function setCreated(int $timestamp = 0): OpignoPostInterface;

  /**
   * Whether the current entity is a post or a comment.
   *
   * @return bool
   *   TRUE if the entity is a comment (has a parent), FALSE if it's a post
   *   (without a parent).
   */
  public function isComment(): bool;

  /**
   * Get the post (comment) parent ID.
   *
   * @return int
   *   The post (comment) parent ID (0 if there is no parent).
   */
  public function getParentId(): int;

  /**
   * Set the post (comment) parent ID.
   *
   * @param int $pid
   *   The parent entity ID to be set.
   *
   * @return \Drupal\opigno_social\Entity\OpignoPostInterface
   *   The updated post entity.
   */
  public function setParentId(int $pid): OpignoPostInterface;

  /**
   * Get the loaded parent entity.
   *
   * @return \Drupal\opigno_social\Entity\OpignoPostInterface|null
   *   The loaded parent entity (for comments).
   */
  public function getParent(): ?OpignoPostInterface;

  /**
   * Get the post attachment entity type (opigno_module/group).
   *
   * @return string
   *   The post attachment entity type (empty if there is no attachment).
   */
  public function getAttachmentEntityType(): string;

  /**
   * Set the post attachment entity type.
   *
   * @param string $type
   *   The attachment entity type to be set.
   *
   * @return \Drupal\opigno_social\Entity\OpignoPostInterface
   *   The updated post entity.
   */
  public function setAttachmentEntityType(string $type): OpignoPostInterface;

  /**
   * Get the post attachment entity ID.
   *
   * @return int
   *   The post attachment entity ID (0 if there is no attachment).
   */
  public function getAttachmentEntityId(): int;

  /**
   * Get the loaded attachment entity (opigno_module/group).
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The loaded attachment entity (NULL if there is no attachment).
   */
  public function getAttachmentEntity(): ?EntityInterface;

  /**
   * Set the post attachment entity ID.
   *
   * @param int $eid
   *   The post attachment entity ID to be set.
   *
   * @return \Drupal\opigno_social\Entity\OpignoPostInterface
   *   The updated post entity.
   */
  public function setAttachmentEntityId(int $eid): OpignoPostInterface;

  /**
   * Get the post attachment type (training/badge/certificate).
   *
   * @return string
   *   The post attachment type (empty if there is no attachment).
   */
  public function getAttachmentType(): string;

  /**
   * Set the post attachment type.
   *
   * @param string $type
   *   The attachment type to be set.
   *
   * @return \Drupal\opigno_social\Entity\OpignoPostInterface
   *   The updated post entity.
   */
  public function setAttachmentType(string $type): OpignoPostInterface;

  /**
   * Checks if the post is pinned or not.
   *
   * @return bool
   *   Whether the post is pinned or not.
   */
  public function isPinned(): bool;

  /**
   * Pin/unpin the post.
   *
   * @param bool $pinned
   *   Should the post be pinned or not.
   *
   * @return \Drupal\opigno_social\Entity\OpignoPostInterface
   *   The called community post entity.
   */
  public function setPinned(bool $pinned = TRUE): OpignoPostInterface;

  /**
   * Gets the post manager service.
   *
   * @return \Drupal\opigno_social\Services\OpignoPostsManagerInterface|null
   *   The post manager service.
   */
  public static function getPostManagerService(): ?OpignoPostsManagerInterface;

  /**
   * Gets the feed route name.
   *
   * @return string
   *   The feed route name.
   */
  public static function getFeedRoute(): string;

  /**
   * The url to redirect the user if the post was deleted from the view page.
   *
   * @return \Drupal\Core\Url
   *   The redirect url.
   */
  public function getDeleteRedirectUrl(): Url;

  /**
   * Gets the list of user IDs who can see the post.
   *
   * @return array
   *   The list of user IDs who can see the post.
   */
  public function getPossibleCompanions(): array;

  /**
   * Gets the tmp store key for available user mentions.
   *
   * @return string
   *   The tmp store key for available user mentions.
   */
  public function getTmpStoreAvailableMentionsKey(): string;

  /**
   * Gets the tmp store key for user mentions in the current post.
   *
   * @return string
   *   The tmp store key for user mentions in the current post.
   */
  public function getTmpStoreMentionsKey(): string;

}
