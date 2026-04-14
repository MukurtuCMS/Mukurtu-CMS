<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This plugin takes an entity ID and lookups up the current revision.
 */
#[MigrateProcess('current_entity_revision')]
class CurrentEntityRevision extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a CurrentEntityRevision object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $entity_type = $this->configuration['entity_type'] ?? NULL;

    if (!$entity_type) {
      throw new MigrateException('You must specify an entity type.');
    }

    $target_id = $value;
    if (is_array($value)) {
      $target_id = $value['target_id'] ?? NULL;
      if (!$target_id) {
        throw new MigrateException(sprintf('"%s" is not a valid entity reference.', json_encode($value)));
      }
    }

    // We kinda need a non-null id to have a hope of loading something.
    if (!$target_id) {
      throw new MigrateException(sprintf('"%s" is not a valid entity reference.', $value));
    }

    try {
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($target_id);
      if (!$entity instanceof RevisionableInterface) {
        throw new MigrateException(sprintf('Could not lookup current revision for ID "%s".', $target_id));
      }
      return [
        'target_id' => (int) $target_id,
        'target_revision_id' => (int) $entity->getRevisionId(),
      ];
    }
    catch (PluginException $e) {
      throw new MigrateException(sprintf('Could not lookup current revision for ID "%s": %s', $target_id, $e->getMessage()));
    }
  }

}
