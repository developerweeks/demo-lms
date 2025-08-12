<?php

namespace Drupal\opigno_social\Plugin\Block;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_social\Entity\OpignoPostInterface;
use Drupal\opigno_social\OpignoPostStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the "Create comment" block.
 *
 * @Block(
 *  id = "opigno_social_comment_form_block",
 *  admin_label = @Translation("Opigno Social create comment block"),
 *  category = @Translation("Opigno Social"),
 * )
 */
class CommentFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The post entity type.
   *
   * @var string
   */
  protected static string $entityType = 'opigno_post';

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected EntityFormBuilderInterface $entityFormBuilder;

  /**
   * The post entity storage.
   *
   * @var \Drupal\opigno_social\OpignoPostStorageInterface
   */
  protected OpignoPostStorageInterface $storage;

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
    EntityFormBuilderInterface $entity_form_builder,
    AccountInterface $account,
    EntityTypeManagerInterface $entity_type_manager,
    ...$default
  ) {
    parent::__construct(...$default);
    $this->entityFormBuilder = $entity_form_builder;
    $this->account = $account;

    try {
      $this->storage = $entity_type_manager->getStorage('opigno_post');
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_social_exception', $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity.form_builder'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
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
  public function blockAccess(AccountInterface $account) {
    $parent_post = $this->configuration['parent_post'] ?? NULL;

    return AccessResult::allowedIf(
      $parent_post instanceof OpignoPostInterface
      && $parent_post->access('comment_post', $account)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!$this->access($this->account)) {
      return [];
    }

    // The form should contain additional wrappers and elements, that are
    // impossible to be added in the form template.
    // It's guaranteed that the parent will be a valid post, this is checked in
    // blockAccess().
    /** @var \Drupal\opigno_social\Entity\OpignoPostInterface $parent_post */
    $parent_post = $this->configuration['parent_post'];

    // Render the form.
    $pid = (int) $parent_post->id();
    $post = $this->storage->create([
      'parent' => $pid,
      'type' => $parent_post->bundle(),
    ]);
    $form = $this->entityFormBuilder->getForm($post, 'add');

    return [
      '#theme' => 'opigno_social_comment_form_block',
      '#form' => $form,
      '#attached' => $form['#attached'] ?? [],
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];
  }

}
