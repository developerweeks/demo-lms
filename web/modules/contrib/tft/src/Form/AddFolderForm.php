<?php

namespace Drupal\tft\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Add a term form.
 */
class AddFolderForm extends BaseFolderForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tft_add_term_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?GroupInterface $group = NULL,
    ?TermInterface $taxonomy_term = NULL,
    ) {

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Folder name"),
      '#required' => TRUE,
      '#weight' => -10,
      '#placeholder' => $this->t('Add name'),
    ];

    $form['parent'] = [
      '#type' => 'hidden',
      '#value' => $taxonomy_term?->id(),
    ];

    $form['group'] = [
      '#type' => 'hidden',
      '#value' => $group?->id(),
    ];

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-actions']],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t("Cancel"),
      '#attributes' => [
        'class' => [
          'use-ajax',
          'btn-cancel',
          'btn',
          'btn-rounded',
        ],
      ],
      '#url' => Url::fromRoute('opigno_learning_path.close_modal'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add folder'),
      '#button_type' => 'primary',
      '#attributes' => [
        'class' => [
          'use-ajax',
          'btn_create',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitFormAjax'],
        'event' => 'click',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // If the user can only add terms to a Group.
    if (!$this->currentUser()->hasPermission(TFT_ADD_TERMS)) {
      if (!_tft_term_access($form_state->getValue('parent'))) {
        $form_state->setErrorByName('name');
        $this->messenger()->addMessage($this->t("You must select a parent folder that is part of a group you're a member of."), 'error');
      }
    }

    // Check for forbidden characters.
    if (strpos($form_state->getValue('name'), ',') !== FALSE
      || strpos($form_state->getValue('name'), '+') !== FALSE) {
      $form_state->setErrorByName('name', $this->t("The following characters are not allowed: ',' (comma) and +"));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $tid = $form_state->getValue('parent');
    $name = $form_state->getValue('name');

    $term = $this->entityTypeManager()->getStorage('taxonomy_term')->create([
      'vid' => 'tft_tree',
      'name' => $name,
      'parent' => $tid,
    ]);
    $term->save();

    if (empty($tid)) {
      $gid = $form_state->getValue('group');
      $group = $this->entityTypeManager()->getStorage('group')->load($gid);
      $group->set('field_learning_path_folder', [
        'target_id' => $term->id(),
      ]);
    }
  }

}
