<?php

namespace Drupal\opigno_social\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the Opigno post type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "opigno_post_type",
 *   label = @Translation("Post type"),
 *   label_collection = @Translation("Post types"),
 *   label_singular = @Translation("post type"),
 *   label_plural = @Translation("post types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count post type",
 *     plural = "@count post types",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\opigno_social\Form\PostTypeForm",
 *       "edit" = "Drupal\opigno_social\Form\PostTypeForm",
 *       "delete" = "Drupal\opigno_social\Form\PostTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "permissions" = "Drupal\user\Entity\EntityPermissionsRouteProvider",
 *     },
 *     "list_builder" = "Drupal\opigno_social\PostBundleListBuilder",
 *   },
 *   admin_permission = "administer opigno post types",
 *   config_prefix = "opigno_post_type",
 *   bundle_of = "opigno_post",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/opigno-social/post-type/add",
 *     "edit-form" = "/admin/opigno-social/post-type/{opigno_post_type}/edit",
 *     "delete-form" = "/admin/opigno-social/post-types/{opigno_post_type}/delete",
 *     "entity-permissions-form" = "/admin/opigno-social/post-type/{opigno_post_type}/permissions",
 *     "collection" = "/admin/structure/post-types",
 *   },
 *   config_export = {
 *     "label",
 *     "id",
 *     "description",
 *   }
 * )
 */
class PostType extends ConfigEntityBundleBase implements PostTypeInterface {

  /**
   * The machine name of the post entity bundle.
   *
   * @var string
   */
  protected string $id;

  /**
   * The human-readable name of the post entity bundle.
   *
   * @var string
   */
  protected string $label;

  /**
   * A brief description of the post entity bundle.
   *
   * @var string
   */
  protected string $description = '';

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if ($update) {
      // Clear the cached field definitions as some settings affect the field
      // definitions.
      \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    // Clear the bundle cache to reflect the removal.
    $storage->resetCache(array_keys($entities));
  }

}
