<?php

namespace Drupal\layout_builder_restrictions\Traits;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\OverridesSectionStorageInterface;

/**
 * Methods to help Layout Builder Restrictions plugins.
 */
trait PluginHelperTrait {

  use LayoutBuilderContextTrait;
  use StringTranslationTrait;

  /**
   * Gets block definitions appropriate for an entity display.
   *
   * @param \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $display
   *   The entity display being edited.
   *
   * @return array[]
   *   Keys are category names, and values are arrays of which the keys are
   *   plugin IDs and the values are plugin definitions.
   */
  protected function getBlockDefinitions(LayoutEntityDisplayInterface $display) {

    // Check for 'load' method, which only exists in > 8.7.
    if (method_exists($this->sectionStorageManager(), 'load')) {
      $section_storage = $this->sectionStorageManager()->load('defaults', ['display' => EntityContext::fromEntity($display)]);
    }
    else {
      // BC for < 8.7.
      $section_storage = $this->sectionStorageManager()->loadEmpty('defaults')->setSectionList($display);
    }
    // Do not use the plugin filterer here, but still filter by contexts.
    $definitions = $this->blockManager()->getDefinitions();

    // Create a list of block_content IDs for later filtering.
    $custom_blocks = [];
    foreach ($definitions as $key => $definition) {
      if ($definition['provider'] == 'block_content') {
        $custom_blocks[] = $key;
      }
    }

    // Allow filtering of available blocks by other parts of the system.
    $definitions = $this->contextHandler()->filterPluginDefinitionsByContexts($this->getPopulatedContexts($section_storage), $definitions);
    $grouped_definitions = $this->getDefinitionsByUntranslatedCategory($definitions);
    // Create a new category of block_content blocks that meet the context.
    foreach ($grouped_definitions as $category => $data) {
      if (empty($data['definitions'])) {
        unset($grouped_definitions[$category]);
      }
      // Ensure all block_content definitions are included in the
      // 'Custom blocks' category.
      foreach ($data['definitions'] as $key => $definition) {
        if (in_array($key, $custom_blocks)) {
          if (!isset($grouped_definitions['Custom blocks'])) {
            $grouped_definitions['Custom blocks'] = [
              'label' => 'Custom blocks',
              'data' => [],
            ];
          }
          // Remove this block_content from its previous category so
          // that it is defined only in one place.
          unset($grouped_definitions[$category]['definitions'][$key]);
          $grouped_definitions['Custom blocks']['definitions'][$key] = $definition;
        }
      }
    }

    // Generate a list of custom block types under the
    // 'Custom block types' namespace.
    $custom_block_bundles = $this->entityTypeBundleInfo()->getBundleInfo('block_content');
    if ($custom_block_bundles) {
      $grouped_definitions['Custom block types'] = [
        'label' => 'Custom block types',
        'definitions' => [],
      ];
      foreach ($custom_block_bundles as $machine_name => $value) {
        $grouped_definitions['Custom block types']['definitions'][$machine_name] = [
          'admin_label' => $value['label'],
          'category' => $this->t('Custom block types'),
        ];
      }
    }
    ksort($grouped_definitions);

    return $grouped_definitions;
  }

  /**
   * Generate a categorized list of blocks, based on the untranslated category.
   *
   * @param array $definitions
   *   The uncategorized definitions.
   *
   * @return array
   *   The categorized definitions.
   */
  protected function getDefinitionsByUntranslatedCategory(array $definitions) {
    $definitions = $this->getGroupedDefinitions($definitions, 'admin_label');
    // Do not display the 'broken' plugin in the UI.
    unset($definitions[$this->t('Block')->render()]['definitions']['broken']);
    return $definitions;
  }

