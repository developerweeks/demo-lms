<?php

namespace Drupal\opigno_social\Form;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\opigno_social\Entity\OpignoPostInterface;
use Drupal\opigno_social\Services\OpignoPostsManager;
use Drupal\opigno_social\Services\OpignoPostsManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the Opigno post entity form.
 *
 * @package Drupal\opigno_social\Form
 */
class PostForm extends ContentEntityForm {

  /**
   * The duplicate of the form entity.
   *
   * This is needed to be able to rebuild the form with ajax.
   *
   * @var \Drupal\opigno_social\Entity\OpignoPostInterface|null
   */
  protected ?OpignoPostInterface $postEntity = NULL;

  /**
   * The CSRF token generator service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected CsrfTokenGenerator $csrfToken;

  /**
   * The Opigno posts manager service.
   *
   * @var \Drupal\opigno_social\Services\OpignoPostsManagerInterface
   */
  protected OpignoPostsManagerInterface $postsManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The loaded current user entity.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * The user entity builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected EntityViewBuilderInterface $userViewBuilder;

  /**
   * Opigno post entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $storage;

  /**
   * Opigno post entity view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected EntityViewBuilderInterface $viewBuilder;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(
    CsrfTokenGenerator $csrf_token,
    OpignoPostsManagerInterface $post_manager,
    RendererInterface $renderer,
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $account,
    ...$default
  ) {
    parent::__construct(...$default);
    $this->csrfToken = $csrf_token;
    $this->postsManager = $post_manager;
    $this->renderer = $renderer;
    $this->entityTypeManager = $entity_type_manager;
    $this->userViewBuilder = $entity_type_manager->getViewBuilder('user');
    $uid = (int) $account->id();
    $this->user = $entity_type_manager->getStorage('user')->load($uid);
    $this->storage = $entity_type_manager->getStorage('opigno_post');
    $this->viewBuilder = $entity_type_manager->getViewBuilder('opigno_post');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('csrf_token'),
      $container->get('opigno_posts.manager'),
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#attached']['library'][] = 'opigno_social/post_form_behavior';

    /** @var \Drupal\opigno_social\Entity\OpignoPostInterface $post */
    $post = $this->entity;
    $this->addHiddenField($form, $form_state, 'parent');
    $is_comment = $post->isComment();
    $with_attachment = FALSE;

    if ($is_comment) {
      // Add the custom text field ID for a comment form.
      $pid = $post->getParentId();
      $form['#attributes']['class'][] = 'opigno-create-comment-form-' . $pid;
      $form['text']['widget'][0]['#attributes']['id'] = 'opigno-comment-text-' . $pid;
    }
    else {
      // Hide attachment fields.
      $hidden_fields = [
        'attachment_entity_id',
        'attachment_entity_type',
        'attachment_type',
      ];

      foreach ($hidden_fields as $field) {
        $this->addHiddenField($form, $form_state, $field);
      }

      $with_attachment = $post->getAttachmentEntityId();
      $form['text']['widget'][0]['#attributes']['id'] = $with_attachment ? 'create-share-post-textfield' : 'create-post-textfield';
    }

    $wrapper_id = $is_comment ? 'opigno-comment-form-wrapper' : 'opigno-post-form-wrapper';
    $form['#prefix'] = "<div id='$wrapper_id' class='opigno-post-form-wrapper'>";
    $form['#suffix'] = '</div>';
    $form['#attributes']['class'] = $with_attachment
      ? ['views-exposed-form', 'opigno-create-share-post-form']
      : ['comment-form'];

    // Add the current user info.
    $author = $this->userViewBuilder->view($this->user, 'post_author');
    $form['author'] = [
      '#type' => 'markup',
      '#markup' => $this->renderer->renderPlain($author),
      '#weight' => -20,
    ];
    if ($with_attachment) {
      $form['author']['#prefix'] = '<div class="author-info-wrapper"';
      $form['author']['#suffix'] = '<div class="comment-item__name">' . $this->user->getDisplayName() . '</div></div>';

      // Add hint text for popup form.
      $form['hint_text'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Only visible to connections'),
        '#attributes' => [
          'class' => ['post-hint'],
        ],
        '#weight' => 50,
      ];
    }
    else {
      $form['#theme'] = 'opigno_post__form';
    }

    // Update the text field displaying.
    $form['text']['#prefix'] = '<div class="opigno-post-form-text-container">';
    $form['text']['#suffix'] = '</div>';
    $form['text']['widget'][0]['#format'] = OpignoPostInterface::POST_TEXT_FORMAT;
    $form['text']['widget'][0]['#placeholder'] = $this->t('Write something');
    $form['text']['widget'][0]['#rows'] = 3;
    $form['text']['widget'][0]['#cols'] = 0;
    $form['text']['widget'][0]['#title_display'] = 'invisible';
    $form['text']['widget'][0]['#attributes']['class'][] = 'form-text';
    $form['text']['widget'][0]['#attributes']['autocomplete'] = 'off';
    $form['text']['widget']['#after_build'][] = [$this, 'hideTextFormatHelp'];
    $form['text']['#attributes']['class'][] = 'form-item';

