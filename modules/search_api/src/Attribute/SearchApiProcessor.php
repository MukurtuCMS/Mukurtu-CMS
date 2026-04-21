<?php

namespace Drupal\search_api\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the Search API processor attribute.
 *
 * @see \Drupal\search_api\Processor\ProcessorPluginManager
 * @see \Drupal\search_api\Processor\ProcessorInterface
 * @see \Drupal\search_api\Processor\ProcessorPluginBase
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class SearchApiProcessor extends Plugin {

  /**
   * Constructs a new class instance.
   *
   * @param string $id
   *   The processor plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the processor plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) The processor description.
   * @param array<string,int>|null $stages
   *   (optional) The stages this processor will run in, along with their
   *   default weights.
   *   This is represented as an associative array, mapping stage identifiers
   *   to the default weight for that stage. For the available stages, see
   *   \Drupal\search_api\Processor\ProcessorPluginManager::getProcessingStages().
   * @param bool $locked
   *   (optional) TRUE if the processor should always be enabled for all
   *   supported indexes.
   * @param bool $hidden
   *   (optional) TRUE to hide the plugin in the UI.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?array $stages = NULL,
    public readonly bool $locked = FALSE,
    public readonly bool $hidden = FALSE,
    public readonly ?string $deriver = NULL,
  ) {}

}
