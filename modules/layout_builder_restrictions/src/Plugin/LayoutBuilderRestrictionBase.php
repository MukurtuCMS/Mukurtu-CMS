<?php

namespace Drupal\layout_builder_restrictions\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_restrictions\Traits\PluginHelperTrait;

/**
 * Base class for Layout builder restriction plugin plugins.
 */
abstract class LayoutBuilderRestrictionBase extends PluginBase implements LayoutBuilderRestrictionInterface, ContainerFactoryPluginInterface {

  use PluginHelperTrait;

  /**
   * Alter the block definitions.
   */
  public function alterBlockDefinitions(array $definitions, array $context) {
    return $definitions;
  }

  /**
   * Alter the section definitions.
   */
  public function alterSectionDefinitions(array $definitions, array $context) {
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function blockAllowedinContext(SectionStorageInterface $section_storage, $delta_from, $delta_to, $region_to, $block_uuid, $preceding_block_uuid = NULL) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function inlineBlocksAllowedinContext(SectionStorageInterface $section_storage, $delta, $region) {
    return $this->getInlineBlockPlugins();
  }

}
