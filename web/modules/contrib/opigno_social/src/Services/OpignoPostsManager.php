<?php

namespace Drupal\opigno_social\Services;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_module\Entity\OpignoModuleInterface;
use Drupal\opigno_social\Entity\OpignoPostInterface;
use Drupal\opigno_social\Entity\UserInvitationInterface;
use Drupal\opigno_social\OpignoPostStorageInterface;
use Drupal\opigno_social\Plugin\Block\CommentFormBlock;
use Drupal\opigno_social_community\Services\CommunityManagerService;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The Opigno posts/comments manager service.
 *
 * @package Drupal\opigno_social\Services
 */
class OpignoPostsManager implements OpignoPostsManagerInterface {

  use StringTranslationTrait;

  /**
   * Cache prefix for the post comments.
   */
  const OPIGNO_POST_COMMENTS_CACHE_PREFIX = 'opigno_post_comments_';

  /**
   * The default number of comments to be shown.
   */
  const OPIGNO_COMMENTS_DEFAULT_AMOUNT = 3;

  /**
   * The posts/comments storage.
   *
   * @var \Drupal\opigno_social\OpignoPostStorageInterface
   */
  protected OpignoPostStorageInterface $storage;

  /**
   * The posts/comments view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected EntityViewBuilderInterface $viewBuilder;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The block manager service.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected BlockManagerInterface $blockManager;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  public CacheBackendInterface $cache;

  /**
   * The user connections manager service.
   *
   * @var UserConnectionManager
   */
  protected UserConnectionManager $connectionManager;

  /**
   * The user data.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected UserDataInterface $userData;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected ? Request $request;

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * OpignoPostsManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param UserConnectionManager $connection_manager
   *   The user connections manager service.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    BlockManagerInterface $block_manager,
    CacheBackendInterface $cache,
    UserConnectionManager $connection_manager,
    UserDataInterface $user_data,
    RequestStack $request_stack,
    Connection $database
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->viewBuilder = $entity_type_manager->getViewBuilder('opigno_post');
    $this->blockManager = $block_manager;
    $this->cache = $cache;
    $this->connectionManager = $connection_manager;
    $this->userData = $user_data;
    $this->request = $request_stack->getCurrentRequest();
    $this->database = $database;

    try {
      $this->storage = $entity_type_manager->getStorage('opigno_post');
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_social_exception', $e);
    }
  }

  /**
   * Load the post/comment by the given ID(s).
   *
   * @param int|array $pid
   *   The post ID(s) to be loaded.
   *
   * @return \Drupal\opigno_social\Entity\OpignoPostInterface|null|array
   *   The loaded post/comment entity (entities).
   */
  public function loadPost($pid) {
    if (!$pid || !$this->storage instanceof EntityStorageInterface) {
      return NULL;
    }

    // Load a single post.
    if (!is_array($pid)) {
      $post = $this->storage->load($pid);

      return $post instanceof OpignoPostInterface ? $post : NULL;
    }

    return $this->storage->loadMultiple($pid);
  }

