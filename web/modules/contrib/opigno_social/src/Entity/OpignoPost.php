<?php

namespace Drupal\opigno_social\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\opigno_like\Services\OpignoLikeManager;
use Drupal\opigno_social\Services\OpignoPostsManager;
use Drupal\opigno_social\Services\OpignoPostsManagerInterface;
use Drupal\opigno_social\Services\UserConnectionManager;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Define the Opigno post/comment entity.
 *
 * @ingroup opigno_social
 *
 * @ContentEntityType(
 *   id = "opigno_post",
 *   label = @Translation("Opigno post/comment"),
 *   label_collection = @Translation("Posts"),
 *   label_singular = @Translation("post"),
 *   label_plural = @Translation("posts"),
 *   label_count = @PluralTranslation(
 *     singular = "@count post",
 *     plural = "@count posts",
 *   ),
 *   base_table = "opigno_post",
 *   admin_permission = "administer opigno_post entities",
 *   bundle_label = @Translation("Opigno post type"),
 *   bundle_entity_type = "opigno_post_type",
 *   field_ui_base_route = "entity.opigno_post_type.edit_form",
 *   permission_granularity = "bundle",
 *   handlers = {
 *     "storage" = "Drupal\opigno_social\OpignoPostStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\opigno_social\OpignoPostViewsData",
 *     "access" = "Drupal\opigno_social\OpignoPostAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\EntityPermissionProvider",
 *     "form" = {
 *       "default" = "Drupal\opigno_social\Form\PostForm",
 *       "add" = "Drupal\opigno_social\Form\PostForm",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *       "html" = "Drupal\opigno_social\RouteProvider\OpignoSocialHtmlRouteProvider",
 *     },
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uid" = "uid",
 *     "bundle" = "type",
 *     "parent" = "parent",
 *     "created" = "created",
 *   },
 *   links = {
 *     "canonical" = "/post/{opigno_post}",
 *     "add-form" = "/post/create/{opigno_post_type}",
 *   },
 * )
 */
