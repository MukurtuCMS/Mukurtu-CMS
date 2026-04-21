<?php

namespace Drupal\layout_builder_restrictions\Form;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder_restrictions\Traits\PluginHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Supplement form UI to add setting for which blocks & layouts are available.
 */
class FormAlter implements ContainerInjectionInterface {

  use PluginHelperTrait;
  use DependencySerializationTrait;

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected $sectionStorageManager;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The layout manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutManager;

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * FormAlter constructor.
   *
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Block\LayoutPluginManagerInterface $layout_manager
   *   The layout plugin manager.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler.
   */
  public function __construct(SectionStorageManagerInterface $section_storage_manager, BlockManagerInterface $block_manager, LayoutPluginManagerInterface $layout_manager, ContextHandlerInterface $context_handler) {
    $this->sectionStorageManager = $section_storage_manager;
    $this->blockManager = $block_manager;
    $this->layoutManager = $layout_manager;
    $this->contextHandler = $context_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.layout_builder.section_storage'),
      $container->get('plugin.manager.block'),
      $container->get('plugin.manager.core.layout'),
      $container->get('context.handler')
    );
  }

  /**
   * The actual form elements.
   */
  public function alterEntityViewDisplayFormAllowedBlockCategories(&$form, FormStateInterface $form_state, $form_id) {
    $display = $form_state->getFormObject()->getEntity();
    $is_enabled = $display->isLayoutBuilderEnabled();
    if ($is_enabled) {
      $form['#entity_builders'][] = [$this, 'entityFormEntityBuild'];
      $allowed_block_categories = $display->getThirdPartySetting('layout_builder_restrictions', 'allowed_block_categories', []);
      $form['layout']['layout_builder_restrictions']['allowed_block_categories'] = [
        '#title' => $this->t('Default restriction for new categories of blocks not listed below.'),
        '#description_display' => 'before',
        '#type' => 'radios',
        '#options' => [
          "allowed" => $this->t('Allow all blocks from newly available categories.'),
          "restricted" => $this->t('Restrict all blocks from newly available categories.'),
        ],
        '#parents' => [
          'layout_builder_restrictions',
          'allowed_block_categories',
        ],
        '#default_value' => !empty($allowed_block_categories) ? "restricted" : "allowed",
      ];
    }
  }

  /**
   * The actual form elements.
   */
  public function alterEntityViewDisplayForm(&$form, FormStateInterface $form_state, $form_id) {
    $display = $form_state->getFormObject()->getEntity();
    $is_enabled = $display->isLayoutBuilderEnabled();
    if ($is_enabled) {
      $form['#entity_builders'][] = [$this, 'entityFormEntityBuild'];
      // Block settings.
      $form['layout']['layout_builder_restrictions']['allowed_blocks'] = [
        '#type' => 'details',
        '#title' => $this->t('Blocks available for placement (all layouts & regions)'),
        '#states' => [
          'disabled' => [
            ':input[name="layout[enabled]"]' => ['checked' => FALSE],
          ],
          'invisible' => [
            ':input[name="layout[enabled]"]' => ['checked' => FALSE],
          ],
        ],
      ];
      $third_party_settings = $display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', []);
      $allowlisted_blocks = (isset($third_party_settings['allowlisted_blocks'])) ? $third_party_settings['allowlisted_blocks'] : [];
      $denylisted_blocks = (isset($third_party_settings['denylisted_blocks'])) ? $third_party_settings['denylisted_blocks'] : [];
      $restricted_categories = (isset($third_party_settings['restricted_categories'])) ? $third_party_settings['restricted_categories'] : [];
      $allowed_block_categories = $display->getThirdPartySetting('layout_builder_restrictions', 'allowed_block_categories', []);
      foreach ($this->getBlockDefinitions($display) as $category => $data) {
        $category = $this->getUntranslatedCategory($category);
        $title = $data['label'];
        if (!empty($data['translated_label']) && $category != 'Custom blocks') {
          $title = $data['translated_label'];
        }
        $category_form = [
          '#type' => 'fieldset',
          '#title' => $title,
          '#parents' => ['layout_builder_restrictions', 'allowed_blocks'],
        ];
        // Check whether this is a newly available category that has been
        // restricted previously.
        $category_is_restricted = (!empty($allowed_block_categories) && !in_array($category, $allowed_block_categories));
        // The category is 'restricted' if it's already been specified as such,
        // or if the default behavior for new categories indicate such.
        if (in_array($category, array_keys($allowlisted_blocks))) {
          $category_setting = 'allowlisted';
        }
        elseif (in_array($category, array_keys($denylisted_blocks))) {
          $category_setting = 'denylisted';
        }
        elseif ($category_is_restricted) {
          $category_setting = 'restrict_all';
        }
        elseif (in_array($category, $restricted_categories)) {
          $category_setting = 'restrict_all';
        }
        else {
          $category_setting = 'all';
        }
        $category_form['restriction_behavior'] = [
          '#type' => 'radios',
          '#options' => [
            "all" => $this->t('Allow all existing & new %category blocks.', ['%category' => $data['label']]),
            "restrict_all" => $this->t('Restrict all existing & new %category blocks.', ['%category' => $data['label']]),
            "allowlisted" => $this->t('Allow specific %category blocks:', ['%category' => $data['label']]),
            "denylisted" => $this->t('Restrict specific %category blocks:', ['%category' => $data['label']]),
          ],
          '#default_value' => $category_setting,
          '#parents' => [
            'layout_builder_restrictions',
            'allowed_blocks',
            $category,
            'restriction',
          ],
        ];
        $category_form['available_blocks'] = [
          '#type' => 'container',
          '#states' => [
            'invisible' => [
              [':input[name="layout_builder_restrictions[allowed_blocks][' . $category . '][restriction]"]' => ['value' => "all"]],
              [':input[name="layout_builder_restrictions[allowed_blocks][' . $category . '][restriction]"]' => ['value' => "restrict_all"]],
            ],
          ],
        ];
        foreach ($data['definitions'] as $block_id => $block) {
          $enabled = FALSE;
          if ($category_setting == 'allowlisted' && isset($allowlisted_blocks[$category]) && in_array($block_id, $allowlisted_blocks[$category])) {
            $enabled = TRUE;
          }
          elseif ($category_setting == 'denylisted' && isset($denylisted_blocks[$category]) && in_array($block_id, $denylisted_blocks[$category])) {
            $enabled = TRUE;
          }
          $category_form['available_blocks'][$block_id] = [
            '#type' => 'checkbox',
            '#title' => $block['admin_label'],
            '#default_value' => $enabled,
            '#parents' => [
              'layout_builder_restrictions',
              'allowed_blocks',
              $category,
              'available_blocks',
              $block_id,
            ],
          ];
        }
        if ($category == 'Custom blocks' || $category == 'Custom block types') {
          $category_form['description'] = [
            '#type' => 'container',
            '#children' => $this->t('<p>In the event both <em>Custom Block Types</em> and <em>Content Blocks</em> restrictions are enabled, <em>Custom Block Types</em> restrictions are disregarded.</p>'),
            '#states' => [
              'visible' => [
                ':input[name="layout_builder_restrictions[allowed_blocks][' . $category . '][restriction]"]' => ['value' => "restricted"],
              ],
            ],
          ];
        }
        $form['layout']['layout_builder_restrictions']['allowed_blocks'][$category] = $category_form;
      }
      // Layout settings.
      $allowed_layouts = (isset($third_party_settings['allowed_layouts'])) ? $third_party_settings['allowed_layouts'] : [];
      $layout_form = [
        '#type' => 'details',
        '#title' => $this->t('Layouts available for sections'),
        '#parents' => ['layout_builder_restrictions', 'allowed_layouts'],
        '#states' => [
          'disabled' => [
            ':input[name="layout[enabled]"]' => ['checked' => FALSE],
          ],
          'invisible' => [
            ':input[name="layout[enabled]"]' => ['checked' => FALSE],
          ],
        ],
      ];
      $layout_form['layout_restriction'] = [
        '#type' => 'radios',
        '#options' => [
          "all" => $this->t('Allow all existing & new layouts.'),
          "restricted" => $this->t('Allow only specific layouts:'),
        ],
        '#default_value' => !empty($allowed_layouts) ? "restricted" : "all",
      ];
      $definitions = $this->getLayoutDefinitions();
      foreach ($definitions as $plugin_id => $definition) {
        $enabled = FALSE;
        if (!empty($allowed_layouts) && in_array($plugin_id, $allowed_layouts)) {
          $enabled = TRUE;
        }
        $layout_form['layouts'][$plugin_id] = [
          '#type' => 'checkbox',
          '#default_value' => $enabled,
          '#description' => [
            $definition->getIcon(60, 80, 1, 3),
            [
              '#type' => 'container',
              '#children' => $definition->getLabel() . ' (' . $plugin_id . ')',
            ],
          ],
          '#states' => [
            'invisible' => [
              ':input[name="layout_builder_restrictions[allowed_layouts][layout_restriction]"]' => ['value' => "all"],
            ],
          ],
        ];
      }
      $form['layout']['layout_builder_restrictions']['allowed_layouts'] = $layout_form;
    }
  }

  /**
   * Save allowed blocks & layouts for the given entity view mode.
   */
  public function entityFormEntityBuild($entity_type_id, LayoutEntityDisplayInterface $display, &$form, FormStateInterface &$form_state) {
    $third_party_settings = $display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction');
    $block_restrictions = $this->setAllowedBlocks($form_state);
    $third_party_settings['allowlisted_blocks'] = $block_restrictions['allowlisted'] ?? [];
    $third_party_settings['denylisted_blocks'] = $block_restrictions['denylisted'] ?? [];
    $third_party_settings['restricted_categories'] = $block_restrictions['restricted_categories'];
    $third_party_settings['allowed_layouts'] = $this->setAllowedLayouts($form_state);
    $allowed_block_categories = $this->setAllowedBlockCategories($form_state, $display);
    // Save!
    $display->setThirdPartySetting('layout_builder_restrictions', 'allowed_block_categories', $allowed_block_categories);
    $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', $third_party_settings);
  }

  /**
   * Helper function to prepare saved allowed blocks.
   *
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   An array of layout names or empty.
   */
  protected function setAllowedBlocks(FormStateInterface $form_state) {
    $categories = $form_state->getValue([
      'layout_builder_restrictions',
      'allowed_blocks',
    ]);
    $block_restrictions = [];
    $block_restrictions['restricted_categories'] = [];
    if (!empty($categories)) {
      foreach ($categories as $category => $settings) {
        $restriction_type = $settings['restriction'];
        if (in_array($restriction_type, ['allowlisted', 'denylisted'])) {
          $block_restrictions[$restriction_type][$category] = [];
          foreach ($settings['available_blocks'] as $block_id => $block_setting) {
            if ($block_setting == '1') {
              // Include only checked blocks.
              $block_restrictions[$restriction_type][$category][] = $block_id;
            }
          }
        }
        elseif ($restriction_type === "restrict_all") {
          $block_restrictions['restricted_categories'][] = $category;
        }
      }
    }
    return $block_restrictions;
  }

  /**
   * Helper function to prepare saved allowed layouts.
   *
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   An array of layout names or empty.
   */
  protected function setAllowedLayouts(FormStateInterface $form_state) {
    // Set allowed layouts.
    $layout_restriction = $form_state->getValue([
      'layout_builder_restrictions',
      'allowed_layouts',
      'layout_restriction',
    ]);
    $allowed_layouts = [];
    if ($layout_restriction == 'restricted') {
      $allowed_layouts = array_keys(array_filter($form_state->getValue([
        'layout_builder_restrictions',
        'allowed_layouts',
        'layouts',
      ])));
    }
    return $allowed_layouts;
  }

  /**
   * Helper function to prepare saved block definition categories.
   *
   * @return array
   *   An array of block category names or empty.
   */
  protected function setAllowedBlockCategories(FormStateInterface $form_state, LayoutEntityDisplayInterface $display) {
    // Set default for allowed block categories.
    $block_category_default = $form_state->getValue([
      'layout_builder_restrictions',
      'allowed_block_categories',
    ]);
    if ($block_category_default == 'restricted') {
      // Create a allowlist of categories whose blocks should be allowed.
      // Newly available categories' blocks not in this list will be
      // disallowed.
      $allowed_block_categories = array_keys($this->getBlockDefinitions($display));
    }
    else {
      // The UI choice indicates that all newly available categories'
      // blocks should be allowed by default. Represent this in the schema
      // as an empty array.
      $allowed_block_categories = [];
    }
    return $allowed_block_categories;
  }

}
