<?php

namespace Drupal\opigno_calendar\Plugin\views\area;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * Defines a views area plugin.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("opigno_calendar_region")
 */
class OpignoCalendarRegion extends AreaPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    $attributes = [
      'class' => ['use-ajax'],
      'data-dialog-type' => 'modal',
    ];

    $url = Url::fromRoute('opigno_calendar_event.add_event');

    $link = [
      '#type' => 'link',
      '#title' => $this->t('Add'),
      '#url' => $url,
      '#attributes' => $attributes,
    ];

    return [
      '#theme' => 'opigno_calendar_add_event',
      '#add_event_link' => $link,
    ];
  }

}
