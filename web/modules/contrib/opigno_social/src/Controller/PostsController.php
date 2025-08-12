<?php

namespace Drupal\opigno_social\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\BeforeCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\opigno_social\Entity\OpignoPostInterface;
use Drupal\opigno_social\Entity\PostTypeInterface;
use Drupal\opigno_social\OpignoPostStorageInterface;
use Drupal\opigno_social\Plugin\Block\SocialFeedBlock;
use Drupal\opigno_social\Services\OpignoPostsManagerInterface;
use Drupal\views\Ajax\ScrollTopCommand;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Opigno posts/comments controller.
 *
 * @package Drupal\opigno_social\Controller
 */
class PostsController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * The posts/comment manager service.
   *
   * @var \Drupal\opigno_social\Services\OpignoPostsManagerInterface
   */
  protected OpignoPostsManagerInterface $postsManager;

  /**
   * The posts/comments storage.
   *
   * @var \Drupal\opigno_social\OpignoPostStorageInterface
   */
  protected OpignoPostStorageInterface $storage;

  /**
   * The posts/comments view builder service.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected EntityViewBuilderInterface $viewBuilder;

  /**
   * The current route name.
   *
   * @var string|null
   */
  protected ?string $currentRoute;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The block plugin manager service.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected BlockManagerInterface $blockManager;

  /**
   * Entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $bundleInfo;

  /**
   * PostsController constructor.
   *
   * @param \Drupal\opigno_social\Services\OpignoPostsManagerInterface $posts_manager
   *   The posts/comment manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder service.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   Entity type bundle info service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Drupal logger service.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(
    OpignoPostsManagerInterface $posts_manager,
    EntityTypeManagerInterface $entity_type_manager,
    RouteMatchInterface $route_match,
    EntityFormBuilderInterface $entity_form_builder,
    BlockManagerInterface $block_manager,
    EntityTypeBundleInfoInterface $bundle_info,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->postsManager = $posts_manager;
    $this->viewBuilder = $entity_type_manager->getViewBuilder('opigno_post');
    $this->currentRoute = $route_match->getRouteName();
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->blockManager = $block_manager;
    $this->storage = $entity_type_manager->getStorage('opigno_post');
    $this->bundleInfo = $bundle_info;
    $this->loggerFactory = $logger_factory;

    if (!$this->storage instanceof OpignoPostStorageInterface) {
      throw new InvalidPluginDefinitionException('opigno_post_storage', 'The post storage should implement OpignoPostStorageInterface.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('opigno_posts.manager'),
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('entity.form_builder'),
      $container->get('plugin.manager.block'),
      $container->get('entity_type.bundle.info'),
      $container->get('logger.factory')
    );
  }

  /**
   * Hide the post comments.
   *
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface $opigno_post
   *   The post entity.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function hidePostComments(OpignoPostInterface $opigno_post): AjaxResponse {
    $pid = (int) $opigno_post->id();
    // Unset the lost of viewing comments.
    $this->postsManager->setUserViewingComments($pid, []);
    $response = new AjaxResponse();

    // Hide the post comments section.
    $response->addCommand(new HtmlCommand("#opigno-comments-placeholder-$pid", ''));
    $comments_link = $this->postsManager->getCommentsLink($pid);
    $comment_form_link = $this->postsManager->getCommentFormLink($pid);

    return $response->addCommand(new ReplaceCommand("#opigno-comments-amount-link-$pid", $comments_link))
      ->addCommand(new ReplaceCommand("#opigno-show-comment-form-$pid", $comment_form_link));
  }

  /**
   * Get the post comments block.
   *
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface $opigno_post
   *   The post entity to get comments for.
   * @param int $amount
   *   The number of comments to be gotten.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function getPostComments(OpignoPostInterface $opigno_post, int $amount): AjaxResponse {
    $pid = (int) $opigno_post->id();
    $response = new AjaxResponse();
    // Set post comments to user data to avoid troubles with displaying in case
    // when the new comment was added while user is viewing the post feed.
    $current_feed = $this->postsManager->getPostComments($pid);
    $this->postsManager->setUserViewingComments($pid, $current_feed);

    // Close all opened forms, display the post comments section, update the
    // comments link.
    $comments = $this->postsManager->renderPostComments($opigno_post, $amount);
    $response->addCommand(new HtmlCommand("#opigno-comments-placeholder-$pid", $comments));
    $comments_link = $this->postsManager->getCommentsLink($pid, TRUE);
    $comment_form_link = $this->postsManager->getCommentFormLink($pid, TRUE);

    // Make all comment form links active, disable only for the current post
    // to prevent comments overriding.
    return $response->addCommand(new ReplaceCommand("#opigno-comments-amount-link-$pid", $comments_link))
      ->addCommand(new ReplaceCommand("#opigno-show-comment-form-$pid", $comment_form_link));
  }

  /**
   * Load more post comments.
   *
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface $opigno_post
   *   The post entity to get comments for.
   * @param int $from
   *   The index of the comment to load more.
   * @param int $amount
   *   The number of comments to load.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function loadMoreComments(OpignoPostInterface $opigno_post, int $from, int $amount): AjaxResponse {
    if (!$amount) {
      return new AjaxResponse(NULL, 400);
    }

    $pid = (int) $opigno_post->id();
    $response = new AjaxResponse();
    $comment_ids = $this->getPostCommentIds($pid, $from, $amount);
    if (!$comment_ids) {
      return new AjaxResponse(NULL, 400);
    }

    // Add new comments to the wrapper.
    $comments = $this->postsManager->loadPost($comment_ids);
    foreach ($comments as $comment) {
      if ($comment instanceof OpignoPostInterface) {
        $item = $this->viewBuilder->view($comment);
        $response->addCommand(new AppendCommand("#opigno-comments-placeholder-$pid .opigno-comment:last", $item));
      }
    }
    // Update the "Load more" link.
    $more_link = $this->postsManager->loadMoreCommentsLink($pid, $amount, $from + $amount);

    return $response->addCommand(new ReplaceCommand("#opigno-comments-load-more-link-$pid", $more_link));
  }

  /**
   * Gets the post comment IDs to load more.
   *
   * @param int $pid
   *   The The post ID to get comments for.
   * @param int $from
   *   The index of the comment to load more.
   * @param int $amount
   *   The number of comments to load.
   *
   * @return array
   *   The post comment IDs to load more.
   */
  protected function getPostCommentIds(int $pid, int $from, int $amount): array {
    return $this->postsManager->getUserViewingComments($pid, $from, $amount)
      ?: $this->postsManager->getPostComments($pid, $amount, $from);
  }

  /**
   * Delete the post/comment with all its likes and comments.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface $opigno_post
   *   The post to be deleted.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function deletePost(Request $request, OpignoPostInterface $opigno_post): AjaxResponse {
    $pid = $opigno_post->id();
    $is_post_page = $this->isPostViewPage($request);
    $response = new AjaxResponse();
    $url = $opigno_post->getDeleteRedirectUrl();

    try {
      $opigno_post->delete();
    }
    catch (EntityStorageException $e) {
      $this->getLogger('opigno_social')->error($e->getMessage());
      return $response->setStatusCode(400, $this->t('An error occurred, the post can not be deleted.'));
    }

    // Remove the post/comment wrapper.
    $response->addCommand(new RemoveCommand("#opigno-post-$pid"));

    // Update comments amount link on the comment removal.
    if ($opigno_post->isComment()) {
      $parent = $opigno_post->getParentId();
      $comments_link = $this->postsManager->getCommentsLink($parent, TRUE);
      $response->addCommand(new ReplaceCommand("#opigno-comments-amount-link-$parent", $comments_link));
      return $response;
    }

    // Redirect to the homepage if the post was removed from the view page.
    if ($is_post_page) {
      $response->addCommand(new RedirectCommand($url->toString()));
    }

    return $response;
  }

  /**
   * Hide the given post for the user.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface $opigno_post
   *   The post entity to be hidden.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function hidePost(Request $request, OpignoPostInterface $opigno_post): AjaxResponse {
    $response = new AjaxResponse();
    if ($opigno_post->isComment()) {
      return $response->setStatusCode(400, $this->t('A comment can not be hidden.'));
    }

    $pid = $opigno_post->id();
    $hidden = $this->postsManager->getPinnedHiddenPosts(FALSE);
    // Do nothing if the post is already hidden.
    if (in_array($pid, $hidden)) {
      return $response->setStatusCode(400, $this->t('A post is already hidden.'));
    }

    $hidden[$pid] = $pid;
    $this->postsManager->setPinnedHiddenPosts($hidden, FALSE);
    // Redirect to the feed page if the post was hidden from the view page,
    // otherwise remove from the list.
    if ($this->isPostViewPage($request)) {
      $route = $opigno_post::getFeedRoute();
      $url = Url::fromRoute($route)->toString();
      $url = $url instanceof GeneratedUrl ? $url->getGeneratedUrl() : $url;
      $response->addCommand(new RedirectCommand($url));
    }
    else {
      $response->addCommand(new RemoveCommand("#opigno-post-$pid"));
    }

    // Invalidate an appropriate cache tags and update the last viewed post ID
    // if needed.
    Cache::invalidateTags($opigno_post->getCacheTagsToInvalidate());
    if ($pid === $this->postsManager->getLastViewedPostId()) {
      $this->postsManager->setLastViewedPostId();
    }

    return $response;
  }

  /**
   * Pin/unpin the given post for the user.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\opigno_social\Entity\OpignoPostInterface $opigno_post
   *   The post to be pinned/unpinned.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function pinPost(Request $request, OpignoPostInterface $opigno_post): AjaxResponse {
    $response = new AjaxResponse();
    if ($opigno_post->isComment()) {
      return $response->setStatusCode(400, $this->t('A comment can not be pinned.'));
    }

    $pid = $opigno_post->id();
    // Invalidate an appropriate cache tags.
    Cache::invalidateTags($opigno_post->getCacheTagsToInvalidate());

    // Unpin if the post is already pinned.
    if ($opigno_post->isPinned()) {
      $opigno_post->setPinned(FALSE);
      // Add a placeholder at the top of the view to correctly place newly
      // pinned posts.
      $pinned_placeholder = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['opigno-pinned-post'],
        ],
      ];

      // Remove the pin icon.
      return $response->addCommand(new RemoveCommand("#opigno-post-$pid .pinned-post"))
        ->addCommand(new InvokeCommand("#opigno-post-$pid .post-action-pin", 'text', [$this->t('Pin')->render()]))
        ->addCommand(new InvokeCommand("#opigno-post-$pid .opigno-pinned-post", 'removeClass', ['opigno-pinned-post']))
        ->addCommand(new AfterCommand('.opigno-post-form-wrapper', $pinned_placeholder));
    }

    $opigno_post->setPinned();
    // Move the post to the top of the feed if it was pinned not form the post
    // view page, otherwise only add the pin icon and text.
    if (!$this->isPostViewPage($request)) {
      $response->addCommand(new RemoveCommand("#opigno-post-$pid"))
        ->addCommand(new BeforeCommand('.opigno-pinned-post:first', $this->viewBuilder->view($opigno_post)));
    }
    else {
      $pin = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => Markup::create($this->t('pinned post', [], ['context' => 'Opigno social']) . '<i class="fi"></i>'),
        '#attributes' => [
          'class' => ['pinned-post'],
        ],
      ];
      $response->addCommand(new AppendCommand("#opigno-post-$pid .comment-item__user", $pin))
        ->addCommand(new InvokeCommand("#opigno-post-$pid .post-action-pin", 'text', [$this->t('Unpin')->render()]));
    }

    return $response;
  }

  /**
   * Check if the current page is a single post page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return bool
   *   If the current page is a single post page.
   */
  protected function isPostViewPage(Request $request): bool {
    return $this->getMainRouteFromRequest($request) === 'entity.opigno_post.canonical';
  }

  /**
   * Get the route name from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return string
   *   The current route name. For ajax requests will be returned the referer
   *   route name.
   */
  protected function getMainRouteFromRequest(Request $request): string {
    // Get the referer page url for ajax route.
    return $request->isXmlHttpRequest()
      ? $this->storage->getMainRoutePropertyFromRequest('_route', '')
      : $this->currentRoute;
  }

  /**
   * Display the popup with the sharable post content (training/badge/cert).
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $type
   *   The content type to be shared (training/badge/certificate).
   * @param string $opigno_post_type
   *   Opigno post entity bundle; by default - "social".
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function getShareableContent(
    Request $request,
    string $type,
    string $opigno_post_type = OpignoPostInterface::SOCIAL_POST_BUNDLE
  ): AjaxResponse {
    $response = new AjaxResponse();
    // Add the general response attachments.
    $url = Url::fromRoute(
      'opigno_social.share_post_content',
      ['opigno_post_type' => $opigno_post_type]
    )->toString();
    $attachments = [
      'library' => ['opigno_social/post_sharing'],
      'drupalSettings' => [
        'opignoSocial' => [
          'shareContentUrl' => $url instanceof GeneratedUrl ? $url->getGeneratedUrl() : $url,
          'postBundle' => $opigno_post_type,
        ],
      ],
    ];

    switch ($type) {
      case 'training':
        $title = $this->t('Add training');
        $view = Views::getView('post_sharing_trainings')->executeDisplay('trainings');
        break;

      case 'certificate':
        $title = $this->t('Add certificate');
        $view = Views::getView('post_sharing_trainings')->executeDisplay('certificates');
        break;

      case 'badge':
        $title = $this->t('Add badge');
        $view = Views::getView('post_sharing_badges')->executeDisplay('badges');
        break;

      default:
        $msg = $this->t('Unrecognized type of content was attempted to share. Type: @type', ['@type' => $type]);
        $this->getLogger('opigno_social')->error($msg);
        $response->setStatusCode(400, $msg);
        return $response;
    }

    $attachments = array_merge_recursive($view['#attached'], $attachments);
    $response->addAttachments($attachments);
    $replace = $request->get('replace', FALSE);
    // Replace the popup content with the view if an appropriate parameter is in
    // the query. This is used for the back link in the popup.
    if ($replace) {
      $close = $this->prepareBackClosePopupLink($opigno_post_type, $type, TRUE);
      return $response->addCommand(new ReplaceCommand('.modal-ajax .modal-header .close', $close))
        ->addCommand(new RemoveCommand('.modal-ajax .modal-header .close-x'))
        ->addCommand(new HtmlCommand('.modal-ajax .modal-title', $title))
        ->addCommand(new HtmlCommand('.modal-ajax .modal-body', $view));
    }

    // Prepare the popup data.
    $build = [
      '#theme' => 'opigno_popup',
      '#title' => $title,
      '#body' => $view,
      '#is_ajax' => TRUE,
    ];

    return $response->addCommand(new RemoveCommand('.modal-ajax'))
      ->addCommand(new AppendCommand('body', $build))
      ->addCommand(new InvokeCommand('.modal-ajax', 'modal', ['show']));
  }

  /**
   * Open the popup for the post content sharing (training/badge/certificate).
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\opigno_social\Entity\PostTypeInterface $opigno_post_type
   *   Post bundle.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function sharePostContent(Request $request, PostTypeInterface $opigno_post_type): AjaxResponse {
    $response = new AjaxResponse();
    $bundle = $opigno_post_type->id();
    $type = $request->get('type', '');
    $id = (int) $request->get('id');
    $entity_type = $request->get('entity_type');
    $text = $request->get('text', '');

    if (is_array($text)) {
      $text = $text[0]['value'] ?? '';
    }
    $text = trim($text);

    /** @var \Drupal\opigno_social\Entity\OpignoPostInterface $post */
    $post = $this->storage->create([
      'type' => $bundle,
      'text' => [
        'value' => $text,
        'format' => OpignoPostInterface::POST_TEXT_FORMAT,
      ],
      'attachment_entity_id' => $id,
      'attachment_entity_type' => $entity_type,
      'attachment_type' => $type,
    ]);
    $form = $this->entityFormBuilder->getForm($post, 'add');

    if (!$form) {
      $msg = $this->t('The form can not be built.');
      $this->getLogger('opigno_social')->error($msg);
      return $response->setStatusCode(400, $msg);
    }

    // Prepare the back and the close popup links.
    // The case when the post is created from the home page.
    $type = $request->get('type', '');
    if ($this->getMainRouteFromRequest($request) === $post::getFeedRoute()) {
      $back = $this->prepareBackClosePopupLink($bundle, $type);
      $close = $this->prepareBackClosePopupLink($bundle, $type, TRUE, 'cross-small');

      return $response->addCommand(new ReplaceCommand('.modal-ajax .modal-header .close', $back))
        ->addCommand(new AfterCommand('.modal-ajax .modal-header .close', $close))
        ->addCommand(new HtmlCommand('.modal-ajax .modal-title', $this->t('Create a post')))
        ->addCommand(new HtmlCommand('.modal-ajax .modal-body', $form));
    }

    // The case when the post is created not from the home page.
    $build = [
      '#theme' => 'opigno_popup',
      '#title' => $this->t('Create a post'),
      '#body' => $form,
      '#is_ajax' => TRUE,
    ];

    return $response->addCommand(new RemoveCommand('.modal-ajax'))
      ->addCommand(new AppendCommand('body', $build))
      ->addCommand(new InvokeCommand('.modal-ajax', 'modal', ['show']));
  }

  /**
   * Prepare the render array for back and close popup links.
   *
   * @param string $bundle
   *   Opigno post entity bundle.
   * @param string $type
   *   The attachment type (training/badge/certificate).
   * @param bool $close
   *   TRUE if the link should trigger the popup closing, FALSE to go back to
   *   the previous state of the modal.
   * @param string $icon
   *   The link icon.
   *
   * @return array
   *   The render array for back and close popup links.
   */
  private function prepareBackClosePopupLink(string $bundle, string $type, bool $close = FALSE, string $icon = 'arrow-left'): array {
    $title = Markup::create('<i class="fi fi-rr-' . $icon . '"></i>');
    $classes = ['use-ajax', 'close'];

    if ($icon === 'cross-small') {
      $classes[] = 'close-x';
    }

    $options = [
      'attributes' => [
        'class' => $classes,
        'type' => 'button',
        'data-dismiss' => 'modal',
        'aria-label' => $close ? $this->t('Close') : $this->t('Back'),
      ],
    ];

    if ($close) {
      $params = [];
      $route = 'opigno_learning_path.close_modal';
    }
    else {
      $route = 'opigno_social.get_shareable_content';
      $params = [
        'type' => $type,
        'opigno_post_type' => $bundle,
      ];
      $options['query'] = ['replace' => TRUE];
    }

    return Link::createFromRoute($title, $route, $params, $options)->toRenderable();
  }

  /**
   * Check if new posts were created after the last social wall access.
   *
   * @param string $bundle
   *   A type of posts to be checked.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function checkNewPosts(string $bundle = 'all'): AjaxResponse {
    $posts = $this->postsManager->getNewPosts($bundle);
    return new AjaxResponse(['newPosts' => !empty($posts)]);
  }

  /**
   * Display posts that were created after the last social wall access.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function displayNewPosts(): AjaxResponse {
    $response = new AjaxResponse();
    $posts = $this->postsManager->getNewPosts();

    if (!$posts || !$this->storage instanceof EntityStorageInterface) {
      return $response->addCommand(new InvokeCommand('.btn-new-post__wrapper', 'addClass', ['hidden']));
    }

    // Display new posts after all pinned ones, hide the link and scroll top.
    $last_key = array_key_last($posts);
    foreach ($posts as $key => $id) {
      $post = $this->storage->load($id);
      if (!$post instanceof OpignoPostInterface) {
        continue;
      }
      $content = $this->viewBuilder->view($post);
      $response->addCommand(new AfterCommand('.opigno-pinned-post:last', $content));
      // Update the last viewed post ID.
      if ($key === $last_key) {
        $this->postsManager->setLastViewedPostId((int) $id);
      }
    }

    return $response->addCommand(new InvokeCommand('.btn-new-post__wrapper', 'addClass', ['hidden']))
      ->addCommand(new ScrollTopCommand('.opigno-pinned-post:last'));
  }

  /**
   * Builds the social feed page.
   *
   * @return array
   *   The social feed page content.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function socialFeedPage(): array {
    $left_col = [];
    $left_blocks = [
      $this->blockManager->createInstance('opigno_user_stats_block', ['without_stats' => TRUE]),
      $this->blockManager->createInstance('opigno_user_connections_block'),
      $this->blockManager->createInstance('opigno_user_communities_block', ['create_community_link' => TRUE]),
    ];

    foreach ($left_blocks as $block) {
      if ($block instanceof BlockBase) {
        $left_col[] = $block->build();
      }
    }

    $feed_block = $this->blockManager->createInstance('opigno_social_feed_block');
    $right_col = [];
    $right_views = [
      'who_s_online' => 'who_s_online_block',
      'who_s_new' => 'block_1',
    ];

    foreach ($right_views as $view => $display) {
      $right_col[] = Views::getView($view)->executeDisplay($display);
    }

    return [
      '#theme' => 'opigno_social_feed_page',
      '#left_column' => $left_col,
      '#center_column' => $feed_block instanceof SocialFeedBlock ? [$feed_block->build()] : [],
      '#right_column' => $right_col,
    ];
  }

}
