<?php

namespace Drupal\search_api\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Utility\PluginHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a config action for adding datasources to search indexes.
 */
#[ConfigAction(
  id: 'search_api_index:addDatasourceToIndex',
  admin_label: new TranslatableMarkup('Add a datasource to a search index'),
  entity_types: ['search_api_index'],
)]
final class AddDatasourceToIndex implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager.
   * @param \Drupal\search_api\Utility\PluginHelperInterface $pluginHelper
   *   The plugin helper service.
   */
  public function __construct(
    private readonly ConfigManagerInterface $configManager,
    private readonly PluginHelperInterface $pluginHelper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get('config.manager'),
      $container->get('search_api.plugin_helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    $index = $this->configManager->loadConfigEntityByName($configName);
    assert($index instanceof IndexInterface);
    assert(is_array($value) && isset($value['name'], $value['options']));

    if ($index->isValidDatasource($value['name'])) {
      throw new ConfigActionException("Datasource \"{$value['name']}\" already exists on index \"{$index->label()}\".");
    }
    try {
      $datasource = $this->pluginHelper->createDatasourcePlugin($index, $value['name'], $value['options']);
      $index->addDatasource($datasource);
      $index->save();
    }
    catch (\Exception $e) {
      throw new ConfigActionException("Error while adding datasource \"{$value['name']}\" to index \"{$index->label()}\": {$e->getMessage()}.");
    }
  }

}
