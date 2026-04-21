<?php

namespace Drupal\gin_lb_plus\EventSubscriber;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\section_library\Entity\SectionLibraryTemplate;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGenerator;

/**
 * Class LayoutBuilderBrowserEventSubscriber.
 *
 * Add layout builder css class layout-builder-browser.
 */
class LayoutBuilderBrowserEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;
  use AjaxHelperTrait;

  /**
   * The list of available modules.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $extensionListModule;

  /**
   * File url generator.
   *
   * @var \Drupal\Core\File\FileUrlGenerator
   */
  protected $urlGenerator;

  /**
   * Constructs a LayoutBuilderBrowserEventSubscriber object.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The module list.
   * @param \Drupal\Core\File\FileUrlGenerator $url_generator
   *   File url generator.
   */
  public function __construct(ModuleExtensionList $extension_list_module, FileUrlGenerator $url_generator) {
    $this->extensionListModule = $extension_list_module;
    $this->urlGenerator = $url_generator;
  }

  /**
   * Add layout-builder-browser class layout_builder.choose_block build block.
   */
  public function onView(ViewEvent $event):void {
    $request = $event->getRequest();
    $route = $request->attributes->get('_route');
    if ($route == 'layout_builder.choose_section') {
      $build = $event->getControllerResult();
      if (!is_array($build)) {
        return;
      }
      $build['#attached']['library'][] = 'gin_lb_plus/core';
      $add_sections = [
        '#type' => 'details',
        '#title' => $this->t('Sections'),
        '#attributes' => [
          'class' => ['gin-lb-plus gin-lb-plus-container'],
        ],
      ];
      $add_sections['items'] = $build['layouts']['#items'];
      foreach ($add_sections['items'] as &$item) {
        $item['#title']['icon']['#theme'] = 'gin_lb_plus_icon';
        $item['#title']['icon']['#icon_type'] = 'section';
        $item['#title']['icon']['#plugin_id'] = $item["#url"]->getRouteParameters()['plugin_id'];
        $item['#title']['label']['#attributes']['class'] = ['gin-lb-plus-link__label'];
        $item['#attributes']['class'][] = 'gin-lb-plus-link';
      }
      $request = $event->getRequest();
      /** @var \Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage $section_storage */
      $section_storage = $request->attributes->get('section_storage');
      $delta_mixed = $request->attributes->get('delta') ?? 0;
      $delta = is_int($delta_mixed) ? $delta_mixed : 0;
      $add_library = [
        '#type' => 'details',
        '#title' => $this->t('Library'),
        '#attributes' => [
          'class' => ['gin-lb-plus gin-lb-plus-container'],
        ],
      ];
      $add_library['items'] = $this->getLibrarySectionLinks(
        $section_storage,
        $delta
      );
      $add_library['#access'] = count($add_library['items']) > 0;
      unset($build['layouts']);
      $build['layouts'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['gin-lb-plus-tabs'],
        ],
      ];
      $build['layouts']['tabs'] = [
        '#type' => 'horizontal_tabs',
        'items' => [
          $add_sections,
          $add_library,
        ],
      ];
      $event->setControllerResult($build);
    }

    if ($route == 'layout_builder.choose_block') {
      $build['#attached']['library'][] = 'gin_lb_plus/core';
      $build = $event->getControllerResult();
      if (is_array($build) && !isset($build['add_block'])) {
        $build['block_categories']['#type'] = 'horizontal_tabs';
        foreach (Element::children($build['block_categories']) as $child) {
          foreach (
            Element::children(
              $build['block_categories'][$child]['links']
            ) as $link_id
          ) {
            $link = &$build['block_categories'][$child]['links'][$link_id];
            $link['#attributes']['class'][] = 'gin-lb-plus-link';
            $link['link']['#title']['image']['#theme'] = 'gin_lb_plus_icon';
            $link['link']['#title']['image']['#icon_type'] = 'block';
            $link['link']['#title']['label']['#markup'] = '<div class="gin-lb-plus-link__label">' . $link['link']['#title']['label']['#markup'] . '</div>';
            if (($key = array_search(
                'layout-builder-browser-block-item',
                $link['#attributes']['class']
              )) !== FALSE) {
              unset($link['#attributes']['class'][$key]);
            }
          }
        }

        if (($key = array_search(
            'layout-builder-browser',
            $build['block_categories']['#attributes']['class']
          )) !== FALSE) {
          unset($build['block_categories']['#attributes']['class'][$key]);
        }

        $build['block_categories']['#attributes']['class'][] = 'gin-lb-plus';
        $event->setControllerResult($build);
      }
    }
  }

  /**
   * Gets a render array of section links.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The region the section is going in.
   *
   * @return array
   *   The section links render array.
   */
  protected function getLibrarySectionLinks(
    SectionStorageInterface $section_storage,
    $delta,
  ) {
    // @phpstan-ignore-next-line
    $sections = SectionLibraryTemplate::loadMultiple();
    $links = [];
    foreach ($sections as $section_id => $section) {
      $attributes = $this->getAjaxAttributes();
      $attributes['class'][] = 'js-layout-builder-section-library-link';
      $attributes['class'][] = 'gin-lb-plus-link';
      // Default library image.
      $img_path = NULL;
      if ($fid = $section->get('image')->target_id) {
        $file = File::load($fid);
        if ($file !== NULL) {
          $img_path = $file->getFileUri();
        }
      }
      if (empty($img_path)) {
        $img_path = $this->extensionListModule->getPath('gin_lb_plus') . '/images/section-empty-icon.svg';
      }
      $icon_url = $this->urlGenerator->generateString($img_path);
      $link = [
        '#type' => 'link',
        '#title' => Markup::create(
          '<div class="gin-lb-plus__icon"><img src="' . $icon_url . '" class="section-library-link-img" /> </div>' . '<div class="gin-lb-plus-link__label">' . $section->label(
          ) . '</div>'
        ),
        '#url' => Url::fromRoute(
          'section_library.import_section_from_library',
          [
            'section_library_id' => $section_id,
            'section_storage_type' => $section_storage->getStorageType(),
            'section_storage' => $section_storage->getStorageId(),
            'delta' => $delta,
          ]
        ),
        '#attributes' => $attributes,
      ];
      $links[] = $link;
    }
    return $links;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::VIEW][] = ['onView', 45];
    return $events;
  }

  /**
   * Get dialog attributes if an ajax request.
   *
   * @return array
   *   The attributes array.
   */
  protected function getAjaxAttributes() {
    if ($this->isAjax()) {
      return [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'dialog',
        'data-dialog-renderer' => 'off_canvas',
      ];
    }
    return [];
  }

}
