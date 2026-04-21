<?php

namespace Drupal\layout_builder_restrictions_by_region\Plugin\LayoutBuilderRestriction;

use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_restrictions\Plugin\LayoutBuilderRestrictionBase;
use Drupal\layout_builder_restrictions\Traits\PluginHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controls behavior of the by region plugin.
 *
 * @LayoutBuilderRestriction(
 *   id = "entity_view_mode_restriction_by_region",
 *   title = @Translation("Per Layout Region"),
 *   description = @Translation("Restrict blocks to specific regions for each allowed layout. If you enable this after configuring the 'Per Entity View Mode' restriction, you will need to resave each entity's layout restrictions.")
 * )
 */
class EntityViewModeRestrictionByRegion extends LayoutBuilderRestrictionBase {

  use PluginHelperTrait;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Database connection service.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, Connection $connection) {
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->moduleHandler = $module_handler;
    $this->database = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alterBlockDefinitions(array $definitions, array $context) {
    // If this method is being called by any action other than 'Add block',
    // then do nothing.
    // @todo Re-assess after https://www.drupal.org/node/3099121
    // has been addressed.
    if (!isset($context['delta'])) {
      return $definitions;
    }
    // Respect restrictions on allowed blocks specified by the section storage.
    if (isset($context['section_storage'])) {
      $default = $context['section_storage'] instanceof OverridesSectionStorageInterface ? $context['section_storage']->getDefaultSectionStorage() : $context['section_storage'];
      if ($default instanceof ThirdPartySettingsInterface) {
        $third_party_settings = $default->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_by_region', []);
        if (empty($third_party_settings)) {
          // This entity has no restrictions. Look no further.
          return $definitions;
        }

        $layout_id = $context['section_storage']->getSection($context['delta'])->getLayoutId();
        $region = $context['region'];

        $allowed_block_categories = $default->getThirdPartySetting('layout_builder_restrictions', 'allowed_block_categories', []);
        $allowlisted_blocks = (isset($third_party_settings['allowlisted_blocks'][$layout_id])) ? $third_party_settings['allowlisted_blocks'][$layout_id] : [];
        $denylisted_blocks = (isset($third_party_settings['denylisted_blocks'][$layout_id])) ? $third_party_settings['denylisted_blocks'][$layout_id] : [];
        $restricted_categories = (isset($third_party_settings['restricted_categories'][$layout_id])) ? $third_party_settings['restricted_categories'][$layout_id] : [];

        // If restriction applies to all regions, then overwrite region
        // to 'all_regions'.
        if (isset($allowlisted_blocks['all_regions']) || isset($denylisted_blocks['all_regions']) || isset($restricted_categories['all_regions'])) {
          $region = 'all_regions';
        }

        // Filter blocks from entity-specific SectionStorage (i.e., UI).
        $content_block_types_by_uuid = $this->getBlockTypeByUuid();

        if (!empty($allowlisted_blocks) || !empty($denylisted_blocks) || !empty($restricted_categories)) {
          foreach ($definitions as $delta => $definition) {
            $original_delta = $delta;
            $category = $this->getUntranslatedCategory($definition['category']);
            // Content blocks get special treatment.
            if ($definition['provider'] == 'block_content') {
              // 'Custom block types' are disregarded if 'Content blocks'
              // restrictions are enabled.
              if (isset($allowlisted_blocks[$region]['Custom blocks']) || isset($denylisted_blocks[$region]['Custom blocks'])) {
                $category = 'Custom blocks';
              }
              else {
                $category = 'Custom block types';
                $delta_exploded = explode(':', $delta);
                $uuid = $delta_exploded[1];
                $delta = $content_block_types_by_uuid[$uuid];
              }
            }
            if (isset($restricted_categories[$region]) && in_array($category, $restricted_categories[$region])) {
              unset($definitions[$original_delta]);
            }
            elseif (isset($allowlisted_blocks[$region]) && in_array($category, array_keys($allowlisted_blocks[$region]))) {
              if (!in_array($delta, $allowlisted_blocks[$region][$category])) {
                // The current block is not allowlisted. Remove it.
                unset($definitions[$original_delta]);
              }
            }
            elseif (isset($denylisted_blocks[$region]) && in_array($category, array_keys($denylisted_blocks[$region]))) {
              if (in_array($delta, $denylisted_blocks[$region][$category])) {
                // The current block is denylisted. Remove it.
                unset($definitions[$original_delta]);
              }
            }
            elseif ($this->categoryIsRestricted($category, $allowed_block_categories)) {
              unset($definitions[$original_delta]);
            }
          }
        }
      }
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function alterSectionDefinitions(array $definitions, array $context) {
    // Respect restrictions on allowed layouts specified by section storage.
    if (isset($context['section_storage'])) {
      $default = $context['section_storage'] instanceof OverridesSectionStorageInterface ? $context['section_storage']->getDefaultSectionStorage() : $context['section_storage'];
      if ($default instanceof ThirdPartySettingsInterface) {
        $third_party_settings = $default->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_by_region', []);
        $allowed_layouts = (isset($third_party_settings['allowed_layouts'])) ? $third_party_settings['allowed_layouts'] : [];
        // Filter blocks from entity-specific SectionStorage (i.e., UI).
        if (!empty($allowed_layouts)) {
          $definitions = array_intersect_key($definitions, array_flip($allowed_layouts));
        }
      }
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function blockAllowedinContext(SectionStorageInterface $section_storage, $delta_from, $delta_to, $region_to, $block_uuid, $preceding_block_uuid = NULL) {
    $has_restrictions = FALSE;

    $view_display = $this->getValuefromSectionStorage([$section_storage], 'view_display');

    $third_party_settings = [];
    if (isset($view_display) && $view_display instanceof ThirdPartySettingsInterface) {
      $third_party_settings = $view_display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_by_region', []);
    }
    if (empty($third_party_settings)) {
      // This entity has no restrictions. Look no further.
      return TRUE;
    }
    // There ARE restrictions. Start by assuming *this* block is not restricted.
    $has_restrictions = FALSE;

    $bundle = $this->getValuefromSectionStorage([$section_storage], 'bundle');

    // Get "from" section and layout id. (not needed?)
    $section_from = $section_storage->getSection($delta_from);

    // Get "to" section and layout id.
    $region = $section_storage->getSection($delta_to);
    $layout_id = $region->getLayoutId();

    // Get region restrictions.
    $allowlisted_blocks = (isset($third_party_settings['allowlisted_blocks'][$layout_id])) ? $third_party_settings['allowlisted_blocks'][$layout_id] : [];
    $denylisted_blocks = (isset($third_party_settings['denylisted_blocks'][$layout_id])) ? $third_party_settings['denylisted_blocks'][$layout_id] : [];
    $restricted_categories = (isset($third_party_settings['restricted_categories'][$layout_id])) ? $third_party_settings['restricted_categories'][$layout_id] : [];

    // If restriction applies to all regions, then overwrite region_to
    // to 'all_regions'.
    if (isset($third_party_settings['allowlisted_blocks'][$layout_id]['all_regions']) || isset($third_party_settings['denylisted_blocks'][$layout_id]['all_regions']) || isset($third_party_settings['restricted_categories'][$layout_id]['all_regions'])) {
      $region_to = 'all_regions';
    }

    // Get block information.
    $component = $section_from->getComponent($block_uuid)->toArray();
    $block_id = $component['configuration']['id'];
    $block_id_parts = explode(':', $block_id);

    // Load the plugin definition.
    if ($definition = $this->blockManager()->getDefinition($block_id)) {
      $category = $this->getUntranslatedCategory($definition['category']);
      if (isset($allowlisted_blocks[$region_to][$category]) || isset($denylisted_blocks[$region_to][$category])) {
        // If there is a restriction, assume this block is restricted.
        // If the block is allowlisted or NOT denylisted,
        // the restriction will be removed, below.
        $has_restrictions = TRUE;
      }
      if (isset($restricted_categories[$region_to]) && in_array($category, array_values($restricted_categories[$region_to]))) {
        $has_restrictions = TRUE;
      }
      elseif (!isset($restricted_categories[$region_to][$category]) && !isset($denylisted_blocks[$region_to][$category]) && !isset($allowlisted_blocks[$region_to][$category]) && $category != "Custom blocks") {
        // No restrictions have been placed on this category.
        $has_restrictions = FALSE;
      }
      else {
        // Some type of restriction has been placed.
        if (isset($allowlisted_blocks[$region_to][$category])) {
          // An explicitly allowlisted block means it's allowed.
          if (in_array($block_id, $allowlisted_blocks[$region_to][$category])) {
            $has_restrictions = FALSE;
          }
        }
        elseif (isset($denylisted_blocks[$region_to][$category])) {
          // If absent from the denylist, it's allowed.
          if (!in_array($block_id, $denylisted_blocks[$region_to][$category])) {
            $has_restrictions = FALSE;
          }
        }
      }

      // Edge case: if block *type* restrictions are present...
      if (!empty($allowlisted_blocks[$region_to]['Custom block types'])) {
        $content_block_types_by_uuid = $this->getBlockTypeByUuid();
        // If no specific custom block restrictions are set
        // check block type restrict by block type.
        if ($category == 'Custom blocks' && !isset($allowlisted_blocks[$region_to]['Custom blocks'])) {
          $block_bundle = $content_block_types_by_uuid[end($block_id_parts)];
          if (in_array($block_bundle, $allowlisted_blocks[$region_to]['Custom block types'])) {
            // There are block type restrictions AND
            // this block type has been allowlisted.
            $has_restrictions = FALSE;
          }
          else {
            // There are block type restrictions BUT
            // this block type has NOT been allowlisted.
            $has_restrictions = TRUE;
          }
        }
      }
      elseif (!empty($denylisted_blocks[$region_to]['Custom block types'])) {
        $content_block_types_by_uuid = $this->getBlockTypeByUuid();
        // If no specific custom block restrictions are set
        // check block type restrict by block type.
        if ($category == 'Custom blocks' && !isset($denylisted_blocks[$region_to]['Custom blocks'])) {
          $block_bundle = $content_block_types_by_uuid[end($block_id_parts)];
          if (in_array($block_bundle, $denylisted_blocks[$region_to]['Custom block types'])) {
            // There are block type restrictions AND
            // this block type has been denylisted.
            $has_restrictions = TRUE;
          }
          else {
            // There are block type restrictions BUT
            // this block type has NOT been denylisted.
            $has_restrictions = FALSE;
          }
        }
      }
      if ($has_restrictions) {
        return $this->t("There is a restriction on %block placement in the %layout %region region for %type content.", [
          "%block" => $definition['admin_label'],
          "%layout" => $layout_id,
          "%region" => $region_to,
          "%type" => $bundle,
        ]);
      }
    }

    // Default: this block is not restricted.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function inlineBlocksAllowedinContext(SectionStorageInterface $section_storage, $delta, $region) {
    $view_display = $this->getValuefromSectionStorage([$section_storage], 'view_display');

    $third_party_settings = [];
    if (isset($view_display) && $view_display instanceof ThirdPartySettingsInterface) {
      $third_party_settings = $view_display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_by_region', []);
    }
    $allowlisted_blocks = (isset($third_party_settings['allowlisted_blocks'])) ? $third_party_settings['allowlisted_blocks'] : [];
    $denylisted_blocks = (isset($third_party_settings['denylisted_blocks'])) ? $third_party_settings['denylisted_blocks'] : [];
    $restricted_categories = (isset($third_party_settings['restricted_categories'])) ? $third_party_settings['restricted_categories'] : [];

    $layout_id = $section_storage->getSection($delta)->getLayoutId();

    // If restriction behavior is for all regions, then overwrite
    // region with 'all_regions'.
    if (isset($third_party_settings['allowlisted_blocks'][$layout_id]['all_regions']) || isset($third_party_settings['denylisted_blocks'][$layout_id]['all_regions']) || isset($third_party_settings['restricted_categories'][$layout_id]['all_regions'])) {
      $region = 'all_regions';
    }
    $inline_blocks_category = 'Inline blocks';

    // Check if the whole category is restricted.
    if (in_array($inline_blocks_category, $restricted_categories[$layout_id][$region] ?? [])) {
      return [];
    }

    // Check if allowed inline blocks are defined in config.
    if (isset($allowlisted_blocks[$layout_id][$region][$inline_blocks_category])) {
      return $allowlisted_blocks[$layout_id][$region][$inline_blocks_category];
    }
    // If not, then allow some inline blocks and check for denylisting.
    else {
      $inline_blocks = $this->getInlineBlockPlugins();
      if (isset($denylisted_blocks[$layout_id][$region][$inline_blocks_category])) {
        foreach ($inline_blocks as $key => $block) {
          // Unset explicitly denylisted inline blocks.
          if (in_array($block, $denylisted_blocks[$layout_id][$region][$inline_blocks_category])) {
            unset($inline_blocks[$key]);
          }
        }
      }
      return $inline_blocks;
    }
  }

  /**
   * Helper function to retrieve uuid->type keyed block array.
   *
   * @return str[]
   *   A key-value array of uuid-block type.
   */
  private function getBlockTypeByUuid() {
    if ($this->moduleHandler->moduleExists('block_content')) {
      // Pre-load all reusable blocks by UUID to retrieve block type.
      $query = $this->database->select('block_content', 'b')
        ->fields('b', ['uuid', 'type']);
      $query->join('block_content_field_data', 'bc', 'b.id = bc.id');
      $query->condition('bc.reusable', 1);
      $results = $query->execute();
      return $results->fetchAllKeyed(0, 1);
    }
    return [];
  }

}
