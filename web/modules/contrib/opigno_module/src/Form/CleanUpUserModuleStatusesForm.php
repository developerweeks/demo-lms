<?php

namespace Drupal\opigno_module\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a UI to clean up user_module_status table.
 *
 * @package Drupal\opigno_module\Form
 */
class CleanUpUserModuleStatusesForm extends FormBase {

  /**
   * The DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * CleanUpUserModuleStatusesForm constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(Connection $database, MessengerInterface $messenger) {
    $this->database = $database;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_module_clean_up_user_module_status_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Batch size'),
      '#default_value' => 50,
      '#min' => 5,
      '#max' => 100,
      '#required' => TRUE,
    ];

    $form['started_before'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Started before'),
      '#default_value' => new DrupalDateTime(),
      '#description' => $this->t('Remove not finished module attempts that were started before the selected date if the module has already been completed.'),
    ];

    $form['caution'] = [
      '#markup' => Markup::create('<strong>' . $this->t('Caution: this action can not be undone.') . '</strong>'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Run batch'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $started_before = $form_state->getValue('started_before');
    // Get the list of all not finished module attempts.
    $query = $this->database->select('user_module_status', 'ums')
      ->fields('ums', ['id', 'module', 'user_id', 'learning_path'])
      ->condition('ums.finished', 0);

    if ($started_before instanceof DrupalDateTime) {
      $query->condition('ums.started', $started_before->getTimestamp(), '<');
    }

    $in_progress = $query->execute()->fetchAllAssoc('id');
    if (!$in_progress) {
      return;
    }

    // Prepare data for batch.
    $operations = [];
    $batch_size = $form_state->getValue('batch_size', 50);
    $chunks = array_chunk($in_progress, $batch_size);
    foreach ($chunks as $chunk) {
      $operations[] = [
        [$this, 'cleanUpModuleStatusesBatchCallback'],
        [$chunk],
      ];
    }

    // Provide all the operations and the finish callback to the batch.
    $batch = [
      'title' => $this->t('Processing not finished module status entities...'),
      'operations' => $operations,
      'finished' => [$this, 'batchFinishCallback'],
    ];

    batch_set($batch);
  }

  /**
   * Batch execution callback to clean up user_module_status table.
   *
   * @param array $data
   *   Batch data to be processed.
   * @param array $context
   *   Batch context.
   */
  public function cleanUpModuleStatusesBatchCallback(array $data, array &$context): void {
    if (!isset($context['results'])) {
      $context['results'] = [];
    }

    $deleted = 0;
    foreach ($data as $attempt) {
      $id = $attempt->id;

      // Check if there are other completed attempts for the given user, module
      // and LP.
      $completed_attempt = $this->database->select('user_module_status', 'ums')
        ->fields('ums', ['id'])
        ->condition('ums.user_id', $attempt->user_id)
        ->condition('ums.learning_path', $attempt->learning_path)
        ->condition('ums.module', $attempt->module)
        ->condition('ums.finished', 0, '>')
        ->orderBy('ums.id', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchCol();

      // Skip if there are no previously completed module attempts.
      if (!$completed_attempt) {
        continue;
      }

      $this->database->delete('user_module_status')
        ->condition('id', $id)
        ->execute();

      $deleted++;
      $context['results'][] = $id;
    }

    $context['message'] = $this->formatPlural(
      count($data),
      '1 record has been processed, @number deleted.',
      '@count records have been processed @number deleted.',
      ['@number' => $deleted]
    );
  }

  /**
   * Batch finish callback.
   *
   * @param bool $success
   *   TRUE if the update passed successfully.
   * @param array $results
   *   Contains individual results per operation.
   * @param array $operations
   *   Contains the unprocessed operations that failed or weren't touched yet.
   */
  public function batchFinishCallback(bool $success, array $results, array $operations): void {
    if ($success) {
      $this->messenger->addStatus($this->formatPlural(
        count($results),
        '1 unfinished module attempt has been removed.',
        '@count unfinished module attempts have been removed.')
      );
    }
    else {
      $this->messenger->addError($this->t('Batch finished with an error.'));
    }
  }

}
