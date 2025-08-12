<?php

namespace Drupal\opigno_social\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\opigno_social\Entity\PostTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form handler for post type forms.
 *
 * @package Drupal\opigno_social\Form
 */
class PostTypeForm extends BundleEntityFormBase {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * PostTypeForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager) {
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $type = $this->entity;
    if (!$type instanceof PostTypeInterface) {
      return $form;
    }

    $form['#title'] = $this->operation === 'add'
      ? $this->t('Add post type')
      : $this->t('Edit %label post type', [
        '%label' => $type->label(),
      ]);

    $form['name'] = [
      '#title' => $this->t('Name'),
      '#type' => 'textfield',
      '#default_value' => $type->label() ?? '',
      '#description' => $this->t('The human-readable name of the post type. This name must be unique.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $type->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => [$type->getEntityType()->getClass(), 'load'],
        'source' => ['name'],
      ],
      '#description' => $this->t('A unique machine-readable name for the post type. It must only contain lowercase letters, numbers, and underscores.'),
    ];

    $form['description'] = [
      '#title' => $this->t('Description'),
      '#type' => 'textarea',
      '#default_value' => $type->getDescription(),
    ];

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save');

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $type = $this->entity;

    if (!$type instanceof PostTypeInterface) {
      $msg = $this->t('Post bundle should implement PostTypeInterface, the post type can not be saved.');
      $this->messenger()->addError($msg);
      $this->logger('opigno_social')->error($msg);
    }

    $values = $form_state->getValues();
    $label = trim($values['name']);
    $args = ['%label' => $label];

    $type->set('id', trim($values['id']))
      ->set('label', $label)
      ->set('description', trim($values['description']));
    $status = $type->save();

    if ($status == SAVED_UPDATED) {
      $this->messenger()->addStatus($this->t('The post type %label has been updated.', $args));
    }
    elseif ($status == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('The post type %label has been added.', $args));
      $this->logger('opigno_social')->notice('Added post type %label.', $args);
    }

    $this->entityFieldManager->clearCachedFieldDefinitions();
    $redirect = Url::fromRoute('entity.opigno_post_type.collection');
    $form_state->setRedirectUrl($redirect);
  }

}
