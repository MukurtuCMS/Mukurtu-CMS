<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\ConfigurableLanguageInterface;

/**
 * Config schema for contrib config that ships without a complete schema.
 *
 * Without these, saving the config fails strict schema checks, which every
 * Functional test that installs the profile runs under (#2386).
 */
final class ConfigSchemaHooks {

  public function __construct(
    private readonly LanguageManagerInterface $languageManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly TypedConfigManagerInterface $typedConfigManager,
  ) {}

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    // Pathauto stores each selection condition's UUID inside the condition,
    // but types the condition as a core condition plugin, which has no uuid.
    if ($this->moduleHandler->moduleExists('pathauto') && isset($definitions['condition.plugin']['mapping'])) {
      $definitions['condition.plugin']['mapping']['uuid'] = [
        'type' => 'uuid',
        'label' => 'UUID',
      ];
    }

    // consent_popup ships no schema, and keeps its text under one key per
    // configurable language.
    if (isset($definitions['block.settings.consent_popup'])) {
      foreach (array_keys($this->languageManager->getLanguages()) as $langcode) {
        $definitions['block.settings.consent_popup']['mapping'][$langcode] = [
          'type' => 'mukurtu_core.consent_popup_text',
          'label' => 'Popup text',
        ];
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for configurable_language.
   */
  #[Hook('configurable_language_insert')]
  public function configurableLanguageInsert(ConfigurableLanguageInterface $language): void {
    $this->typedConfigManager->clearCachedDefinitions();
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for configurable_language.
   */
  #[Hook('configurable_language_delete')]
  public function configurableLanguageDelete(ConfigurableLanguageInterface $language): void {
    $this->typedConfigManager->clearCachedDefinitions();
  }

}
