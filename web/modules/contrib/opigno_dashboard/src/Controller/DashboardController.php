<?php

namespace Drupal\opigno_dashboard\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\opigno_dashboard\BlockServiceInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for all the actions of the Learning Path manager app.
 */
class DashboardController extends ControllerBase {

  /**
   * The block service.
   *
   * @var \Drupal\opigno_dashboard\BlockServiceInterface
   */
  protected $blockService;

  /**
   * The database connection manager.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The users entity query.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface|null
   */
  protected $userEntityQuery = NULL;

  /**
   * DashboardController constructor.
   *
   * @param \Drupal\opigno_dashboard\BlockServiceInterface $block_service
   *   Opigno dashboard blocks service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    BlockServiceInterface $block_service,
    AccountInterface $account,
    MessengerInterface $messenger,
    Connection $database,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->blockService = $block_service;
    $this->currentUser = $account;
    $this->messenger = $messenger;
    $this->database = $database;
    $this->configFactory = $config_factory;

    try {
      $this->userEntityQuery = $entity_type_manager->getStorage('user')->getQuery();
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_dashboard_exception', $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('opigno_dashboard.block'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Get the default blocks.
   *
   * @return array
   *   The default blocks.
   */
  public function dashboardDefaultBlocks() {
    return [];
  }

  /**
   * Returns positioning.
   *
   * @param int|string|null $uid
   *   The user ID to get the positioning for.
   * @param bool $default
   *   Should the default positioning be used or not.
   * @param bool $user_default
   *   Should the user default positioning be used or not.
   *
   * @return array|\Symfony\Component\HttpFoundation\JsonResponse
   *   Blocks positioning.
   */
  public function getPositioning($uid = NULL, bool $default = FALSE, bool $user_default = FALSE) {
    return $this->blockService->getPositioning($uid, $default, $user_default);
  }

  /**
   * Sets the positioning.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function setPositioning(Request $request): JsonResponse {
    $datas = json_decode($request->getContent());
    // Remove the first column.
    unset($datas->positions[0]);

    try {
      $this->database->merge('opigno_dashboard_positioning')
        ->key(['uid' => $this->currentUser->id()])
        ->fields(['columns' => (int) $datas->columns])
        ->fields(['positions' => json_encode($datas->positions)])
        ->execute();
    }
    catch (\Exception $e) {
      watchdog_exception('opigno_dashboard_exception', $e);
    }

    return new JsonResponse(NULL, Response::HTTP_OK);
  }

  /**
   * Returns blocks contents.
   */
  public function getBlocksContents() {
    $data = $this->blockService->getDashboardBlocksContents();

    return new JsonResponse([
      'blocks' => $data['blocks'] ?? [],
      'drupalSettings' => $data['attachments']['drupalSettings'] ?? [],
    ]);
  }

  /**
   * Returns the default positioning.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The default positioning.
   */
  public function getDefaultPositioning(): JsonResponse {
    $positioning = $this->blockService->getPositioning(NULL, TRUE);

    return new JsonResponse([
      'positions' => $positioning['positions'],
      'columns' => $positioning['columns'],
    ], Response::HTTP_OK);
  }

