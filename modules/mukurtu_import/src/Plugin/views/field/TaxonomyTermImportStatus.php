<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\views\field;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays whether a taxonomy term was newly created or updated during import.
 */
#[ViewsField("mukurtu_import_term_status")]
class TaxonomyTermImportStatus extends FieldPluginBase implements ContainerFactoryPluginInterface {

  protected array $revisionCounts = [];


  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $this->field_alias = $this->query->addField($this->tableAlias, 'tid');
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values): void {
    $tids = [];
    foreach ($values as $row) {
      $tid = (int) $this->getValue($row);
      if ($tid) {
        $tids[] = $tid;
      }
    }
    if (empty($tids)) {
      return;
    }
    $query = $this->database->select('taxonomy_term_revision', 'r')
      ->condition('r.tid', $tids, 'IN')
      ->groupBy('r.tid');
    $query->addField('r', 'tid');
    $query->addExpression('COUNT(*)', 'revision_count');
    foreach ($query->execute() as $record) {
      $this->revisionCounts[(int) $record->tid] = (int) $record->revision_count;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string {
    $tid = (int) $this->getValue($values);
    if (!$tid) {
      return '';
    }
    $revision_count = $this->revisionCounts[$tid] ?? 1;
    return $revision_count === 1
      ? (string) $this->t('New')
      : (string) $this->t('Updated');
  }

}