  /**
   * Get comments form.
   *
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface $post
   *   The post entity to add a comment for.
   *
   * @return array
   *   The render array to display the comment form block.
   */
  public function getCommentForm(OpignoPostInterface $post) : array {
    $form = [];
    try {
      $form = $this->blockManager->createInstance('opigno_social_comment_form_block', [
        'parent_post' => $post,
      ]);
    }
    catch (PluginException $e) {
      watchdog_exception('opigno_social_exception', $e);
    }

    return $form instanceof CommentFormBlock ? $form->build() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCommentFormLink(int $pid, bool $is_close_link = FALSE): array {
    $options = [
      'attributes' => [
        'id' => "opigno-show-comment-form-$pid",
        'class' => ['use-ajax', 'comment-item__actions--comment'],
      ],
    ];

    if ($is_close_link) {
      $url = Url::fromRoute('opigno_social.hide_post_comments', ['opigno_post' => $pid], $options);
    }
    else {
      $params = [
        'opigno_post' => $pid,
        'amount' => static::OPIGNO_COMMENTS_DEFAULT_AMOUNT,
      ];

      $url = Url::fromRoute('opigno_social.get_post_comments', $params, $options);
      $this->connectionManager->protectUrl($url);
    }

    if (!$url->access()) {
      return [];
    }

    // Add the icon before the title.
    $title = Markup::create('<i class="fi fi-rr-comment-alt"></i>' . $this->t('Comment'));

    $link = Link::fromTextAndUrl($title, $url)->toRenderable();
    $link['#cache']['contexts'] = ['session'];

    return $link;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMoreCommentsLink(int $pid, int $amount, int $from = 0): array {
    $comments = $this->getUserViewingComments($pid) ?: $this->getPostComments($pid);
    $total = count($comments);
    if ($from >= $total) {
      return [];
    }

    $options = [
      'attributes' => [
        'id' => "opigno-comments-load-more-link-$pid",
        'class' => ['use-ajax', 'load-comments'],
      ],
    ];
    $params = [
      'opigno_post' => $pid,
      'from' => $from,
      'amount' => $amount,
    ];
    $url = Url::fromRoute('opigno_social.load_more_comments', $params, $options);
    $this->connectionManager->protectUrl($url);
    $link = Link::fromTextAndUrl($this->t('Load more'), $url)->toRenderable();
    $link['#cache']['contexts'] = ['session'];

    return $link;
  }

  /**
   * Get the render array for the post comments displaying.
   *
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface $post
   *   The post entity to get comments for.
   * @param int $amount
   *   The number of comments to be gotten.
   *
   * @return array
   *   The render array to display the post comments.
   */
  public function renderPostComments(OpignoPostInterface $post, int $amount): array {
    $pid = (int) $post->id();
    $comment_ids = $this->getPostComments($pid, $amount);
    $loaded_comments = $this->loadPost($comment_ids);
    $comments = [];
    if ($loaded_comments) {
      foreach ($loaded_comments as $comment) {
        if ($comment instanceof OpignoPostInterface) {
          $comments[] = $this->viewBuilder->view($comment);
        }
      }
    }

    return [
      '#theme' => 'opigno_post_comments',
      '#form' => $this->getCommentForm($post),
      '#comments' => $comments,
      '#more_link' => $this->loadMoreCommentsLink($pid, $amount, $amount),
      '#cache' => [
        'tags' => [static::OPIGNO_POST_COMMENTS_CACHE_PREFIX . $pid],
      ],
    ];
  }

  /**
   * Get the list of the post comments.
   *
   * @param int $pid
   *   The post ID to get comments for.
   * @param int $amount
   *   The number of comments to be gotten.
   * @param int $from
   *   The index of comment to get starting from.
   *
   * @return array
   *   The list of rendered the post comments.
   */
  public function getPostComments(int $pid, int $amount = 0, int $from = 0): array {
    $result = [];
    $cid = static::OPIGNO_POST_COMMENTS_CACHE_PREFIX . $pid;
    // Try to get comments from the cache.
    $cached = $this->cache->get($cid)->data ?? [];
    if ($cached && is_array($cached)) {
      // Get the needed number of comments.
      return $amount ? array_slice($cached, $from, $amount) : $cached;
    }

    if (!$this->storage instanceof EntityStorageInterface || !$pid) {
      return $result;
    }

    // Get the post comments from the storage.
    $result = $this->storage->getQuery()
      ->accessCheck()
      ->condition('parent', $pid)
      ->sort('created', 'DESC')
      ->sort('id', 'DESC')
      ->execute();

    if (!is_array($result)) {
      return [];
    }

    // Store data in the cache.
    $this->cache->set($cid, $result, Cache::PERMANENT, [$cid]);

    return $amount ? array_slice($result, $from, $amount) : $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getCommentsLink(int $pid, bool $is_close_link = FALSE): array {
    $classes = ['use-ajax', 'comment-item__actions--comment'];
    // Set the link title to "X comments".
    $count = count($this->getPostComments($pid));
    // Hide display a link if there are no comments yet.
    if (!$count) {
      $classes[] = 'hidden';
    }
    $title = $this->formatPlural($count, '1 comment', '@count comments');

    $options = [
      'attributes' => [
        'id' => "opigno-comments-amount-link-$pid",
        'class' => $classes,
      ],
    ];

    if ($is_close_link) {
      $url = Url::fromRoute('opigno_social.hide_post_comments', ['opigno_post' => $pid], $options);
    }
    else {
      $params = [
        'opigno_post' => $pid,
        'amount' => static::OPIGNO_COMMENTS_DEFAULT_AMOUNT,
      ];

      $url = Url::fromRoute('opigno_social.get_post_comments', $params, $options);
    }

    $this->connectionManager->protectUrl($url);
    $link = Link::fromTextAndUrl($title, $url)->toRenderable();
    $link['#cache']['contexts'] = ['session'];

    return $link;
  }

  /**
   * {@inheritdoc}
   */
  public function getActionLinks(OpignoPostInterface $post): array {
    $build = [
      '#theme' => 'opigno_post_actions',
      '#actions' => [],
    ];

    // For the comment there should be available only "Delete" action and
    // accessible for the author.
    if ($post->isComment()) {
      $delete = $this->getDeleteLink($post);
      if ($delete) {
        $build['#actions'][] = $delete;
      }
      return $build;
    }

    $this->addExtraActionLinks($build, $post);
    return $build;
  }

  /**
   * Gets the post delete link.
   *
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface $post
   *   The post to be deleted.
   *
   * @return array
   *   The render array to display the link.
   */
  protected function getDeleteLink(OpignoPostInterface $post): array {
    return $this->generateActionLink(
      'opigno_social.delete_post',
      ['opigno_post' => $post->id()],
      $this->t('Delete'),
      'delete');
  }

  /**
   * Adds the extra action links available for the given post.
   *
   * @param array $build
   *   The current links build array.
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface $post
   *   The post to add operations for.
   */
  protected function addExtraActionLinks(array &$build, OpignoPostInterface $post): void {
    $pid = (int) $post->id();
    $actions = [];
    // Generate post action links.
    $actions[] = $this->generateActionLink(
      'opigno_social.hide_post',
      ['opigno_post' => $pid],
      $this->t('Hide', [], ['context' => 'Opigno post']),
      'hide'
    );

    $pin_text = $post->isPinned()
      ? $this->t('Unpin', [], ['context' => 'Opigno post'])
      : $this->t('Pin', [], ['context' => 'Opigno post']);
    $actions[] = $this->generateActionLink(
      'opigno_social.pin_post',
      ['opigno_post' => $pid],
      $pin_text,
      'pin'
    );

    // Post author or user with an appropriate permission should be able to
    // delete post. Other users should be able to remove the author from their
    // connections.
    $actions[] = $this->getDeleteLink($post);
    $actions[] = $this->getRemoveConnectionLink($post);

    // Add only not empty actions.
    foreach ($actions as $action) {
      if ($action) {
        $build['#actions'][] = $action;
      }
    }
  }

  /**
   * Gets the "Remove from connections" post action link.
   *
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface $post
   *   The post to generate a link for.
   *
   * @return array
   *   A render link array.
   */
  protected function getRemoveConnectionLink(OpignoPostInterface $post): array {
    $link = [];
    $data = $this->connectionManager->getDeclineInvitationDataByOneUser($post->getAuthorId());
    $invitation = $data['invitation'] ?? NULL;
    if ($invitation instanceof UserInvitationInterface) {
      $link = $this->generateActionLink(
        'opigno_social.decline_user_invitation',
        $data['params'],
        $this->t('Remove from connections'),
        'decline'
      );
    }

    return $link;
  }

  /**
   * Generate the post/comment action link.
   *
   * @param string $route
   *   The name of the route.
   * @param array $params
   *   The route parameters.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $text
   *   The link text.
   * @param string $action
   *   The action name. Needed to add the extra class to the link.
   *
   * @return array
   *   Generated action link.
   */
  protected function generateActionLink(string $route, array $params, $text, string $action): array {
    $options = [
      'attributes' => [
        'class' => ['dropdown-item-text', 'use-ajax', "post-action-$action"],
      ],
    ];
    $url = Url::fromRoute($route, $params, $options);

    if (!$url->access()) {
      return [];
    }

    $this->connectionManager->protectUrl($url);
    $link = Link::fromTextAndUrl($text, $url)->toRenderable();
    $link['#cache']['contexts'] = ['session'];

    return $link;
  }

  /**
   * Get the list of pinned OR hidden post IDs for the current user.
   *
   * @param bool $pinned
   *   Use TRUE to get pinned posts, FALSE for hidden.
   *
   * @return array
   *   The list of pinned OR hidden post IDs for the current user.
   */
  public function getPinnedHiddenPosts(bool $pinned = TRUE): array {
    $key = $pinned ? 'pinned_posts' : 'hidden_posts';
    return (array) $this->userData->get('opigno_social', $this->connectionManager->currentUid, $key);
  }

  /**
   * Set the list of pinned OR hidden post IDs for the current user.
   *
   * @param array $value
   *   The list of posts to be set.
   * @param bool $pinned
   *   Use TRUE to set pinned post IDs, FALSE for hidden.
   */
  public function setPinnedHiddenPosts(array $value, bool $pinned = TRUE): void {
    $key = $pinned ? 'pinned_posts' : 'hidden_posts';
    $this->userData->set('opigno_social', $this->connectionManager->currentUid, $key, $value);
  }

  /**
   * Get the list of currently viewing post comments.
   *
   * @param int $pid
   *   Post ID to get viewed comments for (comments for several posts can be
   *   open at the same time).
   * @param int $from
   *   The index to get comments starting from.
   * @param int $amount
   *   The number of comments to be returned.
   *
   * @return array
   *   The list of currently viewing post comments.
   */
  public function getUserViewingComments(int $pid, int $from = 0, int $amount = 0): array {
    $data = $this->userData->get('opigno_social', $this->connectionManager->currentUid, 'viewing_comments') ?? [];
    $comments = $data[$pid] ?? [];
    if ($comments && $from && $amount) {
      return array_slice($comments, $from, $amount);
    }

    return $comments;
  }

  /**
   * Set the list of currently viewing post comments.
   *
   * This is needed to avoid the comments duplication in case if new comments
   * were posted while the user is viewing the post feed.
   *
   * @param int $pid
   *   Post ID to set viewed comments for (comments for several posts can be
   *   open at the same time).
   * @param array $comments
   *   The list of currently viewing comments.
   */
  public function setUserViewingComments(int $pid, array $comments): void {
    $uid = $this->connectionManager->currentUid;
    $data = $this->userData->get('opigno_social', $uid, 'viewing_comments');
    if (!$comments) {
      unset($data[$pid]);
      if (!$data) {
        $this->userData->delete('opigno_social', $uid, 'viewing_comments');
      }
    }
    else {
      $data[$pid] = $comments;
    }

    $this->userData->set('opigno_social', $uid, 'viewing_comments', $data);
  }

  /**
   * Get the timestamp of the last access of the social wall for the user.
   *
   * @return int
   *   The timestamp of the last access of the social wall for the current user.
   */
  public function getLastUserSocialWallAccessTime(): int {
    return (int) $this->userData->get('opigno_social', $this->connectionManager->currentUid, 'social_wall_access_time');
  }

  /**
   * {@inheritdoc}
   */
  public function getLastViewedPostId(): int {
    $uid = $this->connectionManager->currentUid;
    $pid = (int) $this->userData->get('opigno_social', $uid, 'last_viewed_post');

    // Check if the post exists.
    if (!$this->storage instanceof EntityStorageInterface) {
      return $pid;
    }

    $post = $this->storage->load($pid);
    if (!$post instanceof OpignoPostInterface || $post->isComment()) {
      $this->setLastViewedPostId(0, FALSE);
    }

    return (int) $this->userData->get('opigno_social', $uid, 'last_viewed_post');
  }

  /**
   * Set the post ID that is the last viewed by the current user.
   *
   * @param int $last_post
   *   The post ID to be set (optional). If nothing is given, the ID will be
   *   found automatically.
   * @param bool $check_last
   *   If the previously set comment should be checked or not.
   */
  public function setLastViewedPostId(int $last_post = 0, bool $check_last = TRUE): void {
    $uid = $this->connectionManager->currentUid;
    $pinned = $this->getPinnedHiddenPosts();
    $hidden = $this->getPinnedHiddenPosts(FALSE);

    if (!$last_post) {
      // Find the last viewed post ID.
      $last_access = $this->getLastUserSocialWallAccessTime();
      if (!$this->storage instanceof EntityStorageInterface || !$last_access) {
        return;
      }

      $network = array_merge($this->connectionManager->getUserNetwork(), [$uid]);
      $query = $this->storage->getQuery()
        ->accessCheck()
        ->condition('created', $last_access, '<')
        ->condition('parent', 0)
        ->condition('uid', $network, 'IN');
      // Exclude pinned posts.
      if ($pinned) {
        $query->condition('id', $pinned, 'NOT IN');
      }
      // Exclude hidden.
      if ($hidden) {
        $query->condition('id', $hidden, 'NOT IN');
      }
      $last_post = $query->sort('created', 'DESC')
        ->range(0, 1)
        ->execute();

      if (is_array($last_post)) {
        $last_post = (int) reset($last_post);
      }
    }

    if (!$last_post) {
      return;
    }

    // Invalidate cache for the previous last viewed post and the new one to
    // display the "up to date" label correctly.
    $previous_post = $check_last ? $this->getLastViewedPostId() : 0;
    $post_ids = array_unique([$last_post, $previous_post]);
    $posts = $this->storage->loadMultiple($post_ids);
    foreach ($posts as $post) {
      if ($post instanceof OpignoPostInterface) {
        Cache::invalidateTags($post->getCacheTagsToInvalidate());
      }
    }

    // Don't override the last post if it was viewed without page reload,
    // for example, the post that was created by the current user.
    $final_latest = in_array($previous_post, $pinned) || in_array($previous_post, $hidden) ? $last_post : max($post_ids);
    $this->userData->set('opigno_social', $uid, 'last_viewed_post', $final_latest);
  }

  /**
   * Set the timestamp of the last access of the social wall for the user.
   */
  public function setLastUserSocialWallAccessTime(): void {
    // Don't update the access time if the request was sent with ajax.
    if (!$this->request instanceof Request || $this->request->isXmlHttpRequest()) {
      return;
    }

    $uid = $this->connectionManager->currentUid;
    // Cleanup the list of viewing comments.
    $this->userData->delete('opigno_social', $uid, 'viewing_comments');
    $this->setLastViewedPostId();
    $this->userData->set('opigno_social', $uid, 'social_wall_access_time', time());
  }

  /**
   * Prepare the render array to display the post attachment.
   *
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface|null $post
   *   The post entity. Leave empty to display data based on the given
   *   attachment type (training/post/certificate), attachment entity type
   *   (group/opigno_module) and ID.
   * @param string $type
   *   The attachment type (training/post/certificate). Will be used if the post
   *   isn't given.
   * @param int $id
   *   The attachment entity ID. Will be used if the post isn't given.
   * @param string $entity_type
   *   The attachment entity type (group/opigno_post). Will be used if the post
   *   isn't given.
   *
   * @return array
   *   The render array to display the post attachment.
   */
  public function renderPostAttachment(?OpignoPostInterface $post = NULL, string $type = '', int $id = 0, string $entity_type = ''): array {
    $author = NULL;
    $result = [];
    if ($post instanceof OpignoPostInterface) {
      $entity_type = $post->getAttachmentEntityType();
      $id = $post->getAttachmentEntityId();
      $type = $post->getAttachmentType();
      $author = $post->getAuthor();
    }

    if (!$entity_type || !$type || !$id) {
      return $result;
    }

    // Get the attachment-related entity.
    try {
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($id);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_social_exception', $e);
      $entity = NULL;
    }

    if (!$entity instanceof EntityInterface) {
      return $result;
    }

    $user_name = $author instanceof UserInterface ? $author->getDisplayName() : $this->connectionManager->user->getDisplayName();
    // Render the result depending on the attachment type and entity type.
    switch ($type) {
      case 'training':
        if ($entity instanceof GroupInterface) {
          $result = $this->entityTypeManager->getViewBuilder($entity_type)->view($entity, 'preview');
        }
        break;

      case 'certificate':
        $result = [
          '#theme' => 'opigno_post_achievement_preview',
          '#text' => $this->t('@name acquired a new certificate:', ['@name' => $user_name]),
          '#achievement_type' => $this->t('Certificate'),
          '#name' => $entity instanceof GroupInterface ? $entity->label() : '',
          '#extra_class' => $type,
        ];
        break;

      case 'badge':
        $badge_url = $entity instanceof OpignoModuleInterface ? $entity->getBadgeUrl() : NULL;
        $result = [
          '#theme' => 'opigno_post_achievement_preview',
          '#text' => $this->t('@name acquired a new badge:', ['@name' => $user_name]),
          '#achievement_type' => $this->t('Badge', [], ['context' => 'Opigno post']),
          '#image' => !empty($badge_url) ? [
            '#theme' => 'image_style',
            '#uri' => $badge_url,
            '#width' => 95,
            '#height' => 95,
            '#style_name' => 'thumbnail',
            '#alt' => $this->t('Badge image'),
          ] : [],
          '#name' => $this->getBadgeName($id, $entity_type),
          '#extra_class' => $badge_url ? 'image' : $type,
        ];
        break;

      default:
        // Do nothing.
    }

    return $result;
  }

  /**
   * Get the badge name.
   *
   * @param int $id
   *   The attachment-related entity ID.
   * @param string $entity_type
   *   The attachment-related entity type (opigno_module/group).
   *
   * @return string
   *   The badge name.
   */
  private function getBadgeName(int $id, string $entity_type): string {
    if ($entity_type === 'opigno_module') {
      $name = $this->database->select('opigno_module_field_data', 'om')
        ->fields('om', ['badge_name'])
        ->condition('om.id', $id)
        ->execute()
        ->fetchField();
    }
    else {
      $name = $this->database->select('group__badge_name', 'gbn')
        ->fields('gbn', ['badge_name_value'])
        ->condition('gbn.entity_id', $id)
        ->execute()
        ->fetchField();
    }

    return (string) $name;
  }

  /**
   * Get posts that were created after the last user access.
   *
   * @param string $bundle
   *   A type of posts to be checked.
   *
   * @return array
   *   The list of posts that were created after the last user access.
   */
  public function getNewPosts(string $bundle = 'all'): array {
    $posts = [];
    $previous_access_time = $this->getLastUserSocialWallAccessTime();
    $network = $this->connectionManager->getUserNetwork();
    if (!$this->storage instanceof EntityStorageInterface
      || !$previous_access_time
      || !$network
    ) {
      return $posts;
    }

    // Get posts that were created after the last user access.
    $query = $this->storage->getQuery()
      ->accessCheck()
      ->condition('id', $this->getLastViewedPostId(), '>')
      ->condition('parent', 0);

    switch ($bundle) {
      case OpignoPostInterface::SOCIAL_POST_BUNDLE:
        $query->condition('uid', $network, 'IN')
          ->condition('type', $bundle);
        break;

      case 'community_post':
        $communities = static::getAvailableCommunities();
        if ($communities) {
          $query->condition('community', $communities, 'IN')
            ->condition('type', $bundle);
        }
        break;

      default:
        $from_network = $query->andConditionGroup()
          ->condition('uid', $network, 'IN')
          ->condition('type', OpignoPostInterface::SOCIAL_POST_BUNDLE);

        $communities = static::getAvailableCommunities();
        if ($communities) {
          $from_communities = $query->andConditionGroup()
            ->condition('community', $communities, 'IN')
            ->condition('type', 'community_post');
          $or_group = $query->orConditionGroup()
            ->condition($from_network)
            ->condition($from_communities);
          $query->condition($or_group);
        }
        else {
          $query->condition($from_network);
        }
    }

    $posts = $query->sort('created')->execute();

    return is_array($posts) ? $posts : [];
  }

  /**
   * Gets the joined communities if community module is enabled.
   *
   * @return array
   *   The list of user's available communities.
   */
  private static function getAvailableCommunities(): array {
    $service = \Drupal::hasService('opigno_social_community.manager')
      ? \Drupal::service('opigno_social_community.manager')
      : NULL;

    return $service instanceof CommunityManagerService ? $service->getJoinedCommunities() : [];
  }

  /**
   * Get the post text with read more/show less link.
   *
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface $post
   *   The post to get the text from.
   *
   * @return array|\Drupal\Component\Render\MarkupInterface
   *   The post text with read more/show less link.
   */
  public function renderReadMoreText(OpignoPostInterface $post) {
    $value = $post->getText(TRUE);
    $text = $value['value'] ?? '';
    $format = $value['format'] ?? 'plain_text';
    $text_length = mb_strlen($text);
    $length = 600;

    // Do nothing if text length is less than the trim one.
    if ($text_length <= $length) {
      return check_markup($text, $format);
    }

    $summary = Unicode::truncate($text, $length, TRUE, TRUE);
    // Close all HTML tags.
    $summary = Html::normalize($summary);

    return [
      '#theme' => 'opigno_read_more',
      '#summary' => check_markup($summary, $format),
      '#text' => check_markup($text, $format),
    ];
  }

}
