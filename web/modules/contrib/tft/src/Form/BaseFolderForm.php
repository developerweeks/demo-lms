<?php

namespace Drupal\tft\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base folder form.
 */
abstract class BaseFolderForm extends FormBase {

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Creates a BaseFolderForm object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Get entity_type.manager service.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   Entity_type.manager service.
   */
  public function entityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager;
  }

  /**
   * Ajax callback for the form submit.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitFormAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $tid = $form_state->getValue('parent');
    $gid = $form_state->getValue('group');
    $url = Url::fromRoute('tft.group.folder', [
      'group' => $gid,
      'taxonomy_term' => $tid,
    ]);
    $response->addCommand(new RedirectCommand($url->toString()));
    $response->addCommand(new InvokeCommand('.modal', 'modal', ['hide']));
    return $response;
  }

}
