<?php

namespace Drupal\opigno_social\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_social\Services\UserConnectionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the user connections block.
 *
 * @Block(
 *  id = "opigno_user_connections_block",
 *  admin_label = @Translation("Connections"),
 *  category = @Translation("Opigno Social"),
 * )
 */
class UserConnectionsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The user connections manager service.
   *
   * @var \Drupal\opigno_social\Services\UserConnectionManager
   */
  protected $connectionsManager;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * {@inheritdoc}
   */
  public function __construct(UserConnectionManager $connections_manager, AccountInterface $account, ...$default) {
    parent::__construct(...$default);
    $this->connectionsManager = $connections_manager;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('opigno_user_connection.manager'),
      $container->get('current_user'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::forbiddenIf($account->isAnonymous());
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
  public function build(bool $with_wrapper = TRUE) {
    $uid = $this->account->id();
    $connections_number = count($this->connectionsManager->getUserNetwork($uid));
    $attributes = ['attributes' => ['class' => ['btn']]];

    if ($connections_number) {
      $link_title = $this->t('Manage your connections');
    }
    else {
      $link_title = $this->t('Find connections');
      $attributes['attributes']['class'][] = 'btn-rounded';
    }

    return [
      '#theme' => 'opigno_user_connections_block',
      '#connections' => $connections_number,
      '#connections_link' => Link::createFromRoute($link_title,
        'opigno_social.manage_connections',
        [],
        $attributes
      )->toRenderable(),
      '#with_wrapper' => $with_wrapper,
    ];
  }

}
