<?php

namespace Drupal\opigno_social\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the form handler to delete Opigno post bundles.
 *
 * @package Drupal\opigno_social\Form
 */
class PostTypeDeleteForm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $num_entities = $this->entityTypeManager
      ->getStorage('opigno_post_type')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $entity->id())
      ->count()
      ->execute();

    if ($num_entities) {
      $form['#title'] = $this->getQuestion();
      $form['description'] = [
        '#markup' => $this->formatPlural($num_entities,
          '%type is used by 1 entity on your site. You can not remove this bundle until you have removed all related entities.',
          '%type is used by @count entities on your site. You can not remove this bundle until you have removed all related entities.',
          ['%type' => $entity->label()]
        ),
      ];

      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

}
