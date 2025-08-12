<?php

namespace Drupal\tft\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Edit a term form.
 */
class EditFolderForm extends BaseFolderForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tft_edit_term_form';
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
    $name = $taxonomy_term?->getName();
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Name"),
      '#required' => TRUE,
      '#default_value' => $name,
      '#weight' => -10,
    ];
    $tid = $taxonomy_term?->id();
    $form['tid'] = [
      '#type' => 'hidden',
      '#value' => $tid,
    ];

    $form['group'] = [
      '#type' => 'hidden',
      '#value' => $group?->id(),
    ];

    $form['parent'] = [
      '#type' => 'hidden',
      '#value' => _tft_get_parent_tid($tid),
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
      '#value' => $this->t('Edit'),
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
    // Update the term name.
    $term = $this->entityTypeManager()->getStorage('taxonomy_term')->load($form_state->getValue('tid'));
    $term->setName($form_state->getValue('name'));
    $term->save();

    $this->messenger()->addMessage($this->t("The folder '@name' was updated.", [
      '@name' => $form_state->getValue('name'),
    ]));
  }

}
