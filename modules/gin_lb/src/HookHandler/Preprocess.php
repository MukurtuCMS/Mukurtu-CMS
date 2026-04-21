<?php

declare(strict_types=1);

namespace Drupal\gin_lb\HookHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\file\FileInterface;
use Drupal\gin_lb\Service\ContextValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementation.
 */
class Preprocess implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The context validator.
   *
   * @var \Drupal\gin_lb\Service\ContextValidatorInterface
   */
  protected ContextValidatorInterface $contextValidator;

  /**
   * Constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\gin_lb\Service\ContextValidatorInterface $contextValidator
   *   The context validator.
   */
  public function __construct(
    RequestStack $requestStack,
    ConfigFactoryInterface $configFactory,
    ContextValidatorInterface $contextValidator,
  ) {
    $this->requestStack = $requestStack;
    $this->configFactory = $configFactory;
    $this->contextValidator = $contextValidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('gin_lb.context_validator')
    );
  }

  /**
   * Hook implementation.
   *
   * @param array $variables
   *   The preprocessed variables.
   */
  public function preprocessFieldMultipleValueForm(array &$variables): void {
    // Add gin_lb_form attribute to tables.
    if ($variables['element']['#gin_lb_form'] ?? NULL) {
      $variables['table']['#attributes']['class'][] = 'glb-table';

      // Make disabled available for the template.
      $variables['disabled'] = !empty($variables['element']['#disabled']);

      if ($variables['multiple']) {
        // Add a CSS class for the field label table cell.
        // This repeats the logic of .ate_preprocess_field_multiple_value_form()
        // without using '#prefix' and '#suffix' for the wrapper element.
        //
        // If the field is multiple, we don't have to check the existence of the
        // table header cell.
        //
        // @see template_preprocess_field_multiple_value_form().
        $header_attributes = [
          'class' => [
            'form-item__label',
            'form-item__label--multiple-value-form',
          ],
        ];
        if (!empty($variables['element']['#required'])) {
          $header_attributes['class'][] = 'js-form-required';
          $header_attributes['class'][] = 'form-required';
        }
        // Using array_key_first() for addressing the first header cell would be
        // more elegant here, but we can rely on the related theme.inc
        // preprocess.
        $variables['table']['#header'][0]['data'] = [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => $variables['element']['#title'],
          '#attributes' => $header_attributes,
        ];

        if ($variables['disabled']) {
          $variables['table']['#attributes']['class'][] = 'tabledrag-disabled';
          $variables['table']['#attributes']['class'][] = 'js-tabledrag-disabled';

          // We will add the 'is-disabled' CSS class to the disabled table
          // header cells.
          $header_attributes['class'][] = 'is-disabled';
          foreach ($variables['table']['#header'] as &$cell) {
            if (\is_array($cell) && isset($cell['data'])) {
              $cell = $cell + ['class' => []];
              $cell['class'][] = 'is-disabled';
            }
            else {
              // We have to modify the structure of this header cell.
              $cell = [
                'data' => $cell,
                'class' => ['is-disabled'],
              ];
            }
          }
        }

        // Make add-more button smaller.
        if (!empty($variables['button'])) {
          $variables['button']['#attributes']['class'][] = 'button--small';
        }
      }
    }
  }

  /**
   * Hook implementation.
   *
   * @param array $variables
   *   The preprocessed variables.
   */
  public function preprocessFileManagedFileGinLb(array &$variables): void {
    if (!$this->contextValidator->isValidTheme()) {
      return;
    }

    // Produce the same renderable element structure as image widget has.
    $child_keys = Element::children($variables['element']);
    foreach ($child_keys as $child_key) {
      $variables['data'][$child_key] = $variables['element'][$child_key];
    }

    $this->preprocessFileAndImageWidget($variables);
  }

  /**
   * Hook implementation.
   *
   * @param array $variables
   *   The preprocessed variables.
   */
  public function preprocessFormElement(array &$variables): void {
    if (!isset($variables['label'])) {
      return;
    }

    if (isset($variables['element']['#gin_lb_form'])) {
      $variables['label']['#gin_lb_form'] = TRUE;
      if (isset($variables['element']['#type'])) {
        $variables['attributes']['class'][] = 'form-type--' . $variables['element']['#type'];
      }
    }
  }

  /**
   * Hook implementation.
   *
   * @param array $variables
   *   The preprocessed variables.
   */
  public function preprocessHtml(array &$variables): void {
    if (!$this->contextValidator->isLayoutBuilderRoute()) {
      return;
    }

    $variables['attributes']['class'][] = 'glb-body';
    if ($this->configFactory->get('gin_lb.settings')->get('enable_preview_regions')) {
      $variables['attributes']['class'][] = 'glb-preview-regions--enable';
    }
  }

  /**
   * Hook implementation.
   *
   * @param array $variables
   *   The preprocessed variables.
   */
  public function preprocessImageWidgetGinLb(array &$variables): void {
    if (!$this->contextValidator->isValidTheme()) {
      return;
    }

    // This prevents image widget templates from rendering preview container
    // HTML to users that do not have permission to access these previews.
    // @todo revisit in https://drupal.org/node/953034
    // @todo revisit in https://drupal.org/node/3114318
    if (isset($variables['data']['preview']['#access']) && $variables['data']['preview']['#access'] === FALSE) {
      unset($variables['data']['preview']);
    }

    $this->preprocessFileAndImageWidget($variables);
  }

  /**
   * Hook implementation.
   *
   * This targets each new, unsaved media item added to the media library,
   * before they are saved.
   *
   * @param array $variables
   *   The preprocessed variables.
   */
  public function preprocessItemListMediaLibraryAddFormMediaList(array &$variables): void {
    foreach ($variables['items'] as &$item) {
      $item['value']['preview']['#attributes']['class'][] = 'media-library-add-form__preview';
      $item['value']['fields']['#attributes']['class'][] = 'media-library-add-form__fields';
      $item['value']['remove_button']['#attributes']['class'][] = 'media-library-add-form__remove-button';

      $item['value']['remove_button']['#attributes']['class'][] = 'button--extrasmall';
      // #source_field_name is set by AddFormBase::buildEntityFormElement()
      // to help themes and form_alter hooks identify the source field.
      $fields = &$item['value']['fields'];
      $source_field_name = $fields['#source_field_name'];

      // Set this flag, so we can remove the details element.
      $fields[$source_field_name]['widget'][0]['#do_not_wrap_in_details'] = TRUE;

      if (isset($fields[$source_field_name])) {
        $fields[$source_field_name]['#attributes']['class'][] = 'media-library-add-form__source-field';
      }
    }
  }

  /**
   * Hook implementation.
   *
   * This targets the menu of available media types in the media library's modal
   * dialog.
   *
   * @param array $variables
   *   The preprocessed variables.
   *
   * @todo Do this in the relevant template once
   *    https://www.drupal.org/project/drupal/issues/3088856 is resolved.
   */
  public function preprocessLinksMediaLibraryMenu(array &$variables): void {
    if (!$this->contextValidator->isValidTheme()) {
      return;
    }

    foreach ($variables['links'] as &$link) {
      // Add a class to the Media Library menu items.
      $link['attributes']->addClass('glb-media-library-menu__item');

      // This conditional exists because the media-library-menu__link class is
      // currently added by Classy, but Claro will eventually not use Classy as
      // a base theme.
      // @todo remove conditional, keep class addition in
      //   https://drupal.org/node/3110137
      // @see classy_preprocess_links__media_library_menu()
      if (!isset($link['link']['#options']['attributes']['class']) || !\in_array('glb-media-library-menu__link', $link['link']['#options']['attributes']['class'], TRUE)) {
        $link['link']['#options']['attributes']['class'][] = 'glb-media-library-menu__link';
      }
    }
  }

  /**
   * Hook implementation.
   *
   * This targets each media item selected in an entity reference field.
   *
   * @param array $variables
   *   The preprocessed variables.
   */
  public function preprocessMediaLibraryItem(array &$variables): void {
    if (!$this->contextValidator->isValidTheme()) {
      return;
    }

    $variables['content']['remove_button']['#attributes']['class'][] = 'media-library-item__remove';
    $variables['content']['remove_button']['#attributes']['class'][] = 'icon-link';

    if (isset($variables['content']['media_edit'])) {
      $variables['content']['media_edit']['#attributes']['class'][] = 'glb-media-library-item__edit';
    }
  }

  /**
   * Hook implementation.
   *
   * This targets each media item selected in an entity reference field.
   *
   * @param array $variables
   *   The preprocessed variables.
   */
  public function preprocessMediaLibraryItemWidget(array &$variables): void {
    $variables['content']['remove_button']['#attributes']['class'][] = 'media-library-item__remove';
    $variables['content']['remove_button']['#attributes']['class'][] = 'icon-link';
  }

  /**
   * Hook implementation.
   *
   * @param array $variables
   *   The preprocessed variables.
   */
  public function preprocessStatusMessagesGinLb(array &$variables): void {
    $variables['toastify'] = FALSE;

    $config = $this->configFactory->get('gin_lb.settings');
    if (\in_array($config->get('toastify_loading'), ['cdn', 'composer'], TRUE)) {
      $variables['toastify'] = TRUE;
    }
  }

  /**
   * Hook implementation.
   *
   * @param array $variables
   *   The preprocessed variables.
   */
  public function preprocessTable(array &$variables): void {
    if (isset($variables['attributes']['class'])
      && \is_array($variables['attributes']['class'])
      && \in_array('glb-table', $variables['attributes']['class'], TRUE)) {
      // Adding table sort indicator CSS class for inactive sort link.
      // @todo Revisit after https://www.drupal.org/node/3025726 or
      // https://www.drupal.org/node/1973418 is in.
      if (!empty($variables['header'])) {
        foreach ($variables['header'] as &$header_cell) {
          if ($header_cell['content'] instanceof Link) {
            /** @var array $query */
            $query = $header_cell['content']->getUrl()->getOption('query') ?: [];

            if (isset($query['order'], $query['sort'])) {
              $header_cell['attributes']->addClass('sortable-heading');
            }
          }
        }
      }

      // Mark the whole table and the first cells if rows are draggable.
      $draggable_row_found = FALSE;
      if (!empty($variables['rows'])) {
        foreach ($variables['rows'] as &$row) {
          if (($row['attributes'] instanceof Attribute) && $row['attributes']->hasClass('draggable')) {
            if (!$draggable_row_found) {
              $variables['attributes']['class'][] = 'draggable-table';
              $draggable_row_found = TRUE;
            }

            \reset($row['cells']);
            $first_cell_key = \key($row['cells']);
            // The 'attributes' key is always here, and it is an
            // \Drupal\Core\Template\Attribute.
            // @see template_preprocess_table();
            $row['cells'][$first_cell_key]['attributes']->addClass('tabledrag-cell');

            // Check that the first cell is empty or not.
            if (empty($row['cells'][$first_cell_key]) || empty($row['cells'][$first_cell_key]['content'])) {
              $row['cells'][$first_cell_key]['attributes']->addClass('tabledrag-cell--only-drag');
            }
          }
        }
      }

      if ($draggable_row_found) {
        $variables['#attached']['library'][] = 'gin/gin_tabledrag';
      }
    }
  }

  /**
   * Hook implementation.
   *
   * @param array $variables
   *   The preprocessed variables.
   */
  public function preprocessToolbarGinLb(array &$variables): void {
    $variables['secondary_toolbar_frontend'] = FALSE;

    if (isset($variables['route_name']) && \preg_match('#layout_builder\.overrides\.(?<entity_type_id>.+)\.view#', $variables['route_name'], $matches)) {
      $request = $this->requestStack->getCurrentRequest();
      if ($request) {
        $entity = $request->attributes->get($matches['entity_type_id']);

        if ($entity instanceof EntityInterface && $entity->hasLinkTemplate('edit-form')) {
          $variables['entity_title'] = $entity->label();
          $variables['entity_edit_url'] = $entity->toUrl('edit-form');
        }
        if ($entity instanceof EntityInterface && $entity->hasLinkTemplate('canonical')) {
          $variables['entity_view_url'] = $entity->toUrl();
        }
      }
    }
    $variables['preview_region'] = [
      '#prefix' => '<div class="glb-toolbar-menu-preview">',
      '#suffix' => '</div>',
      '#type' => 'checkbox',
      '#title' => $this->t('Preview Regions'),
      '#gin_lb_form' => TRUE,
      '#id' => 'glb-toolbar-preview-regions',
      '#default_value' => $this->configFactory->get('gin_lb.settings')->get('enable_preview_regions'),
    ];
    $variables['preview_content'] = [
      '#prefix' => '<div class="glb-toolbar-menu-preview">',
      '#suffix' => '</div>',
      '#type' => 'checkbox',
      '#title' => $this->t('Preview Content'),
      '#value' => TRUE,
      '#gin_lb_form' => TRUE,
      '#id' => 'glb-toolbar-preview-content',
    ];

    $variables['#cache']['tags'] = $this->configFactory->get('gin_lb.settings')->getCacheTags();
  }

  /**
   * Hook implementation.
   *
   * @param array $variables
   *   The preprocessed variables.
   */
  public function preprocessTopBar(&$variables): void {
    // If layout builder path.
    if ($this->contextValidator->isLayoutBuilderRoute()) {
      $variables['preview_region'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Preview Regions'),
        '#gin_lb_form' => TRUE,
        '#id' => 'glb-toolbar-preview-regions',
        '#default_value' => $this->configFactory->get('gin_lb.settings')->get('enable_preview_regions'),
      ];
      $variables['preview_content'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Preview Content'),
        '#value' => TRUE,
        '#gin_lb_form' => TRUE,
        '#id' => 'glb-toolbar-preview-content',
      ];

      $variables['#cache']['tags'] = $this->configFactory->get('gin_lb.settings')->getCacheTags();

      $variables['gin_form_actions']['gin_lb'] = [
        '#theme' => 'gin_lb_form_actions',
        '#preview_region' => $variables['preview_region'],
        '#preview_content' => $variables['preview_content'],
      ];
    }
  }

  /**
   * Helper pre-process callback for file_managed_file and image_widget.
   *
   * @param array $variables
   *   The renderable array of image and file widgets, with 'element' and 'data'
   *   keys.
   */
  protected function preprocessFileAndImageWidget(array &$variables): void {
    $element = $variables['element'];
    $main_item_keys = [
      'upload',
      'upload_button',
      'remove_button',
    ];

    // Calculate helper values for the template.
    $upload_is_accessible = !isset($element['upload']['#access']) || $element['upload']['#access'] !== FALSE;
    $is_multiple = !empty($element['#cardinality']) && $element['#cardinality'] !== 1;
    $has_value = isset($element['#value']['fids']) && !empty($element['#value']['fids']);

    // File widget properties.
    $display_can_be_displayed = !empty($element['#display_field']);
    // Display is rendered in a separate table cell for multiple value widgets.
    $display_is_visible = $display_can_be_displayed && !$is_multiple && isset($element['display']['#type']) && $element['display']['#type'] !== 'hidden';
    $description_can_be_displayed = !empty($element['#description_field']);
    $description_is_visible = $description_can_be_displayed && isset($element['description']);

    // Image widget properties.
    $alt_can_be_displayed = !empty($element['#alt_field']);
    $alt_is_visible = $alt_can_be_displayed && (!isset($element['alt']['#access']) || $element['alt']['#access'] !== FALSE);
    $title_can_be_displayed = !empty($element['#title_field']);
    $title_is_visible = $title_can_be_displayed && (!isset($element['title']['#access']) || $element['title']['#access'] !== FALSE);

    $variables['multiple'] = $is_multiple;
    $variables['upload'] = $upload_is_accessible;
    $variables['has_value'] = $has_value;
    $variables['has_meta'] = $alt_is_visible || $title_is_visible || $display_is_visible || $description_is_visible;
    $variables['display'] = $display_is_visible;

    // Render file upload input and upload button (or file name and remove
    // button, if the field is not empty) in an emphasized div.
    foreach ($variables['data'] as $key => $item) {
      $item_is_filename = isset($item['filename']['#file']) && $item['filename']['#file'] instanceof FileInterface;

      // Move filename to main items.
      if ($item_is_filename) {
        $variables['main_items']['filename'] = $item;
        unset($variables['data'][$key]);
        continue;
      }

      // Move buttons, upload input and hidden items to main items.
      if (\in_array($key, $main_item_keys, TRUE)) {
        $variables['main_items'][$key] = $item;
        unset($variables['data'][$key]);
      }
    }
  }

}
