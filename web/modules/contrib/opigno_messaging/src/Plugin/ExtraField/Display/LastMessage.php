<?php

namespace Drupal\opigno_messaging\Plugin\ExtraField\Display;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the extra field to show the last thread message.
 *
 * @ExtraFieldDisplay(
 *   id = "opigno_last_thread_message",
 *   label = @Translation("Opigno: last thread message"),
 *   bundles = {
 *     "private_message_thread.*"
 *   },
 * )
 */
class LastMessage extends ExtraFieldDisplayFormattedBase implements ContainerFactoryPluginInterface {

  /**
   * Date formatter service.
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
  public function viewElements(ContentEntityInterface $entity) {
    if (!$entity instanceof PrivateMessageThreadInterface) {
      return $this->emptyField();
    }

    $messages = $entity->getMessages();
    $last_message = end($messages);

    if (!$last_message instanceof PrivateMessageInterface) {
      return $this->emptyField();
    }

    $text_value = $last_message->get('message')->getValue();
    $text_value = reset($text_value);
    $text = $text_value['value'] ?? '';
    $format = $text_value['format'] ?? 'basic_html';

    $members = $entity->getMembersId();
    $owner = $last_message->getOwner();
    $title = '';
    if (!empty($owner)) {
      $title = $owner->getDisplayName();
    }
    if (count($members) > 2) {
      $discussion_name = $entity->get('field_pm_subject')->getString() ?? '';
      $title = $discussion_name ? $discussion_name . ' - ' . $title : $title;
    }

    return [
      '#theme' => 'opigno_last_thread_message',
      '#title' => $title,
      '#text' => check_markup($text, $format),
      '#time' => $this->dateFormatter->format($last_message->getCreatedTime(), 'date_short_with_time'),
      '#url' => Url::fromRoute('entity.private_message_thread.canonical', [
        'private_message_thread' => $entity->id(),
      ])->toString(),
      '#cache' => [
        'tags' => $entity->getCacheTags(),
        'contexts' => $entity->getCacheContexts(),
      ],
    ];
  }

  /**
   * Marks the field as empty.
   *
   * @return array
   *   The empty field data.
   */
  protected function emptyField(): array {
    $this->isEmpty = TRUE;
    return ['#cache' => ['max-age' => 0]];
  }

}