  /**
   * Method to categorize blocks in a multilingual-friendly way.
   *
   * This is based on CategorizingPluginManagerTrait::getGroupedDefinitions.
   *
   * @param array|null $definitions
   *   (optional) The definitions as provided by the Block Plugin Manager.
   * @param string $label_key
   *   The key to use if a block does not have a category defined.
   *
   * @return array
   *   Definitions grouped by untranslated category.
   */
  public function getGroupedDefinitions(?array $definitions = NULL, $label_key = 'label') {
    $menu_block_active = \Drupal::moduleHandler()->moduleExists('menu_block');
    $definitions = $this->getSortedDefinitions($definitions, $label_key);
    $grouped_definitions = [];
    foreach ($definitions as $id => $definition) {
      // If the Menu Block module is active, suppress duplicative core menus.
      if ($menu_block_active && $definition['id'] === 'system_menu_block') {
        continue;
      }
      // If the block category is a translated string, get the
      // untranslated equivalent to create an unchanging category ID, not
      // affected by multilingual translations.
      $category = $this->getUntranslatedCategory($definition['category']);
      if (!isset($grouped_definitions[$category])) {
        $grouped_definitions[$category]['label'] = $category;
        // Also add the translated string in there, to use for the display of
        // the categories.
        $grouped_definitions[$category]['translated_label'] = (string) $definition['category'];
      }
      $grouped_definitions[$category]['definitions'][$id] = $definition;
    }
    return $grouped_definitions;
  }

