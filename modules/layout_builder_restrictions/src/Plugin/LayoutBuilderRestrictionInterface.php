<?php

namespace Drupal\layout_builder_restrictions\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Defines an interface for Layout builder restriction plugin plugins.
 */
interface LayoutBuilderRestrictionInterface extends PluginInspectionInterface {

  /**
   * Alter the block definitions.
   *
   * This will be called when the block list is being populated
   * for placing a block into a section.
   * A plugin can manipulate the definitions as needed, with
   * optional context about the section being utilized.
   *
   * @param array $definitions
   *   All the available block definitions.
   * @param array $context
   *   At a minimum, the entity, view_mode, layout, and region.
   *   Depending on the plugin, they may or may not ignore some of
   *   these contexts.
   *
   * @return array
   *   A modified block definition array.
   */
  public function alterBlockDefinitions(array $definitions, array $context);

  /**
   * Alter the layout definitions.
   *
   * This will be called when the layout list is being populated.
   * A plugin can manipulate the definitions as needed.
   *
   * @param array $definitions
   *   All the available layout definitions.
   * @param array $context
   *   At a minimum, the entity, view_mode, layout, and region.
   *   Depending on the plugin, they may or may not ignore some of
   *   these contexts.
   *
   * @return array
   *   A modified layout definition array.
   */
  public function alterSectionDefinitions(array $definitions, array $context);

  /**
   * Determine whether the block being moved is allowed to the destination.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta_from
   *   The delta of the original section.
   * @param int $delta_to
   *   The delta of the destination section.
   * @param string $region_to
   *   The new region for this block.
   * @param string $block_uuid
   *   The UUID for this block.
   * @param string|null $preceding_block_uuid
   *   (optional) If provided, the UUID of the block to insert this block after.
   *
   * @return mixed
   *   TRUE if the block is allowed, or a string error message explaining
   *   the restriction.
   */
  public function blockAllowedinContext(SectionStorageInterface $section_storage, $delta_from, $delta_to, $region_to, $block_uuid, $preceding_block_uuid = NULL);

  /**
   * Returns an array of allowed inline blocks in a given context.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $region
   *   The region the block is going in.
   *
   * @return array
   *   An array of allowed inline block types.
   */
  public function inlineBlocksAllowedinContext(SectionStorageInterface $section_storage, $delta, $region);

}