    // Ajaxify the form submit.
    $form['actions']['#weight'] = 60;
    $form['actions']['submit']['#value'] = $this->t('Post');
    $form['actions']['submit']['#attributes'] = [
      'class' => ['use-ajax-submit', 'use-ajax', 'post-form-submit'],
    ];
    $form['actions']['submit']['#ajax'] = [
      'callback' => '::ajaxSubmit',
      'wrapper' => 'opigno-create-post-form-wrapper',
    ];

    // Add the ajax callback url if the form is rendered with ajax.
    if ($this->getRequest()->isXmlHttpRequest()) {
      $url = $this->getAjaxFormActionUrl();
      $form['#action'] = $url;
      $form['actions']['submit']['#ajax']['url'] = $url;

      // Set field values from form state.
      foreach ($form_state->getValues() as $field => $value) {
        $form[$field]['#default_value'] = $value;
      }
    }

    // Add attached content (training/badge/certificate).
    if ($with_attachment) {
      $attachment = $this->postsManager->renderPostAttachment($post);
      if ($attachment) {
        $form['attachment'] = [
          '#markup' => $this->renderer->renderPlain($attachment),
          '#weight' => 45,
        ];
      }
    }

    // Add the awards links.
    if (!$with_attachment && !$is_comment) {
      $form['footer']['#attributes']['class'][] = 'awards-list';
      $form['footer']['#weight'] = 45;
      $form['footer'] += $this->getAttachmentLinks();
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);

    // Rebuild the form.
    if ($this->entity instanceof OpignoPostInterface) {
      $this->postEntity = $this->entity;
    }

    $values = [
      'type' => $this->postEntity->bundle(),
    ];
    if ($this->postEntity->isComment()) {
      $values['parent'] = $this->postEntity->getParentId();
    }
    $this->entity = $this->storage->create($values);
    // Clear user input.
    $input = $form_state->getUserInput();
    $clean_keys = $form_state->getCleanValueKeys();
    $clean_keys[] = 'ajax_page_state';

    foreach ($input as $key => $item) {
      if (!in_array($key, $clean_keys) && substr($key, 0, 1) !== '_') {
        unset($input[$key]);
      }
    }

