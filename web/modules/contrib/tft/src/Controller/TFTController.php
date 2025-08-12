<?php

namespace Drupal\tft\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\media\MediaInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\tft\Form\AddFolderForm;
use Drupal\tft\Form\DeleteFileForm;
use Drupal\tft\Form\DeleteFolderForm;
use Drupal\tft\Form\EditFolderForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class TFTController. Manage the Files.
 */
class TFTController extends ControllerBase {

  /**
   * The temporary storage.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $tmpStore;

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * Current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPath;

  /**
   * The group storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $groupStorage;

  /**
   * The media storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $mediaStorage;

  /**
   * The file storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $fileStorage;

  /**
   * The taxonomy storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $taxonomyStorage;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * TFTController constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tmp_store
   *   The private temporary storage service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module list extension service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   *   The form builder.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   */
  public function __construct(
    PrivateTempStoreFactory $tmp_store,
    ModuleExtensionList $module_extension_list,
    FileSystemInterface $file_system,
    RequestStack $request_stack,
    RendererInterface $renderer,
    CurrentPathStack $current_path,
    FormBuilder $formBuilder,
    EntityFormBuilderInterface $entity_form_builder
  ) {
    $this->tmpStore = $tmp_store->get('tft');
    $this->moduleExtensionList = $module_extension_list;
    $this->fileSystem = $file_system;
    $this->request = $request_stack->getCurrentRequest();
    $this->renderer = $renderer;
    $this->currentPath = $current_path;
    $this->formBuilder = $formBuilder;
    $this->entityFormBuilder = $entity_form_builder;
    $this->groupStorage = $this->entityTypeManager()->getStorage('group');
    $this->mediaStorage = $this->entityTypeManager()->getStorage('media');
    $this->fileStorage = $this->entityTypeManager()->getStorage('file');
    $this->taxonomyStorage = $this->entityTypeManager()->getStorage('taxonomy_term');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('extension.list.module'),
      $container->get('file_system'),
      $container->get('request_stack'),
      $container->get('renderer'),
      $container->get('path.current'),
      $container->get('form_builder'),
      $container->get('entity.form_builder')
    );
  }

  /**
   * Format a folder or file link.
   *
   * @param array $item
   *   File item.
   * @param string $mime
   *   Mime type of file.
   * @param \Drupal\media\MediaInterface|null $media
   *   Media entity.
   * @param bool $error
   *   Some problem with the file.
   *
   * @return array
   *   Render an array with the formatted link
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function link(array $item, string $mime, ?MediaInterface $media = NULL, bool $error = FALSE): array {
    if ($mime === 'folder') {
      return [
        'data' => [
          '#type' => 'link',
          '#title' => Markup::create('<i class="fi fi-rr-folder"></i>'),
          '#url' => Url::fromRoute('tft.group.folder', [
            'group' => $item['gid'],
            'taxonomy_term' => $item['id'],
          ]),
          '#attributes' => [
            'class' => ['folder-folder-link'],
          ],
        ],
      ];
    }
    else {
      $message = $error ? ' [' . $this->t('Wrong file') . ']' : NULL;
      $file = $this->getFile($media);
      switch ($this->getFileType($mime)) {
        // Image link to AJAX callback to display popup.
        case 'image':
          $uri = $file->getFileUri();
          $thumbnail = $message ?? [
            '#theme' => 'image_style',
            '#style_name' => 'user_list_item',
            '#uri' => $uri,
            '#attributes' => ['class' => ['thumbnail-image']],
          ];
          $attributes = [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'modal',
          ];
          $url = Url::fromRoute('tft.media_preview', [
            'media' => $media->id(),
          ]);
          break;

        // Video link to AJAX callback to display popup.
        case 'video':
          $thumbnail = $message ?? $this->getVideoThumbnail($media);
          $url = Url::fromRoute('tft.media_preview', [
            'media' => $media->id(),
          ]);
          $attributes = [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'modal',
          ];
          break;

        // Link for all other documents.
        default:
          // Default documents icon.
          $icon_class = file_icon_class($mime);
          $thumbnail = Markup::create('<i class="fi fi-rr-file"></i>' . $message);
          $url = Url::fromRoute('tft.download_file', [
            'media' => $media->id(),
          ]);
          $attributes = [
            'class' => [
              'file',
              $icon_class,
              'folder-folder-link',
            ],
            'target' => '_blank',
          ];
          break;
      }

      $attributes['class'][] = $error ? 'error' : '';
      return [
        'data' => [
          '#type' => 'link',
          '#title' => $thumbnail,
          '#url' => $url,
          '#attributes' => $attributes,
        ],
      ];
    }
  }

  /**
   * Provide media thumbnail for the video.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return array
   *   Renderer element.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function getVideoThumbnail(MediaInterface $media): array {
    return [
      '#theme' => 'image_formatter',
      '#item' => $media->get('thumbnail')->first(),
      '#image_style' => 'user_list_item',
    ];
  }

  /**
   * AJAX Callback to show popup with media content.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response to display modal.
   */
  public function mediaPreview(MediaInterface $media): AjaxResponse {
    $file = $this->getFile($media);
    $mime = $file->getMimeType();
    $type = $this->getFileType($mime);

    if ($type === 'image') {
      $uri = $file->getFileUri();
      $content = [
        '#theme' => 'image_style',
        '#style_name' => 'large',
        '#uri' => $uri,
        '#attributes' => ['class' => ['thumbnail-image']],
      ];
    }
    else {
      $content = $this->entityTypeManager()
        ->getViewBuilder('media')
        ->view($media);
    }

    $response = new AjaxResponse();
    $build = [
      '#theme' => 'tft_form_modal',
      '#body' => $content,
      '#close' => 1,
    ];

    $response->addCommand(new RemoveCommand('.modal-ajax'));
    $response->addCommand(new AppendCommand('body', $build));
    $response->addCommand(new InvokeCommand('.modal-ajax', 'modal', ['show']));
    return $response;
  }

  /**
   * Get folder content.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param \Drupal\taxonomy\TermInterface|null $taxonomy_term
   *   Taxonomy term.
   *
   * @return array
   *   The renderable array with a list of groups.
   */
  public function listGroupFolder(GroupInterface $group, ?TermInterface $taxonomy_term = NULL): array {
    $tid = $taxonomy_term instanceof TermInterface ? $taxonomy_term->id() : NULL;
    return $this->listGroup($group, $tid);
  }

  /**
   * Return an <ul> with links for the current folder.
   *
   * @param string $type
   *   The type of file.
   * @param int $tid
   *   The file ID.
   * @param \Drupal\media\MediaInterface|null $media
   *   Media entity.
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   Group ID.
   *
   * @return array
   *   The render array
   */
  protected function operationLinks(string $type, int $tid, ?MediaInterface $media = NULL, ?GroupInterface $group = NULL): array {
    $links = [];

    switch ($type) {
      case 'folder':
        $user = $this->currentUser();

        // Hide an edit link if the user has no access.
        if ($user->hasPermission(TFT_ADD_TERMS)
          || ($group && $group->hasPermission(TFT_ADD_TERMS, $user))) {
          $links[] = [
            '#type' => 'link',
            '#title' => $this->t("Edit"),
            '#url' => Url::fromRoute('tft.form', [
              'group' => $group->id(),
              'taxonomy_term' => $tid,
              'type' => 'edit_folder',
            ]),
            '#attributes' => [
              'id' => 'add-child-folder',
              'class' => [
                'use-ajax',
                'dropdown-item-text',
              ],
              'data-dialog-type' => 'modal',
            ],
          ];
        }

        if ($user->hasPermission(TFT_DELETE_TERMS)
          || ($group && $group->hasPermission(TFT_DELETE_TERMS, $user))) {

          $links[] = [
            '#type' => 'link',
            '#title' => $this->t("Delete"),
            '#url' => Url::fromRoute('tft.form', [
              'group' => $group->id(),
              'taxonomy_term' => $tid,
              'type' => 'delete_folder',
            ]),
            '#attributes' => [
              'id' => 'add-child-folder',
              'class' => [
                'use-ajax',
                'dropdown-item-text',
              ],
              'data-dialog-type' => 'modal',
            ],
          ];
        }
        break;

      case 'file':
        if ($media && $media->access('update')) {
          $links[] = [
            '#type' => 'link',
            '#title' => $this->t("Edit"),
            '#url' => Url::fromRoute('tft.form.file', [
              'group' => $group->id(),
              'taxonomy_term' => $tid,
              'type' => 'edit_file',
              'media' => $media->id(),
            ]),
            '#attributes' => [
              'id' => 'add-child-folder',
              'class' => [
                'use-ajax',
                'dropdown-item-text',
              ],
              'data-dialog-type' => 'modal',
            ],
          ];
        }

        if ($media && $media->access('delete')) {
          $links[] = [
            '#type' => 'link',
            '#title' => $this->t("Delete"),
            '#url' => Url::fromRoute('tft.form.file', [
              'group' => $group->id(),
              'taxonomy_term' => $tid,
              'type' => 'delete_file',
              'media' => $media->id(),
            ]),
            '#attributes' => [
              'id' => 'add-child-folder',
              'class' => [
                'use-ajax',
                'dropdown-item-text',
              ],
              'data-dialog-type' => 'modal',
            ],
          ];
        }

        $links[] = [
          '#type' => 'link',
          '#title' => $this->t('Download'),
          '#url' => Url::fromRoute('tft.download_file', ['media' => $media->id()]),
          '#attributes' => [
            'class' => ['dropdown-item-text'],
            'target' => '_blank',
          ],
        ];
        break;
    }

    return [
      'data' => [
        '#theme' => 'tft_documents_actions',
        '#actions' => $links,
      ],
    ];
  }

  /**
   * Returns type of file.
   *
   * @param string $mime
   *   The mime type of the file.
   *
   * @return string
   *   The type of file.
   */
  protected function getFileType(string $mime): string {
    return explode('/', $mime)[0];
  }

  /**
   * Returns type of file.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return \Drupal\file\FileInterface|null
   *   File entity.
   */
  protected function getFile(MediaInterface $media): FileInterface|null {
    if ($media->get('tft_file')->isEmpty()) {
      return NULL;
    }

    $fids = $media->get('tft_file')->getValue();
    $fid = reset($fids)['target_id'];
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->fileStorage->load($fid);
    return $file instanceof FileInterface ? $file : NULL;
  }

  /**
   * Returns folder content.
   *
   * @param int $tid
   *   Taxonomy term ID.
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   Group entity.
   *
   * @return array
   *   The folder content
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getContent(int $tid, ?GroupInterface $group = NULL): array {
    $content = [];

    if (!$group instanceof GroupInterface) {
      return [];
    }
    $elements = _tft_folder_content($tid, FALSE, $group->id());
    if (empty($elements)) {
      return [];
    }
    $account = $this->currentUser();
    foreach ($elements as $element) {
      $error = FALSE;
      if ($element['type'] === 'term') {
        $content[] = [
          $this->link($element, 'folder'),
          $element['name'],
          '',
          '',
          $this->t("Folder"),
          $this->operationLinks('folder', $element['id'], NULL, $group),
        ];
      }
      else {
        /** @var \Drupal\media\Entity\Media $media */
        $media = $this->mediaStorage->load($element['id']);
        $user = $media->getOwner();
        $file = $this->getFile($media);
        if ($file instanceof FileInterface) {
          $uri = $file->getFileUri();
          $path = $this->fileSystem->realpath($uri);
          if (!file_exists($path)) {
            // Administrators should see the problems with files to manage it.
            if ($account->hasPermission('')) {
              $error = TRUE;
            }
            else {
              continue;
            }
          }
          $file_name = $file->getFilename();
          $file_name_parts = explode('.', $file_name);
          $file_extension = end($file_name_parts);

          $content[] = [
            $this->link($element, $file->getMimeType(), $media, $error),
            $element['name'],
            $user->getDisplayName(),
            date('d/m/Y H:i', $media->getChangedTime()),
            $this->t('@type file', [
              '@type' => strtoupper($file_extension),
            ]),
            $this->operationLinks('file', $tid, $media, $group),
          ];
        }
        elseif (!empty($media->get('opigno_moxtra_recording_link')->getValue())) {
          $content[] = [
            $this->link($element, 'video/mp4'),
            $element['name'],
            $user->getDisplayName(),
            date('d/m/Y H:i', $media->getChangedTime()),
            $this->t('MP4 file'),
            $this->operationLinks('file', $tid, $media, $group),
          ];
        }
      }
    }

    return $content;
  }

  /**
   * Render the added file and add folder links.
   *
   * @param int $tid
   *   Taxonomy term ID.
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   Group entity ID.
   *
   * @return array
   *   The item_list content
   */
  protected function addContentLinks(int $tid = 0, ?GroupInterface $group = NULL): array {
    $items = [];

    // Do we have a tid?
    if ($tid && empty($group)) {
      $gid = _tft_get_group_gid($tid);
      $group = $this->groupStorage->load($gid);
    }

    $user = $this->currentUser();

    // Can the user add terms anywhere, only under Group or never?
    if ($user->hasPermission(TFT_ADD_TERMS) && $group
      || ($group && $group->hasPermission(TFT_ADD_TERMS, $user))) {
      $items[] = [
        '#wrapper_attributes' => [
          'class' => 'folder-add-content-link',
        ],
        '#type' => 'link',
        '#title' => Markup::create('<i class="fi fi-rr-folder-add"></i>' . $this->t("Add a folder")),
        '#url' => Url::fromRoute('tft.form', [
          'group' => $group->id(),
          'taxonomy_term' => $tid,
          'type' => 'add_folder',
        ]),
        '#attributes' => [
          'id' => 'add-child-folder',
          'class' => [
            'use-ajax',
          ],
          'data-dialog-type' => 'modal',
        ],
      ];
    }

    // Can the user create files?
    if ($user->hasPermission('create media')) {
      // Can they add files in this context?
      if ($user->hasPermission(TFT_ADD_FILE)
        || ($group && $group->hasPermission(TFT_ADD_FILE, $user))) {
        $items[] = [
          '#wrapper_attributes' => [
            'class' => 'folder-add-content-link',
          ],
          '#type' => 'link',
          '#title' => Markup::create('<i class="fi fi-rr-file-add"></i>' . $this->t("Add a file")),
          '#url' => Url::fromRoute('tft.form', [
            'group' => $group->id(),
            'taxonomy_term' => $tid,
            'type' => 'add_file',
          ]),
          '#attributes' => [
            'id' => 'add-child-file',
            'class' => [
              'use-ajax',
            ],
            'data-dialog-type' => 'modal',
          ],
        ];
      }
    }

    return [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#attributes' => [
        'id' => 'folder-add-content-links',
      ],
      '#items' => $items,
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
    ];
  }

  /**
   * Get the folder content in HTML table form.
   *
   * @param int $tid
   *   Taxonomy term ID.
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   Group entity.
   *
   * @return array
   *   The render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function contentTable(int $tid, ?GroupInterface $group = NULL): array {
    $headers = [
      [
        'id' => 'table-th-preview',
        'data' => $this->t('Preview'),
      ],
      [
        'id' => 'table-th-name',
        'data' => $this->t('Name'),
      ],
      [
        'id' => 'table-th-loaded-by',
        'data' => $this->t('Loaded by'),
      ],
      [
        'id' => 'table-th-date',
        'data' => $this->t('Last modified'),
      ],
      [
        'id' => 'table-th-type',
        'data' => $this->t('Type'),
      ],
      [
        'id' => 'table-th-ops',
        'data' => $this->t('Operations'),
      ],
    ];

    return [
      [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['folder-menu-wrapper'],
        ],
        'operation_links' => $this->getFolderOperationLinks($tid, $group),
        'content_links' => $this->addContentLinks($tid, $group),
      ],
      [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['content-box', 'documents-table'],
        ], [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['table-responsive'],
          ],
          'table' => [
            '#type' => 'table',
            '#header' => $headers,
            '#rows' => $this->getContent($tid, $group),
            '#empty' => $this->t('No files'),
          ],
        ],
      ],
    ];
  }

  /**
   * Return an <ul> with links for the current folder.
   *
   * @param int $tid
   *   Taxonomy term ID.
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   Group entity.
   *
   * @return array
   *   The render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getFolderOperationLinks(int $tid, ?GroupInterface $group = NULL): array {
    $items = [];

    // First link: got to parent.
    $parent_tid = _tft_get_parent_tid($tid);

    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $root_tid = $this->tmpStore->get('root_tid');
    $query = ['query' => ['destination' => $this->tmpStore->get('q')]];

    $disabled = FALSE;

    if ($parent_tid <= -1 || $tid != $root_tid && !_tft_term_access($parent_tid)) {
      $disabled = TRUE;
    }

    if ($group instanceof GroupInterface) {
      $gid = $group->id();
    }
    else {
      $gid = _tft_get_group_gid($tid);
    }

    $class = $disabled ? ['disabled'] : [];
    $url = $disabled ?
      Url::fromRoute('<none>') :
      Url::fromRoute('tft.group.folder', [
        'group' => $gid,
        'taxonomy_term' => $parent_tid,
      ]);

    $items[] = [
      '#wrapper_attributes' => [
        'id' => 'tft-back',
        'class' => 'folder-menu-ops-link first',
      ],
      '#type' => 'link',
      '#title' => Markup::create('<i class="fi fi-rr-undo"></i>' . $this->t("Parent folder")),
      '#url' => $url,
      '#attributes' => [
        'class' => $class,
        'id' => 'tft-back-link',
      ],
    ];

    // Third link: reorder child terms.
    $url = Url::fromRoute('tft.reorder_terms', [
      'taxonomy_term' => $tid,
    ], $query);
    $user = $this->currentUser();

    if ($user->hasPermission(TFT_REORDER_TERMS)
      || ($group && $group->hasPermission(TFT_REORDER_TERMS, $user))) {
      $items[] = [
        '#wrapper_attributes' => [
          'id' => 'manage-folders',
          'class' => 'folder-menu-ops-link',
        ],
        '#type' => 'link',
        '#title' => $this->t("reorder elements"),
        '#url' => $url,
      ];
    }

    return [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#attributes' => [
        'class' => 'tabs primary',
        'id' => 'folder-menu-container',
      ],
      '#items' => $items,
    ];
  }

  /**
   * File explorer.
   *
   * @param int|string $tid
   *   Taxonomy term ID.
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   Group entity ID.
   *
   * @return array
   *   Folder menu.
   */
  protected function tft(int|string $tid = 'all', ?GroupInterface $group = NULL): array {
    if ($tid === 'all' || !(int) $tid) {
      if ($this->currentUser()->hasPermission(TFT_ACCESS_FULL_TREE)) {
        $tid = 0;
      }
      else {
        throw new AccessDeniedHttpException();
      }
    }

    // Check if the user has access to this tree.
    if (!_tft_term_access($tid)) {
      throw new AccessDeniedHttpException();
    }

    $base_path = base_path();
    $base_path = strlen($base_path) === '/' ? '' : substr($base_path, 0, -1);
    // Store the URL query. Need the current path for some AJAX callbacks.
    $this->tmpStore->set('q', $base_path . $this->currentPath->getPath());

    // Store the current term tid.
    $this->tmpStore->set('root_tid', $tid);

    $path = $this->moduleExtensionList->getPath('tft');

    return [
      [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'folder-content-container',
        ],
        'content' => $this->contentTable($tid, $group),
      ],
      // Add CSS and Javascript files.
      '#attached' => [
        'library' => [
          'tft/tft',
        ],
        'drupalSettings' => [
          'tftDirectory' => $path,
        ],
      ],
    ];
  }

  /**
   * Downloads file.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
   *   Response.
   */
  public function downloadFile(MediaInterface $media) {
    // Check if the user has access to group entity.
    if (!empty($media->tft_folder)) {
      $tid = $media->get('tft_folder')->getString();
      if (!empty($tid) && !_tft_term_access($tid)) {
        throw new AccessDeniedHttpException();
      }
    }

    $fids = $media->get('tft_file')->getValue();
    if (!empty($fids)) {
      $fid = reset($fids)['target_id'];

      /** @var \Drupal\file\Entity\File $file */
      $file = $this->fileStorage->load($fid);

      if (!$file) {
        throw new NotFoundHttpException();
      }

      // Don't trust the system. Check if the file really exists.
      $uri = $file->getFileUri();
      $path = $this->fileSystem->realpath($uri);
      if (!file_exists($path)) {
        throw new NotFoundHttpException();
      }

      if (!($file->access('view') && $file->access('download'))) {
        throw new AccessDeniedHttpException();
      }

      // Let other modules provide headers and control access to the file.
      $headers = $this->moduleHandler()->invokeAll('file_download', [$file->getFileUri()]);
      if (in_array(-1, $headers) || empty($headers)) {
        throw new AccessDeniedHttpException();
      }

      $file_name = $file->getFilename();
      $headers = [
        'Content-Type' => $file->getMimeType(),
        'Content-Disposition' => 'attachment; filename="' . $file_name . '"',
      ];

      if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
        $headers['Cache-Control'] = 'must-revalidate, post-check=0, pre-check=0';
        $headers['Pragma'] = 'public';
      }
      else {
        $headers['Pragma'] = 'no-cache';
      }

      return new BinaryFileResponse($file->getFileUri(), 200, $headers);
    }
    elseif (!empty($link = $media->get('opigno_moxtra_recording_link')->getValue())) {
      if ($opigno_api = tft_moxtra_service()) {
        $token = $opigno_api->getToken($media->getOwnerId());
        $url = $link[0]['uri'] . "&access_token=$token";
        return new TrustedRedirectResponse($url);
      }
    }

    throw new NotFoundHttpException();
  }

  /**
   * Returns directory list.
   *
   * @param \Drupal\taxonomy\TermInterface|null $taxonomy_term
   *   Taxonomy term.
   *
   * @return array
   *   Render array.
   */
  public function listDirectory(?TermInterface $taxonomy_term = NULL) {
    $tid = isset($taxonomy_term) ? $taxonomy_term->id() : 'all';
    return $this->tft($tid);
  }

  /**
   * Returns group list.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param int|null $tid
   *   Taxonomy term ID.
   *
   * @return array
   *   Render array.
   */
  public function listGroup(GroupInterface $group, ?int $tid = NULL): array {
    if (empty($tid)) {
      $tid = _tft_get_group_tid($group->id());
    }

    if (!$tid) {
      return [
        '#markup' => $this->t("No term was found for this group ! Please contact your system administrator."),
      ];
    }

    return $this->tft($tid, $group);
  }

  /**
   * Returns folder access flag.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Access a flag.
   */
  public function accessAjaxGetFolder(AccountInterface $account): AccessResult {
    $tid = $this->request->query->get('tid');
    $term = $this->taxonomyStorage->load($tid);
    if (isset($term) && $term->access('view', $account)) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * Returns folder.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function ajaxGetFolder() {
    $tid = $this->request->query->get('tid');
    $gid = _tft_get_group_gid($tid);
    /** @var \Drupal\group\Entity\GroupInterface $group */
    $group = $this->groupStorage->load($gid);
    $data = $this->contentTable($tid, $group);

    return new JsonResponse([
      'data' => $this->renderer->renderRoot($data),
      'parent' => _tft_get_parent_tid($tid, $gid),
      'ops_links' => '',
    ]);
  }

  /**
   * Callback for the Add a Folder modal form.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group entity.
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   Taxonomy term entity.
   * @param string|null $type
   *   The type of the content should display.
   * @param \Drupal\media\MediaInterface|null $media
   *   Media entity.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Open modal dialog response.
   */
  public function tftModalForms(
    GroupInterface $group,
    TermInterface $taxonomy_term,
    ?string $type = NULL,
    ?MediaInterface $media = NULL,
  ): AjaxResponse {
    $response = new AjaxResponse();
    $close = NULL;

    switch ($type) {
      case 'add_folder':
        $title = $this->t('Add folder');
        $content = $this->formBuilder->getForm(AddFolderForm::class, $group, $taxonomy_term);
        break;

      case 'edit_folder':
        $title = $this->t('Edit folder @folder', ['@folder' => $taxonomy_term->getName()]);
        $content = $this->formBuilder->getForm(EditFolderForm::class, $group, $taxonomy_term);
        break;

      case 'delete_folder':
        $title = $this->t('Delete folder @folder', ['@folder' => $taxonomy_term->getName()]);
        $content = $this->formBuilder->getForm(DeleteFolderForm::class, $group, $taxonomy_term);
        break;

      case 'add_file':
        $title = $this->t('Add a file');
        $media = $this->entityTypeManager->getStorage('media')->create([
          'bundle' => 'tft_file',
          'uid' => $this->currentUser()->id(),
          'tft_folder' => [
            'target_id' => $taxonomy_term->id(),
          ],
        ]);
        $content = $this->entityFormBuilder->getForm($media);
        break;

      case 'edit_file':
        $title = $this->t('Edit a file @file', ['@file' => $media->getName()]);
        $content = $this->entityFormBuilder->getForm($media);
        break;

      case 'delete_file':
        $title = $this->t('Delete a file @file', ['@file' => $media->getName()]);
        $content = $this->formBuilder->getForm(DeleteFileForm::class, $group, $taxonomy_term, $media);
        break;

      default:
        $title = $this->t('Wrong type');
        $content = [];
        $close = TRUE;
        break;
    }

    $build = [
      '#theme' => 'tft_form_modal',
      '#title' => $title,
      '#body' => $content,
      '#close' => $close,
    ];

    $response->addCommand(new RemoveCommand('.modal-ajax'));
    $response->addCommand(new AppendCommand('body', $build));
    $response->addCommand(new InvokeCommand('.modal-ajax', 'modal', ['show']));
    return $response;
  }

}
