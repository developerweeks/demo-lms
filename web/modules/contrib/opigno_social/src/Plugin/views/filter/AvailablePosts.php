<?php

namespace Drupal\opigno_social\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\opigno_social\Entity\OpignoPostInterface;
use Drupal\opigno_social\Services\OpignoPostsManager;
use Drupal\opigno_social\Services\UserConnectionManager;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter view handler for the user's available posts.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("opigno_available_posts")
 */
class AvailablePosts extends FilterPluginBase {

  /**
   * User connection manager service.
   *
   * @var \Drupal\opigno_social\Services\UserConnectionManager
   */
  protected UserConnectionManager $connectionManager;

  /**
   * Posts manager service.
   *
   * @var \Drupal\opigno_social\Services\OpignoPostsManager
   */
  protected OpignoPostsManager $postsManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(UserConnectionManager $connection_manager, OpignoPostsManager $posts_manager, ...$default) {
    parent::__construct(...$default);
    $this->connectionManager = $connection_manager;
    $this->postsManager = $posts_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('opigno_user_connection.manager'),
      $container->get('opigno_posts.manager'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['before_last_visit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Before the last feed visit.'),
      '#description' => $this->t('If checked, will be shown posts created before the last social feed visit page. This is needed for the social wall to not duplicate new posts.'),
      '#weight' => -10,
      '#default_value' => $this->options['before_last_visit'] ?? FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    $this->options['before_last_visit'] = $form_state->getValue([
      'options',
      'before_last_visit',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return $this->options['before_last_visit'] ? $this->t('Before last visit') : $this->t('All available posts');
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!$this->query instanceof Sql) {
      return;
    }

    // Display posts that were created before the last dashboard access time to
    // avoid bugs related to the "Load more" button. New posts will be added by
    // the separate ajax request or after the page reload.
    $last_access = $this->postsManager->getLastUserSocialWallAccessTime();
    // Display only posts from the user's network and user's own posts.
    $network = $this->connectionManager->getUserNetwork();
    $network = array_merge($network, [$this->connectionManager->currentUid]);
    // Exclude the hidden posts.
    $hidden = $this->postsManager->getPinnedHiddenPosts(FALSE);

    // Prepare query.
    $this->ensureMyTable();
    $this->query->addWhere($this->options['group'], "$this->tableAlias.type", OpignoPostInterface::SOCIAL_POST_BUNDLE);

    $before_last_visit = $this->options['before_last_visit'] ?? FALSE;
    if ($before_last_visit) {
      $this->query->addWhere($this->options['group'], "$this->tableAlias.created", $last_access, '<');
    }

    $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $network, 'IN');
    if ($hidden) {
      $this->query->addWhere($this->options['group'], "$this->tableAlias.id", $hidden, 'NOT IN');
    }
  }

}