    $form_state->setUserInput($input);
    $form_state->setValues([]);
    $form_state->setRebuild();
    $form_state->setStorage([]);
  }

  /**
   * The ajax form submit callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The submit ajax response.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $post = $this->postEntity ?? $this->entity;
    if (!$post instanceof OpignoPostInterface) {
      return $response->setStatusCode(400, $this->t('The entity should implement OpignoPostInterface.'));
    }

    $is_comment = $post->isComment();
    $textfield_selector = $is_comment
      ? 'opigno-comment-text-' . $post->getParentId()
      : $form['text']['widget'][0]['#attributes']['id'];
    $textfield_id = '#' . $textfield_selector;
    $error_msg_id = $textfield_selector . '-error-msg';

    // Check if there are any errors and display them.
    if ($form_state->getErrors()) {
      $this->messenger()->deleteByType('error');
      $error_msg = "<div id='$error_msg_id' class='error-msg'>{$this->t('Add a text to publish your post.')}</div>";

      return $response->addCommand(new InvokeCommand($textfield_id, 'addClass', ['error']))
        ->addCommand(new RemoveCommand('#' . $error_msg_id))
        ->addCommand(new AfterCommand($textfield_id, $error_msg))
        ->addCommand(new InvokeCommand($textfield_id, 'focus'))
        ->setStatusCode(400, $this->t('The form is not validated'));
    }

    if ($is_comment) {
      return $this->ajaxCreateComment($form, $form_state);
    }

    // Add the post to the feed, clean the main form, close the popup if needed.
    $content = $this->viewBuilder->view($post);
    $response->addCommand(new AfterCommand('.opigno-pinned-post:last', $content))
      ->addCommand(new InvokeCommand($textfield_id, 'val', ['']))
      ->addCommand(new ReplaceCommand('#opigno-post-form-wrapper', $form))
      ->addCommand(new RemoveCommand('#' . $error_msg_id))
      ->addCommand(new InvokeCommand($textfield_id, 'removeClass', ['error']))
      ->addCommand(new InvokeCommand('.modal', 'modal', ['hide']))
      ->addCommand(new RemoveCommand('.modal-backdrop'))
      ->addCommand(new InvokeCommand($textfield_id, 'focus'));

    // Set the post as the last viewed if no other posts were added between the
    // last time when the user accessed the social wall and the post creation.
    if (!$this->postsManager->getNewPosts()) {
      $this->postsManager->setLastViewedPostId((int) $post->id());
    }

    return $response;
  }

  /**
   * The ajax form submit callback in case of comment creation.
   *
   * @param array $form
   *   The called form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The submit ajax response.
   */
  public function ajaxCreateComment(array &$form, FormStateInterface $form_state): AjaxResponse {
    $parent = $this->postEntity->getParent();
    $response = new AjaxResponse();

    if (!$parent instanceof OpignoPostInterface || !$parent->access('view')) {
      return $response->setStatusCode(400, $this->t('The parent post does not exist or inaccessible.'));
    }

    $pid = (int) $parent->id();
    // Invalidate an appropriate cache tags.
    $tags = array_merge($parent->getCacheTagsToInvalidate(), [
      OpignoPostsManager::OPIGNO_POST_COMMENTS_CACHE_PREFIX . $pid,
    ]);
    Cache::invalidateTags($tags);

    $textfield_id = '#opigno-comment-text-' . $pid;
    $response->addCommand(new InvokeCommand($textfield_id, 'val', ['']));
    $content = $this->viewBuilder->view($this->postEntity);
    $response->addCommand(new PrependCommand("#opigno-post-$pid .comment-item__comment--list:first", $content));
    $comments_link = $this->postsManager->getCommentsLink($pid, TRUE);
    $response->addCommand(new ReplaceCommand("#opigno-comments-amount-link-$pid", $comments_link))
      ->addCommand(new RemoveCommand($textfield_id . '-error-msg'))
      ->addCommand(new InvokeCommand($textfield_id, 'removeClass', ['error']))
      ->addCommand(new InvokeCommand($textfield_id, 'focus'));

    return $response;
  }

  /**
   * Adds the hidden field to the form.
   *
   * @param array $form
   *   A form to add a field to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   A form state.
   * @param string $field_name
   *   A field name to be added. Note: a field with the same machine name should
   *   exist in the post entity.
   */
  protected function addHiddenField(array &$form, FormStateInterface $form_state, string $field_name): void {
    $post = $this->entity;
    if (!$post->hasField($field_name)) {
      return;
    }

    $input = $form_state->getUserInput();
    $input = $input[$field_name] ?? '';
    $value = $post->get($field_name)->getString() ?: $input;

    $form[$field_name] = [
      '#type' => 'hidden',
      '#default_value' => $value,
    ];

    $post->set($field_name, $value);
  }

  /**
   * Prepares the list of attachment links.
   *
   * @return array
   *   The list of attachment links.
   */
  protected function getAttachmentLinks(): array {
    // Generate attachment links.
    $attachment_links = [
      'training' => [
        'icon' => 'fi fi-rr-book-alt',
        'title' => $this->t('Training', [], ['context' => 'Opigno post']),
        'class' => 'training',
      ],
      'badge' => [
        'icon' => 'fi fi-rr-badge',
        'title' => $this->t('Badges', [], ['context' => 'Opigno post']),
        'class' => 'badges',
      ],
      'certificate' => [
        'icon' => 'fi fi-rr-diploma',
        'title' => $this->t('Certificates', [], ['context' => 'Opigno post']),
        'class' => 'certificate',
      ],
    ];

    $weight = 0;
    foreach ($attachment_links as $type => $data) {
      $links[$type] = [
        '#type' => 'link',
        '#title' => Markup::create('<i class="' . $data['icon'] . '"></i>' . $data['title']),
        '#url' => $this->createProtectedUrl('opigno_social.get_shareable_content', [
          'type' => $type,
          'opigno_post_type' => $this->entity->bundle(),
        ]),
        '#attributes' => [
          'class' => [
            'use-ajax',
            'awards-list__item',
            'awards-list__' . $data['class'],
          ],
        ],
        '#weight' => $weight,
      ];

      $weight++;
    }

    return $links;
  }

  /**
   * Protect the url with the CSRF token to make AJAX request secure.
   *
   * @param string $route
   *   The route name to create the url.
   * @param array $params
   *   The route parameters.
   * @param array $options
   *   The url options.
   *
   * @return \Drupal\Core\Url
   *   The protected url.
   */
  protected function createProtectedUrl(string $route, array $params = [], array $options = []): Url {
    $url = Url::fromRoute($route, $params, $options);
    $internal = $url->getInternalPath();
    $url->setOption('query', ['token' => $this->csrfToken->get($internal)]);

    return $url;
  }

  /**
   * Gets the ajax form action url.
   *
   * @return string
   *   The ajax form action url.
   */
  protected function getAjaxFormActionUrl(): string {
    return Url::fromRoute('entity.opigno_post.add_form',
      ['opigno_post_type' => $this->entity->bundle()],
      [
        'query' => $this->getRequest()->query->all() + [
          FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
        ],
      ]
    )->toString();
  }

  /**
   * Hides the help text for the textarea field.
   *
   * @param array $form_element
   *   The text field form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated form element.
   */
  public function hideTextFormatHelp(array $form_element, FormStateInterface $form_state): array {
    if (isset($form_element[0]['format'])) {
      $form_element[0]['format']['#access'] = FALSE;
    }

    return $form_element;
  }

}
