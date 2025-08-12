<?php

namespace Drupal\opigno_social;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity\EntityAccessControlHandler;
use Drupal\opigno_social\Entity\OpignoPostInterface;
use Drupal\opigno_social\Services\UserConnectionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access control handler for the Opigno post entities.
 *
 * @package Drupal\opigno_social
 */
class OpignoPostAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The user connection manager.
   *
   * @var \Drupal\opigno_social\Services\UserConnectionManager
   */
  protected UserConnectionManager $connectionManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(UserConnectionManager $connections_manager, ...$default) {
    parent::__construct(...$default);
    $this->connectionManager = $connections_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('opigno_user_connection.manager'),
      $entity_type
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (!$entity instanceof OpignoPostInterface || !$this->connectionManager->socialsEnabled) {
      return AccessResult::forbidden();
    }

    $author_id = $entity->getAuthorId();
    $uid = (int) $account->id();
    if ($uid === 1 && !in_array($operation, ['edit', 'update'])) {
      return AccessResult::allowed();
    }

    $network = $this->connectionManager->getUserNetwork((int) $account->id());

    return match ($operation) {
      'view', 'view_label', 'like' => AccessResult::allowedIf(
        in_array($author_id, $network)
        || $author_id === $uid
      ),
      'comment_post', 'pin', 'hide' => AccessResult::allowedIf(
        (in_array($author_id, $network) || $author_id === $uid)
        && !$entity->isComment()
      ),
      'edit' => AccessResult::forbidden(),
      'delete' => AccessResult::allowedIf($author_id === $uid || $account->hasPermission('remove any post entities')),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIf($this->connectionManager->socialsEnabled);
  }

}
