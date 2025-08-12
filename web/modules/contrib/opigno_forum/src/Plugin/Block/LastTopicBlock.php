<?php

namespace Drupal\opigno_forum\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\forum\ForumManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a opigno_forum_last_topics_block block.
 *
 * @Block(
 *   id = "opigno_forum_last_topics_block",
 *   admin_label = @Translation("ForumLastTopicsBlock"),
 *   category = @Translation("Custom")
 * )
 */
class LastTopicBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Service forum_manager definition.
   *
   * @var \Drupal\forum\ForumManagerInterface
   */
  protected ForumManagerInterface $forumManager;

  /**
   * Current user service definition.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ForumManagerInterface $forum_manager,
    AccountInterface $current_user,
    ...$default
  ) {
    parent::__construct(...$default);
    $this->forumManager = $forum_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('forum_manager'),
      $container->get('current_user'),
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $tid = $this->configuration["taxonomy_term"];

    $build = $this->forumManager->getTopics($tid, $this->currentUser);
    $build['content'] = [
      '#theme' => 'opigno_forum_last_topics_block',
      'topics' => array_slice($build['topics'] ?: [], 0, 4),
    ];
    return $build;
  }

}