class OpignoPost extends ContentEntityBase implements OpignoPostInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['text'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Text'))
      ->setRequired(TRUE)
      ->setSettings([
        'text_processing' => 1,
        'allowed_formats' => [static::POST_TEXT_FORMAT],
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['parent'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Parent')
      ->setSetting('target_type', 'opigno_post')
      ->setDefaultValue(0)
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ]);

    // Group/module ID.
    $fields['attachment_entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Attachment entity ID'))
      ->setReadOnly(TRUE)
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ]);

    // Attachment entity type: group/opigno_module.
    $fields['attachment_entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Attachment entity type'))
      ->setReadOnly(TRUE)
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ]);

    // Training/certificate/badge.
    $fields['attachment_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Attachment type'))
      ->setReadOnly(TRUE)
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created on'))
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorId(): int {
    return (int) $this->get('uid')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthor(): ?UserInterface {
    $uid = $this->getAuthorId();
    if (!$uid) {
      return NULL;
    }
    $user = User::load($uid);

    return $user instanceof UserInterface ? $user : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthorId(int $uid): OpignoPostInterface {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentId(): int {
    return (int) $this->get('parent')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setParentId(int $pid): OpignoPostInterface {
    $this->set('parent', $pid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParent(): ?OpignoPostInterface {
    $id = $this->getParentId();
    if (!$id) {
      return NULL;
    }
    $parent = static::load($id);

    return $parent instanceof OpignoPostInterface || !$parent->isComment() ? $parent : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getText(bool $as_array = FALSE) {
    $field = $this->get('text');
    if ($field->isEmpty()) {
      return $as_array ? [] : '';
    }

    $values = $field->getValue();
    $values = reset($values);

    if ($as_array) {
      return $values;
    }

    $text = $values['value'] ?? $field->getString();
    $format = $values['format'] ?? 'plain_text';

    return check_markup($text, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function setText(string $text): OpignoPostInterface {
    $this->set('text', [
      'value' => $text,
      'format' => static::POST_TEXT_FORMAT,
    ]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setCreated(int $timestamp = 0): OpignoPostInterface {
    if (!$timestamp) {
      $timestamp = \Drupal::time()->getRequestTime();
    }
    $this->set('created', $timestamp);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isComment(): bool {
    return (bool) $this->getParentId();
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachmentEntityType(): string {
    return $this->get('attachment_entity_type')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setAttachmentEntityType(string $type): OpignoPostInterface {
    $this->set('attachment_entity_type', $type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachmentEntityId(): int {
    return (int) $this->get('attachment_entity_id')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachmentEntity(): ?EntityInterface {
    $eid = $this->getAttachmentEntityId();
    $type = $this->getAttachmentEntityType();
    if (!$eid || !$type) {
      return NULL;
    }

    try {
      $entity = $this->entityTypeManager()->getStorage($type)->load($eid);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_social_exception', $e);
      $entity = NULL;
    }

    return $entity instanceof EntityInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setAttachmentEntityId(int $eid): OpignoPostInterface {
    // Attachments can be added only to posts, not to comments.
    if (!$this->isComment()) {
      $this->set('attachment_entity_id', $eid);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachmentType(): string {
    return $this->get('attachment_type')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setAttachmentType(string $type): OpignoPostInterface {
    // Attachments can be added only to posts, not to comments.
    if (!$this->isComment()) {
      $this->set('attachment_type', $type);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPinned(): bool {
    $posts_manager = static::getPostManagerService();

    return $posts_manager instanceof OpignoPostsManagerInterface
      && in_array($this->id(), $posts_manager->getPinnedHiddenPosts());
  }

  /**
   * {@inheritdoc}
   */
  public function setPinned(bool $pinned = TRUE): OpignoPostInterface {
    $posts_manager = static::getPostManagerService();
    if ($pinned === $this->isPinned() || !$posts_manager instanceof OpignoPostsManagerInterface) {
      return $this;
    }

    $pinned_list = $posts_manager->getPinnedHiddenPosts();
    $pid = $this->id();
    // Unpin the post.
    if (!$pinned) {
      unset($pinned_list[$pid]);
    }
    else {
      $pinned_list[$pid] = $pid;
    }

    $posts_manager->setPinnedHiddenPosts($pinned_list);
    // Update the last viewed post ID if needed.
    if ($pid === $posts_manager->getLastViewedPostId()) {
      $posts_manager->setLastViewedPostId(0, TRUE);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Set the actual created date.
    if ($this->isNew()) {
      $this->setCreated();
    }

    // Set the current user as the author if none was set before.
    if (!$this->getAuthorId()) {
      $uid = (int) \Drupal::currentUser()->id();
      $this->setAuthorId($uid);
    }

    // Set the parent to 0 if it's not set before.
    if ($this->get('parent')->isEmpty()) {
      $this->setParentId(0);
    }

    // Save the attachment only if the allowed type was given.
    $attachment_type = $this->getAttachmentType();
    $allowed = [
      'training',
      'certificate',
      'badge',
    ];
    $attachment_entity_type = $this->getAttachmentEntityType();
    $entity_types = ['group', 'opigno_module'];

    if (($attachment_type && !in_array($attachment_type, $allowed))
      || ($attachment_entity_type && !in_array($attachment_entity_type, $entity_types))
    ) {
      $this->setAttachmentType('');
      $this->setAttachmentEntityId(0);
      $this->setAttachmentEntityType('');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Create the notification for the post author if someone commented a post.
    if ($update) {
      return;
    }

    $post = $this->getParent();
    $comment_author = $this->getAuthor();
    if (!$post instanceof OpignoPostInterface || !$comment_author instanceof UserInterface) {
      return;
    }

    $post_author_id = $post->getAuthorId();
    if ($post_author_id === (int) $comment_author->id()) {
      return;
    }

    $msg = $this->getCommentNotificationMsg($comment_author);
    $url = Url::fromRoute('entity.opigno_post.canonical', ['opigno_post' => (int) $post->id()])->toString();
    $url = $url instanceof GeneratedUrl ? $url->getGeneratedUrl() : $url;

    try {
      opigno_set_message($post_author_id, $msg, $url);
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_social_exception', $e);
    }
  }

  /**
   * Gets the notification text for the case when the post has been commented.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user who commented the post.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The notification message text.
   */
  protected function getCommentNotificationMsg(UserInterface $user): TranslatableMarkup {
    return $this->t('@user replied to your post', ['@user' => $user->getDisplayName()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return array_merge(parent::getCacheContexts(), ['user', 'url']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    $tags = parent::getCacheTagsToInvalidate();
    return array_merge($tags, [OpignoLikeManager::ENTITY_LIKERS_CACHE_PREFIX . $this->entityTypeId . (int) $this->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    parent::delete();

    $like_manager = \Drupal::service('opigno_like.manager');
    $post_manager = static::getPostManagerService();
    if (!$like_manager instanceof OpignoLikeManager
      || !$post_manager instanceof OpignoPostsManagerInterface
    ) {
      return;
    }

    // Remove likes.
    $id = (int) $this->id();
    $type = $this->getEntityTypeId();
    $post_manager->cache->delete(OpignoPostsManager::OPIGNO_POST_COMMENTS_CACHE_PREFIX . $id);
    $like_manager->deleteEntityLikes($id, $type);

    if ($this->isComment()) {
      // Invalidate cache.
      $post_manager->cache->invalidate(OpignoPostsManager::OPIGNO_POST_COMMENTS_CACHE_PREFIX . $this->getParentId());
      return;
    }

    // Remove post comments.
    $comment_ids = $post_manager->getPostComments($id);
    $comments = $post_manager->loadPost($comment_ids);
    if ($comments) {
      \Drupal::entityTypeManager()->getStorage($type)->delete($comments);
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getPostManagerService(): ?OpignoPostsManagerInterface {
    return \Drupal::service('opigno_posts.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function sendLikeNotification(UserInterface $user): void {
    // Don't send the notification if the user liked their own post/comment.
    $author_id = $this->getAuthorId();
    if ($author_id === (int) \Drupal::currentUser()->id()) {
      return;
    }

    if ($this->isComment()) {
      $msg = $this->getCommentLikeNotificationMsg($user);
      $id = $this->getParentId();
    }
    else {
      $msg = $this->getPostLikeNotificationMsg($user);
      $id = (int) $this->id();
    }

    $url = Url::fromRoute('entity.opigno_post.canonical', ['opigno_post' => $id])->toString();
    $url = $url instanceof GeneratedUrl ? $url->getGeneratedUrl() : $url;
    try {
      opigno_set_message($author_id, $msg, $url);
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_social_exception', $e);
    }
  }

  /**
   * Gets the notification text for the case when the comment was liked.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user who liked the comment.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The notification message text.
   */
  protected function getCommentLikeNotificationMsg(UserInterface $user): TranslatableMarkup {
    return $this->t('@user liked your comment', ['@user' => $user->getDisplayName()]);
  }

  /**
   * Gets the notification text for the case when the post was liked.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user who liked the post.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The notification message text.
   */
  protected function getPostLikeNotificationMsg(UserInterface $user): TranslatableMarkup {
    return $this->t('@user liked your post', ['@user' => $user->getDisplayName()]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getFeedRoute(): string {
    return 'opigno_social.feed_page';
  }

  /**
   * {@inheritdoc}
   */
  public function getDeleteRedirectUrl(): Url {
    $route = static::getFeedRoute();
    return Url::fromRoute($route);
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleCompanions(): array {
    // For the regular posts limit the list of users who can see the post to the
    // user network.
    $connections_manager = \Drupal::service('opigno_user_connection.manager');
    return $connections_manager instanceof UserConnectionManager ? $connections_manager->getUserNetwork() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getTmpStoreAvailableMentionsKey(): string {
    return 'post_available_mention_uids_' . \Drupal::currentUser()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getTmpStoreMentionsKey(): string {
    $suffix = $this->isComment() ? 'comment_' : 'post_';
    return 'post_mentioned_by_' . $suffix . \Drupal::currentUser()->id();
  }

}
