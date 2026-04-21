<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an Authentication attribute object.
 *
 * Plugin namespace: Plugin\migrate_plus\authentication.
 *
 * @see \Drupal\migrate_plus\AuthenticationPluginBase
 * @see \Drupal\migrate_plus\AuthenticationPluginInterface
 * @see \Drupal\migrate_plus\AuthenticationPluginManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Authentication extends AttributeBase {

  /**
   * Constructs an Authentication attribute object.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $title
   *   The title of the plugin.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    string $id,
    public readonly TranslatableMarkup|string $title,
    public readonly ?string $deriver = NULL,
  ) {
    parent::__construct($id);
  }

}
