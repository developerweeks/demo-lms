<?php

namespace Drupal\tft\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Delete a term form.
 */
class DeleteFolderForm extends BaseFolderForm {

  /**
   * Check if the term has no files or child terms.
   */
  protected function checkTermIsDeletable($tid) {
    /** @var \Drupal\taxonomy\TermStorage $storage */
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = $storage->loadTree('tft_tree', $tid, 1);

    if (!empty($terms)) {
      return FALSE;
    }

    $fids = $this->entityTypeManager()->getStorage('media')
      ->getQuery()
      ->accessCheck()
      ->condition('bundle', 'tft_file')
      ->condition('tft_folder.target_id', $tid)
      ->execute();

    if (!empty($fids)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tft_delete_term_form';
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
    $tid = $taxonomy_term?->id();
    $name = $taxonomy_term?->getName();

    $cancel = [
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

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-actions']],
    ];

    // Check that this term has no child terms or files.
    if (!$this->checkTermIsDeletable($tid)) {
      $form[] = [
        '#markup' => $this->t("<em>@name</em> contains files and/or child folders. Move or delete these before deleting this folder.", [
          '@name' => $name,
        ]),
      ];

      $form['actions']['cancel'] = $cancel;

      return $form;
    }

    $form['title'] = [
      '#markup' => $this->t("Are you sure you want to delete the folder @term ?", [
        '@term' => $name,
      ]),
    ];

    $form['tid'] = [
      '#type' => 'hidden',
      '#value' => $tid,
    ];

    $form['group'] = [
      '#type' => 'hidden',
      '#value' => $group->id(),
    ];

    $form['parent'] = [
      '#type' => 'hidden',
      '#value' => _tft_get_parent_tid($tid),
    ];

    $form['actions']['cancel'] = $cancel;

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
    $term = $this->entityTypeManager()->getStorage('taxonomy_term')->load($form_state->getValue('tid'));
    $term->delete();
  }

}
