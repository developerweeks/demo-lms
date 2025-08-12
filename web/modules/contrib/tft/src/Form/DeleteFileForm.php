<?php

namespace Drupal\tft\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\media\MediaInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Delete a file form.
 */
class DeleteFileForm extends BaseFolderForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tft_delete_file_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?GroupInterface $group = NULL,
    ?TermInterface $folder = NULL,
    ?MediaInterface $media = NULL,
  ) {

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-actions']],
    ];

    $mid = $media?->id();

    $name = $media?->getName();

    $form['title'] = [
      '#markup' => $this->t("Are you sure you want to delete the file @file?", [
        '@file' => $name,
      ]),
    ];

    $form['mid'] = [
      '#type' => 'hidden',
      '#value' => $mid,
    ];

    $form['group'] = [
      '#type' => 'hidden',
      '#value' => $group?->id(),
    ];

    $form['parent'] = [
      '#type' => 'hidden',
      '#value' => $folder?->id(),
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
      '#value' => $this->t('Delete'),
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
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $term = $this->entityTypeManager()->getStorage('media')->load($form_state->getValue('mid'));
    $term->delete();
  }

}