  /**
   * Helper function to check the default block category allowlist.
   *
   * @param string $category
   *   The identifier of the category.
   * @param array $allowed_block_categories
   *   The entity view mode's allowed block categories.
   *
   * @return bool
   *   Whether or not the category is restricted.
   */
  public function categoryIsRestricted($category, array $allowed_block_categories) {
    if (!empty($allowed_block_categories)) {
      // There is no explicit indication whether the blocks from
      // this category should be restricted. Check the default allowlist.
      if (!in_array($category, $allowed_block_categories)) {
        // This block's category has not been allowlisted.
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Helper function to return an untranslated block Category.
   *
   * @param mixed $category
   *   The block category name or object.
   *
   * @return string
   *   A string representing the untranslated block category.
   */
  public function getUntranslatedCategory($category) {

    if ($category instanceof TranslatableMarkup) {
      $output = $category->getUntranslatedString();
      // Rename to match Layout Builder Restrictions naming.
      if ($output == '@entity fields') {
        $output = 'Content fields';
      }
      if ($output === "Custom") {
        $output = "Custom blocks";
      }
      // Add affordance for name change in Drupal 10.1. "Custom blocks" are now
      // "Content block". We use the legacy name for compatibility reasons.
      // See #3363076.
      if ($output === "Content block") {
        $output = "Custom blocks";
      }
    }
    else {
      $output = (string) $category;
    }

    return $output;
  }

  /**
   * Sort block categories alphabetically.
   *
   * @param array|null $definitions
   *   (optional) The block definitions, with category values.
   * @param string $label_key
   *   The module name, if no category value is present on the block.
   *
   * @return array
   *   The alphabetically sorted categories with definitions.
   */
  protected function getSortedDefinitions(?array $definitions = NULL, $label_key = 'label') {
    uasort($definitions, function ($a, $b) use ($label_key) {
      if ($a['category'] != $b['category']) {
        $a['category'] = $a['category'] ?? '';
        $b['category'] = $b['category'] ?? '';
        return strnatcasecmp($a['category'], $b['category']);
      }
      $a[$label_key] = $a[$label_key] ?? '';
      $b[$label_key] = $b[$label_key] ?? '';
      return strnatcasecmp($a[$label_key], $b[$label_key]);
    });
    return $definitions;
  }

  /**
   * A helper function to return values derivable from section storage.
   *
   * @param array $section_storage
   *   A section storage object nested in an array.
   *   - \Drupal\layout_builder\SectionStorageInterface, or
   *   - \Drupal\layout_builder\OverridesSectionStorageInterface.
   * @param string $requested_value
   *   The value to be returned.
   *
   * @return mixed
   *   The return value depends on $requested_value parameter:
   *   - contexts (array)
   *   - entity (object)
   *   - view mode (string)
   *   - bundle (string)
   *   - entity_type (string)
   *   - storage (object)
   *   - view_display (object)
   */
  public function getValuefromSectionStorage(array $section_storage, $requested_value) {
    $section_storage = array_shift($section_storage);
    $contexts = $section_storage->getContexts();
    // Provide a fallback view mode; this will be overridden by specific
    // contexts, below. We need a fallback since some entity types (such as
    // Layout entities) do not implement a view mode.
    $view_mode = 'default';

    // Initialize the $bundle variable to avoid 'Undefined variable' warnings.
    $bundle = NULL;

    // Initialize $entity_type variable to avoid 'Undefined variable' warnings.
    $entity_type = NULL;

    if ($requested_value == 'contexts') {
      return $contexts;
    }
    if ($section_storage instanceof OverridesSectionStorageInterface) {
      $entity = $contexts['entity']->getContextValue();
      $view_mode = $contexts['view_mode']->getContextValue();
      $entity_type = $entity->getEntityTypeId();
      $bundle = $entity->bundle();
    }
    elseif (isset($contexts['entity']) && $contexts['entity']->getContextValue() instanceof ConfigEntityBase) {
      $entity = $view_display = $contexts['entity']->getContextValue();
      $entity_type = $entity->getEntityTypeId();
      $bundle = $entity->bundle();
    }
    elseif (get_class($section_storage) == 'Drupal\mini_layouts\Plugin\SectionStorage\MiniLayoutSectionStorage') {
      $view_display = $contexts['display']->getContextValue();
    }
    elseif (isset($contexts['display'])) {
      $entity = $contexts['display']->getContextValue();
      $view_mode = $entity->getMode();
      $bundle = $entity->getTargetBundle();
      $entity_type = $entity->getTargetEntityTypeId();
    }
    elseif (isset($contexts['layout'])) {
      $entity = $contexts['layout']->getContextValue();
      $bundle = $entity->getTargetBundle();
      $entity_type = $entity->getTargetEntityType();
    }
    switch ($requested_value) {
      case 'entity':
        return $entity;

      case 'view_mode':
        return $view_mode;

      case 'bundle':
        return $bundle;

      case 'entity_type':
        return $entity_type;
    }

    $context = $entity_type . "." . $bundle . "." . $view_mode;
    $storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
    if ($requested_value == 'storage') {
      return $storage;
    }
    if (empty($view_display)) {
      $view_display = $storage->load($context);
    }
    if ($requested_value == 'view_display') {
      return $view_display;
    }

    $third_party_settings = $view_display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', []);
    if ($requested_value == 'third_party_settings') {
      return $third_party_settings;
    }

    return NULL;
  }

  /**
   * Gets a list of all plugins available as Inline Blocks.
   *
   * @return array
   *   An array of inline block plugins.
   */
  public function getInlineBlockPlugins() {
    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('block_content');
    $inline_blocks = [];
    foreach ($bundles as $machine_name => $bundle) {
      $inline_blocks[] = 'inline_block:' . $machine_name;
    }
    return $inline_blocks;
  }

  /**
   * Gets layout definitions.
   *
   * @return array[]
   *   Keys are layout machine names, and values are layout definitions.
   */
  protected function getLayoutDefinitions() {
    return $this->layoutManager()->getFilteredDefinitions('layout_builder', []);
  }

  /**
   * Gets the section storage manager.
   *
   * @return \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   *   The section storage manager.
   */
  private function sectionStorageManager() {
    return $this->sectionStorageManager ?? \Drupal::service('plugin.manager.layout_builder.section_storage');
  }

  /**
   * Gets the block manager.
   *
   * @return \Drupal\Core\Block\BlockManagerInterface
   *   The block manager.
   */
  private function blockManager() {
    return $this->blockManager ?? \Drupal::service('plugin.manager.block');
  }

  /**
   * Gets the layout plugin manager.
   *
   * @return \Drupal\Core\Layout\LayoutPluginManagerInterface
   *   The layout plugin manager.
   */
  private function layoutManager() {
    return $this->layoutManager ?? \Drupal::service('plugin.manager.core.layout');
  }

  /**
   * Gets the context handler.
   *
   * @return \Drupal\Core\Plugin\Context\ContextHandlerInterface
   *   The context handler.
   */
  private function contextHandler() {
    return $this->contextHandler ?? \Drupal::service('context.handler');
  }

  /**
   * Gets the entity bundle interface.
   *
   * @return \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   *   An interface for an entity type bundle info.
   */
  private function entityTypeBundleInfo() {
    return $this->entityTypeBundleInfo ?? \Drupal::service('entity_type.bundle.info');
  }

}
