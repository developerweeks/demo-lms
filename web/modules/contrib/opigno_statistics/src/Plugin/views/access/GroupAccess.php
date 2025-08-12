<?php

namespace Drupal\opigno_statistics\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that provides group operation-based access control.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "opigno_group_access",
 *   title = @Translation("Group access"),
 *   help = @Translation("Access will be granted to users who are permitted to execute the selected operation on group.")
 * )
 */
class GroupAccess extends AccessPluginBase implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

  /**
   * The group entity from the route.
   *
   * @var \Drupal\group\Entity\GroupInterface|null
   */
  protected ?GroupInterface $group;

  /**
   * The group context from the route.
   *
   * @var \Drupal\Core\Plugin\Context\ContextInterface
   */
  protected ContextInterface $context;

  /**
   * {@inheritdoc}
   */
  public function __construct(ContextProviderInterface $context_provider, ...$default) {
    parent::__construct(...$default);

    $contexts = $context_provider->getRuntimeContexts(['group']);
    $this->context = $contexts['group'];
    $this->group = $this->context->getContextValue();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('group.group_route_context'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    $entity = $this->group ?? NULL;
    if ($entity instanceof GroupInterface) {
      $operation = $this->options['operation'];
      return $this->group->access($operation, $account);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $operation = $this->options['operation'];
    $route->setRequirement('_entity_access', $operation);

    // Upcast any %group path key the user may have configured so the
    // '_entity_access' access check will receive a properly loaded group.
    $route->setOption('parameters', ['group' => ['type' => 'entity:group']]);
  }

  /**
   * Returns the list of available group operations to check access.
   *
   * @return array
   *   The list of available group operations.
   */
  protected function getOperationsList(): array {
    return [
      'view' => $this->t('View'),
      'update' => $this->t('Update'),
      'delete' => $this->t('Delete'),
      'view statistics' => $this->t('View statistics'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    $op = $this->options['operation'];
    $operations = $this->getOperationsList();

    return $operations[$op] ?? $this->t('Not selected');
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['operation'] = ['default' => 'view'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['operation'] = [
      '#type' => 'select',
      '#options' => $this->getOperationsList(),
      '#title' => $this->t('Group operation'),
      '#default_value' => $this->options['operation'],
      '#description' => $this->t('Only users who are permitted to execute the selected operation on group will be able to access this display.<br /><strong>Warning:</strong> This will only work if there is a {group} parameter in the route. If not, the access will be denied.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::mergeMaxAges(Cache::PERMANENT, $this->context->getCacheMaxAge());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(['user'], $this->context->getCacheContexts());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->context->getCacheTags();
  }

}
