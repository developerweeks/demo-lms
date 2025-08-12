<?php

namespace Drupal\opigno_social\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_social\Services\UserConnectionManager;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the block to display the recent posts.
 *
 * @Block(
 *  id = "opigno_social_wall_block",
 *  admin_label = @Translation("Recent posts"),
 *  category = @Translation("Opigno Social"),
 * )
 */
class RecentPostsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Whether the social features enabled or not.
   *
   * @var bool
   */
  protected bool $socialsEnabled;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AccountInterface $account,
    ...$default
  ) {
    parent::__construct(...$default);
    $this->socialsEnabled = (bool) $config_factory->get('opigno_class.socialsettings')->get('enable_social_features') ?? FALSE;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('config.factory'),
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
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIf($this->socialsEnabled);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return Views::getView('opigno_social_posts')->executeDisplay('block_recent');
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
