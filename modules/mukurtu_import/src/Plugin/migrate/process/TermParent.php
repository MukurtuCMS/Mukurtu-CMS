<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\mukurtu_import\TermParentResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Finds a term's parent by UUID, term ID, or unique name (#2520).
 *
 * A parent that isn't found yet, for example because its own row comes later
 * in the file, is recorded and set once every row has been imported.
 *
 * Example:
 * @code
 * parent:
 *   plugin: mukurtu_term_parent
 *   source: Parent
 *   vid: location
 * @endcode
 *
 * @see \Drupal\mukurtu_import\TermParentResolver
 */
#[MigrateProcess('mukurtu_term_parent')]
class TermParent extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected MigrationInterface $migration,
    protected TermParentResolver $resolver,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('mukurtu_import.term_parent_resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value = trim((string) $value);
    if ($value === '') {
      return 0;
    }

    $vid = (string) $this->configuration['vid'];
    $parent = $this->resolver->resolve($vid, $value);
    if ($parent === NULL) {
      $this->resolver->defer($this->migration->id(), $row->getSourceIdValues(), $vid, $value);
      return 0;
    }
    return $parent;
  }

}
