<?php

namespace Drupal\opigno_social\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\opigno_social\Entity\OpignoPost;
use Drupal\opigno_social\Entity\OpignoPostInterface;
use Drupal\opigno_social\Services\OpignoPostsManagerInterface;
use Drupal\opigno_social\Services\UserConnectionManager;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the social feed block.
 *
 * @Block(
 *  id = "opigno_social_feed_block",
 *  admin_label = @Translation("Social feed"),
 *  category = @Translation("Opigno Social"),
 * )
 */
class SocialFeedBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Whether the social features enabled or not.
   *
   * @var bool
   */
  protected bool $socialsEnabled;

  /**
   * The entity form builder service.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected EntityFormBuilderInterface $entityFormBuilder;

  /**
   * The posts manager service.
   *
   * @var \Drupal\opigno_social\Services\OpignoPostsManagerInterface
   */
  protected OpignoPostsManagerInterface $postsManager;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * If new posts should be checked.
   *
   * @var bool
   */
  protected static bool $checkNewPosts = TRUE;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityFormBuilderInterface $entity_form_builder,
    OpignoPostsManagerInterface $posts_manager,
    AccountInterface $account,
    ...$default
  ) {
    parent::__construct(...$default);
    $this->socialsEnabled = (bool) $config_factory->get('opigno_class.socialsettings')->get('enable_social_features') ?? FALSE;
    $this->entityFormBuilder = $entity_form_builder;
    $this->postsManager = $posts_manager;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity.form_builder'),
      $container->get('opigno_posts.manager'),
      $container->get('current_user'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!$this->socialsEnabled) {
      return [
        '#cache' => [
          'tags' => ['config:opigno_class.socialsettings'],
        ],
      ];
    }

    // Set the social wall access time for the user.
    if (static::$checkNewPosts) {
      $this->postsManager->setLastUserSocialWallAccessTime();
      $url = Url::fromRoute('opigno_social.check_new_posts')->toString();
      $options = [
        'attributes' => [
          'id' => 'opigno-new-posts-link',
          'class' => [
            'use-ajax',
            'btn',
            'btn-rounded',
            'btn-new-post',
          ],
          'data-opigno-social-check-posts-url' => $url instanceof GeneratedUrl ? $url->getGeneratedUrl() : $url,
        ],
      ];

      $new_posts_link = Link::createFromRoute(
        $this->t('New post available'), 'opigno_social.display_new_posts', [], $options
      )->toRenderable();
    }
    else {
      $new_posts_link = NULL;
    }

    return [
      '#theme' => 'opigno_social_wall_block',
      '#create_post_form' => $this->getPostForm(),
      '#posts' => $this->getView(),
      '#new_posts_link' => $new_posts_link,
    ];
  }

  /**
   * Gets the post creation form.
   *
   * @return array
   *   The render array to display the post creation form.
   */
  protected function getPostForm(): array {
    $post = OpignoPost::create(['type' => OpignoPostInterface::SOCIAL_POST_BUNDLE]);
    return $this->entityFormBuilder->getForm($post, 'add');
  }

  /**
   * Gets the rendered posts view.
   *
   * @return array|null
   *   The render array to display the posts view.
   */
  protected function getView(): ?array {
    return Views::getView('opigno_social_posts')->executeDisplay('posts');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:opigno_class.socialsettings',
      UserConnectionManager::USER_CONNECTIONS_CACHE_TAG_PREFIX . $this->account->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

}
