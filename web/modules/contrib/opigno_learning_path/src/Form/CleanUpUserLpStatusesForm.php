<?php

namespace Drupal\opigno_learning_path\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a UI to clean up user_lp_status table.
 *
 * @package Drupal\opigno_learning_path\Form
 */
class CleanUpUserLpStatusesForm extends FormBase {

  /**
   * The DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * CleanUpUserLpStatusesForm constructor.
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
    return 'opigno_learning_path_clean_up_user_lp_status_form';
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
      '#description' => $this->t('Remove not finished LP attempts started before the selected date if the training has already been completed.'),
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
    // Get the list of all LP attempts in status "in progress".
    $query = $this->database->select('user_lp_status', 'uls')
      ->fields('uls', ['id', 'gid', 'uid', 'started'])
      ->condition('uls.status', 'in progress');

    if ($started_before instanceof DrupalDateTime) {
      $query->condition('started', $started_before->getTimestamp(), '<');
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
        [$this, 'cleanUpLpStatusesBatchCallback'],
        [$chunk],
      ];
    }

    // Provide all the operations and the finish callback to our batch.
    $batch = [
      'title' => $this->t('Processing not finished LP status entities...'),
      'operations' => $operations,
      'finished' => [$this, 'batchFinishCallback'],
    ];

    batch_set($batch);
  }

  /**
   * Batch execution callback to clean up user_lp_status table.
   *
   * @param array $data
   *   Batch data to be processed.
   * @param array $context
   *   Batch context.
   */
  public function cleanUpLpStatusesBatchCallback(array $data, array &$context): void {
    if (!isset($context['results'])) {
      $context['results'] = [];
    }

    $deleted = 0;
    foreach ($data as $attempt) {
      $id = $attempt->id;
      $uid = $attempt->uid;
      $gid = $attempt->gid;
      $timestamp = $attempt->started;

      // Check if there are other completed attempts for the given user and LP.
      $completed_attempt = $this->database->select('user_lp_status', 'uls')
        ->fields('uls', ['id'])
        ->condition('uls.uid', $uid)
        ->condition('uls.gid', $gid)
        ->condition('uls.status', ['passed', 'failed'], 'IN')
        ->orderBy('uls.id', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchCol();

      // Skip if there are no previously completed LP attempts.
      if (!$completed_attempt) {
        continue;
      }

      $completed_attempt_id = reset($completed_attempt);
      // Delete all user module attempts related to the not finished LP attempt.
      // That's impossible to rely on "lp_status" field because for old module
      // attempts it was set automatically to the latest LPStatus entity, see
      // opigno_module_update_9004().
      $this->database->delete('user_module_status')
        ->condition('lp_status', $id)
        ->condition('started', $timestamp, '>=')
        ->execute();

      // For the remaining module attempts connected to the not finished LP
      // attempt change the linked lp_status to the latest completed one.
      $this->database->update('user_module_status')
        ->fields(['lp_status' => $completed_attempt_id])
        ->condition('lp_status', $id)
        ->execute();

      // Delete the unfinished attempt.
      $this->database->delete('user_lp_status')
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
        '1 unfinished LP attempt has been removed.',
        '@count unfinished LP attempts have been removed.')
      );
    }
    else {
      $this->messenger->addError($this->t('Batch finished with an error.'));
    }
  }

}
