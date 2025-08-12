<?php

namespace Drupal\h5p\Plugin\Field\FieldFormatter;

use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\Entity\H5PContent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'h5p_default' formatter.
 *
 * @FieldFormatter(
 *   id = "h5p_default",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "h5p"
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
class H5PDefaultFormatter extends FormatterBase {

  /**
   * Constructor.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected ModuleHandlerInterface $moduleHandler,
    protected AssetResolverInterface $assetResolver,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['label'], $configuration['view_mode'],       $configuration['third_party_settings'],
      $container->get('module_handler'),
      $container->get('asset.resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();

    $summary[] = t('Displays interactive H5P content.');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = array();

    foreach ($items as $delta => $item) {
      $value = $item->getValue();

      // Load H5P Content entity
      $h5p_content = H5PContent::load($value['h5p_content_id']);
      if (empty($h5p_content)) {
        continue;
      }

      // Grab generic integration settings
      $h5p_integration = H5PDrupal::getGenericH5PIntegrationSettings();

      // Add content specific settings
      $content_id_string = 'cid-' . $h5p_content->id();
      $h5p_integration['contents'][$content_id_string] = $h5p_content->getH5PIntegrationSettings($item->getEntity()->access('update'));

      $core = H5PDrupal::getInstance('core');
      $preloaded_dependencies = $core->loadContentDependencies($h5p_content->id(), 'preloaded');

      $loadpackages = [
        'h5p/h5p.content',
      ];

      // Load dependencies
      foreach ($preloaded_dependencies as $dependency) {
        $loadpackages[] = 'h5p/' . _h5p_library_machine_to_id($dependency);
      }

      // Determine embed type and HTML to use
      if ($h5p_content->isDivEmbeddable()) {
        $html = '<div class="h5p-content" data-content-id="' . $h5p_content->id() . '"></div>';
      }
      else {
        $assets = new AttachedAssets();
        $assets->setLibraries($loadpackages);

        // Aggregate the libraries.
        $scripts = $this->assetResolver->getJsAssets($assets, TRUE);
        $styles = $this->assetResolver->getCssAssets($assets, TRUE);
        // reset packages sto be loaded dynamically
        $loadpackages = [
          'h5p/h5p.content',
        ];
        // set html
        $metadata = $h5p_content->getMetadata();
        $language = isset($metadata['defaultLanguage'])
          ? $metadata['defaultLanguage']
          : 'en';
        $title = isset($metadata['title'])
          ? $metadata['title']
          : 'H5P content';

        $html = '<div class="h5p-iframe-wrapper"><iframe id="h5p-iframe-' . $h5p_content->id() . '" class="h5p-iframe" data-content-id="' . $h5p_content->id() . '" style="height:1px" frameBorder="0" scrolling="no" lang="' . $language . '" title="' . $title . '"></iframe></div>';

        [$header_js, $footer_js] = $scripts;
        $all_js = array_merge($header_js, $footer_js);
        foreach ($all_js as $asset) {
          $jsFilePaths[] = $asset['data'];
        }

        foreach ($styles as $style) {
          $cssFilePaths[] = $style['data'];
        }

        // Load core assets
        $coreAssets = H5PDrupal::getCoreAssets();
        $file_url_generator = \Drupal::service('file_url_generator');
        foreach ($coreAssets as $type => $core_assets) {
          foreach ($core_assets as $key => $value) {
            $coreAssets[$type][$key] = $file_url_generator->generateAbsoluteString($value);
          }
        }

        $h5p_integration['core']['scripts'] = $coreAssets['scripts'];
        $h5p_integration['core']['styles'] = $coreAssets['styles'];
        $h5p_integration['contents'][$content_id_string]['scripts'] = $jsFilePaths;
        $h5p_integration['contents'][$content_id_string]['styles'] = $cssFilePaths;
      }

      // Render each element as markup.
      $element[$delta] = array(
        '#type' => 'markup',
        '#markup' => $html,
        '#allowed_tags' => ['div','iframe'],
        '#attached' => [
          'drupalSettings' => [
            'h5p' => [
              'H5PIntegration' => $h5p_integration,
            ]
          ],
          'library' => $loadpackages,
        ],
        '#cache' => [
          'tags' => [
            'h5p_content:' . $h5p_content->id(),
            'h5p_content'
          ]
        ],
      );
    }
    $this->moduleHandler->alter('h5p_formatter', $element, $items);

    return $element;
  }

}