  /**
   * Sets default positioning.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function setDefaultPositioning(Request $request): JsonResponse {
    $datas = json_decode($request->getContent());
    unset($datas->positions[0]);

    // Fix critical symbols.
    if (!empty($datas->positions)) {
      foreach ($datas->positions as &$position) {
        if (!empty($position)) {
          foreach ($position as &$block) {
            $block->admin_label = str_replace("'", "`", $block->admin_label);
          }
        }
      }
    }

    try {
      $config = $this->configFactory->getEditable('opigno_dashboard.default.settings');
      $config->set('positions', json_encode($datas->positions));
      $config->set('columns', (int) $datas->columns);
      $config->save();
    }
    catch (\Exception $e) {
      watchdog_exception('opigno_dashboard_exception', $e);
      $this->messenger->addMessage($e->getMessage(), 'error');
    }

    return new JsonResponse();
  }

  /**
   * Restore dashboard settings to defaults for the current user.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function restoreToDefaultAll(): JsonResponse {
    $positioning = $this->blockService->getPositioning(NULL, TRUE, TRUE);
    unset($positioning['positions'][0]);

    if (!$this->userEntityQuery instanceof QueryInterface) {
      return new JsonResponse(NULL, 400);
    }

    $uids = $this->userEntityQuery->accessCheck()->execute();
    unset($uids[0]);
    if (!$uids) {
      return new JsonResponse(NULL, 400);
    }

    foreach ($uids as $uid) {
      try {
        $this->database->merge('opigno_dashboard_positioning')
          ->key(['uid' => $uid])
          ->fields([
            'columns' => (int) $positioning['columns'],
            'positions' => json_encode($positioning['positions']),
          ])
          ->execute();
      }
      catch (\Exception $e) {
        watchdog_exception('opigno_dashboard_exception', $e);
      }
    }

    return new JsonResponse();
  }

  /**
   * Prepares an ajax response to display a popup with online users listing.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response to display online users listing popup.
   */
  public function getOnlineUsers(): AjaxResponse {
    // Can not use buildUsersListingPopup() because of the specific label
    // generation (need to load a view to get the total number of the rows, no
    // need to do this twice).
    $response = new AjaxResponse();
    $view = Views::getView('who_s_online');
    if (!$view instanceof ViewExecutable) {
      return $response->setStatusCode(400, 'The requested view does not exist.');
    }

    $list = $view->executeDisplay('full_list_block');
    if (!$list) {
      return $response->setStatusCode(400, 'The requested view display does not exist.');
    }

    $content = [
      '#theme' => 'opigno_dashboard_popup',
      '#title' => $this->formatPlural($view->total_rows, '@count user online', '@count users online'),
      '#body' => $list,
    ];

    $response->addCommand(new RemoveCommand('.modal-ajax'));
    $response->addCommand(new AppendCommand('body', $content));
    $response->addCommand(new InvokeCommand('.modal-ajax', 'modal', ['show']));

    return $response;
  }

  /**
   * Prepares an ajax response to display a popup with new users listing.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response to display new users listing popup.
   */
  public function getNewUsers(): AjaxResponse {
    return $this->buildUsersListingPopup('who_s_new', 'full_list_block', $this->t('New users'));
  }

  /**
   * Prepares an ajax response to display a popup with pending memberships list.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response to display pending memberships listing popup.
   */
  public function getPendingMemberships(): AjaxResponse {
    return $this->buildUsersListingPopup('opigno_group_members', 'full_list_block', $this->t('Pending memberships'));
  }

  /**
   * Prepares an ajax response to display a popup with users listing.
   *
   * @param string $view_id
   *   A user listing view ID.
   * @param string $display_id
   *   A user listing view display ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   A text that will be displayed as an aria-label popup attribute.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response to display a popup with users listing.
   */
  private function buildUsersListingPopup(string $view_id, string $display_id, TranslatableMarkup $label): AjaxResponse {
    $response = new AjaxResponse();
    $view = Views::getView($view_id);
    if (!$view instanceof ViewExecutable) {
      return $response->setStatusCode(400, 'The requested view does not exist.');
    }

    $list = $view->executeDisplay($display_id);
    if (!$list) {
      return $response->setStatusCode(400, 'The requested view display does not exist.');
    }

    $content = [
      '#theme' => 'opigno_dashboard_popup',
      '#title' => $label,
      '#body' => $list,
    ];

    $response->addCommand(new RemoveCommand('.modal-ajax'));
    $response->addCommand(new AppendCommand('body', $content));
    $response->addCommand(new InvokeCommand('.modal-ajax', 'modal', ['show']));

    return $response;
  }

}
