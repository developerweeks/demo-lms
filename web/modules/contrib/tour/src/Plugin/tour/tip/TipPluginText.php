<?php

namespace Drupal\tour\Plugin\tour\tip;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\tour\Attribute\Tip;
use Drupal\tour\TipPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays some text as a tip.
 */
#[Tip(
  id: 'text',
  title: new TranslatableMarkup('Text'),
)]
class TipPluginText extends TipPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The body text which is used for render of this Text Tip.
   *
   * @var string
   */
  protected string $body;

  /**
   * Constructs a \Drupal\tour\Plugin\tour\tip\TipPluginText object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected Token $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('token'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'body' => '',
      'selector' => NULL,
      'position' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getBody(): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->token->replace($this->get('body')),
      '#attributes' => [
        'class' => ['tour-tip-body'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $id = $this->get('id');
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('The tip title should match the title of the component the selector field is referencing. For the initial general tip with the empty selector field, use the h1 of the page. Each additional tip without a selector should have a unique descriptive title.'),
      '#required' => TRUE,
      '#default_value' => $this->get('label'),
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $id,
      '#disabled' => !empty($id),
      '#machine_name' => [
        'exists' => '\Drupal\tour\Entity\Tour::load',
      ],
    ];
    $form['plugin'] = [
      '#type' => 'value',
      '#value' => $this->get('plugin'),
    ];
    $form['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#default_value' => $this->get('weight'),
      '#attributes' => [
        'class' => ['tip-order-weight'],
      ],
    ];

    $form['selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Selector'),
      '#default_value' => $this->get('selector'),
      '#description' => $this->t('This can be any selector string or a DOM element (e.g,. .some .selector-path or #some-id). If you don’t specify the element will appear in the middle of the screen.'),
    ];

    $form['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#options' => [
        'auto' => $this->t('Auto'),
        'auto-start' => $this->t('Auto start'),
        'auto-end' => $this->t('Auto end'),
        'top' => $this->t('Top'),
        'top-start' => $this->t('Top start'),
        'top-end' => $this->t('Top end'),
        'bottom' => $this->t('Bottom'),
        'bottom-start' => $this->t('Bottom start'),
        'bottom-end' => $this->t('Bottom end'),
        'right' => $this->t('Right'),
        'right-start' => $this->t('Right start'),
        'right-end' => $this->t('Right end'),
        'left' => $this->t('Left'),
        'left-start' => $this->t('Left start'),
        'left-end' => $this->t('Left end'),
      ],
      '#default_value' => $this->get('position'),
      '#states' => [
        'visible' => [
          ':input[name="selector"]' => ['!value' => ''],
        ],
      ],
    ];
    $tags = Xss::getAdminTagList();
    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#required' => TRUE,
      '#default_value' => $this->get('body'),
      '#description' => $this->t('You could use the following tags: %s', ['%s' => implode(', ', $tags)]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

}
