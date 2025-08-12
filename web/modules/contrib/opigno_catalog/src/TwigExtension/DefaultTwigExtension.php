<?php

namespace Drupal\opigno_catalog\TwigExtension;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\group\Entity\Group;
use Drupal\opigno_catalog\StyleService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The twig extension for the catalog.
 *
 * @package Drupal\opigno_catalog\TwigExtension
 */
class DefaultTwigExtension extends AbstractExtension {

  use StringTranslationTrait;

  /**
   * Theme extension list service.
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList
   */
  protected ThemeExtensionList $themeList;

  /**
   * A module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The Opigno catalog style service.
   *
   * @var \Drupal\opigno_catalog\StyleService
   */
  protected StyleService $styleService;

  /**
   * The current request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * DefaultTwigExtension constructor.
   *
   * @param \Drupal\Core\Extension\ThemeExtensionList $theme_list
   *   Theme list service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\opigno_catalog\StyleService $style_service
   *   The catalog style service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(
    ThemeExtensionList $theme_list,
    ModuleHandlerInterface $module_handler,
    AccountInterface $account,
    StyleService $style_service,
    RequestStack $request_stack
  ) {
    $this->themeList = $theme_list;
    $this->moduleHandler = $module_handler;
    $this->account = $account;
    $this->styleService = $style_service;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction(
        'opigno_catalog_get_style',
        [$this, 'getRowStyle']
      ),
      new TwigFunction(
        'opigno_catalog_is_member',
        [$this, 'isMember']
      ),
      new TwigFunction(
        'opigno_catalog_is_started',
        [$this, 'isStarted']
      ),
      new TwigFunction(
        'opigno_catalog_get_default_image',
        [$this, 'getDefaultImage']
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'opigno_catalog.twig.extension';
  }

  /**
   * Gets row style.
   *
   * @return string
   *   The row style.
   */
  public function getRowStyle(): string {
    $style = $this->styleService->getStyle();

    return ($style === 'line') ? 'style-line' : 'style-block';
  }

  /**
   * Checks if user is a member of group.
   *
   * @param int|string $group_id
   *   Group ID.
   *
   * @return bool
   *   If a user is a member of group.
   */
  public function isMember($group_id): bool {
    $group = Group::load($group_id);

    return (bool) $group->getMember($this->account);
  }

  /**
   * Checks if training started.
   *
   * @param int|string $group_id
   *   Group ID.
   *
   * @return bool
   *   If the training started by user.
   */
  public function isStarted($group_id): bool {
    $group = Group::load($group_id);

    return (bool) opigno_learning_path_started($group, $this->account);
  }

  /**
   * Returns default image.
   *
   * @param string $type
   *   Entity or group type to get the image for.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $title
   *   The entity label.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   Markup to build the default image.
   */
  public function getDefaultImage(string $type, $title) {
    $path = $this->moduleHandler->getModule('opigno_catalog')->getPath();
    $title = $this->t('Picture of @title', ['@title' => $title]);
    switch ($type) {
      case 'course':
        $img = '<img src="' . $this->request->getBasePath() . '/' . $path . '/img/img_course.png" alt="' . $title . '">';
        break;

      case 'module':
        $img = '<img src="' . $this->request->getBasePath() . '/' . $path . '/img/img_module.png" alt="' . $title . '">';
        break;

      case 'learning_path':
        $theme_path = $this->themeList->getPath('aristotle');
        $img = '<img src="' . $this->request->getBasePath() . '/' . $theme_path . '/dist/images/content/training.svg" alt="' . $title . '">';
        break;

      case 'certificate_image':
        $theme_path = $this->themeList->getPath('aristotle');
        $img = '<img src="' . $this->request->getBasePath() . '/' . $theme_path . '/dist/images/design/certificate.svg" alt="' . $title . '">';
        break;

      default:
        $img = NULL;
        break;
    }

    return Markup::create($img);
  }

}
