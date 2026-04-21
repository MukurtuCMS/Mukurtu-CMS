<?php

namespace Drupal\devel_generate;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\devel_generate\Annotation\DevelGenerate;

/**
 * Plugin type manager for DevelGenerate plugins.
 */
class DevelGeneratePluginManager extends DefaultPluginManager {

  /**
   * Constructs a DevelGeneratePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The translation manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MessengerInterface $messenger,
    protected LanguageManagerInterface $languageManager,
    protected TranslationInterface $stringTranslation,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {
    parent::__construct('Plugin/DevelGenerate', $namespaces, $module_handler, NULL, DevelGenerate::class);
    $this->moduleHandler = $module_handler;
    $this->alterInfo('devel_generate_info');
    $this->setCacheBackend($cache_backend, 'devel_generate_plugins');
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions(): array {
    $definitions = [];
    foreach (parent::findDefinitions() as $plugin_id => $plugin_definition) {
      $plugin_available = TRUE;
      foreach ($plugin_definition['dependencies'] as $module_name) {
        // If a plugin defines module dependencies and at least one module is
        // not installed don't make this plugin available.
        if (!$this->moduleHandler->moduleExists($module_name)) {
          $plugin_available = FALSE;
          break;
        }
      }

      if ($plugin_available) {
        $definitions[$plugin_id] = $plugin_definition;
      }
    }

    return $definitions;
  }

}
