<?php

declare(strict_types=1);

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Keeps a Mukurtu CMS v3 term's parent.
 *
 * The term migrations keep each term's ID, so the source parent ID is also the
 * destination parent ID, even when the parent hasn't been migrated yet.
 *
 * Mukurtu vocabularies are flat by default, and the flat_taxonomy module
 * removes the parent from any term saved to a flat vocabulary. So the first
 * nested term turns nesting on for its vocabulary, before the term is saved.
 * The migration's vid must be processed before its parent. A vocabulary with no
 * nested terms stays flat.
 *
 * A term can have only one parent in Mukurtu 4, so a term with several keeps
 * the first and the rest are logged.
 */
#[MigrateProcess(
  id: 'mukurtu_v3_term_parent',
  handle_multiples: TRUE,
)]
class V3TermParent extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a V3TermParent process plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
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
    $parents = array_values(array_filter(array_map('intval', (array) $value)));
    if (!$parents) {
      return 0;
    }

    $this->allowNesting((string) $row->getDestinationProperty('vid'), $migrate_executable);

    $parent = array_shift($parents);
    if ($parents) {
      $migrate_executable->saveMessage(sprintf('The term "%s" has more than one parent in Mukurtu CMS v3. It was kept under term %d, and these parents were left out: %s.', $row->getSourceProperty('name'), $parent, implode(', ', $parents)), MigrationInterface::MESSAGE_INFORMATIONAL);
    }
    return $parent;
  }

  /**
   * Turns nesting on for a flat vocabulary.
   *
   * Vocabulary config is loaded free of overrides, so a translated name is
   * never saved over the original.
   */
  protected function allowNesting(string $vid, MigrateExecutableInterface $migrate_executable): void {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    $vocabulary = $storage->loadOverrideFree($vid);
    if (!$vocabulary || !$vocabulary->getThirdPartySetting('flat_taxonomy', 'flat')) {
      return;
    }
    $vocabulary->unsetThirdPartySetting('flat_taxonomy', 'flat')->save();
    $migrate_executable->saveMessage(sprintf('Turned on nested terms for the %s vocabulary, because the Mukurtu CMS v3 terms in it are nested.', $vocabulary->label()), MigrationInterface::MESSAGE_INFORMATIONAL);
  }

}
