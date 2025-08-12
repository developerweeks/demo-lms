<?php

namespace Drupal\opigno_statistics\Plugin\views\field;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to output user LP achievement time.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("opigno_statistics_user_achievement_time")
 */
class UserAchievementTime extends NumericField {

  /**
   * Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public function __construct(DateFormatterInterface $date_formatter, ...$default) {
    parent::__construct(...$default);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('date.formatter'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $time = (int) parent::render($values);

    return $time ? $this->dateFormatter->formatInterval($time) : '-';
  }

}
