<?php

namespace Drupal\opigno_calendar_event\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\opigno_calendar_event\CalendarEventInterface;

/**
 * Provide AJAX features for events.
 */
class CalendarEventController extends ControllerBase {

  /**
   * Display modal to add event.
   *
   * @param \Drupal\opigno_calendar_event\CalendarEventInterface|null $opigno_calendar_event
   *   The ID of opigno calendar event.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Open modal dialog response.
   */
  public function eventModal(?CalendarEventInterface $opigno_calendar_event = NULL): AjaxResponse {
    $event_storage = $this->entityTypeManager()->getStorage('opigno_calendar_event');
    if (!empty($opigno_calendar_event)) {
      $title = $this->t('Edit event');
      $event = $opigno_calendar_event;
    }
    else {
      $title = $this->t('Add event');
      $event = $event_storage->create([
        'type' => 'opigno_calendar_event',
        'uid' => $this->currentUser()->id(),
        'revision' => NULL,
      ]);
    }

    $content = $this->entityFormBuilder()->getForm($event);

    $build = [
      '#theme' => 'opigno_calendar_event_modal',
      '#title' => $title,
      '#body' => $content,
      '#close' => 1,
    ];

    $response = new AjaxResponse();

    $response->addCommand(new RemoveCommand('.modal-ajax'));
    $response->addCommand(new AppendCommand('body', $build));
    $response->addCommand(new InvokeCommand('.modal-ajax', 'modal', ['show']));
    return $response;
  }

}
