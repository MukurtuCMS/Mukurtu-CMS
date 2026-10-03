<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays what the import just did to an item: New, Updated, or Unchanged.
 *
 * The outcome is recorded per row by ProtocolAwareEntityContent::import()
 * and saved to the import's private tempstore by
 * ImportBatchExecutable::batchFinishedImport(), keyed by entity type, ID,
 * and language. It reflects the import only, unlike core History's
 * "unread" marker, which depends on what the current user has viewed.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2309
 */
#[ViewsField("mukurtu_import_status")]
class ImportStatus extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The tempstore key holding the last import's per-entity outcomes.
   */
  public const TEMPSTORE_KEY = 'batch_results_row_outcomes';

  /**
   * The query alias of the langcode column, if the entity type has one.
   */
  protected string $langcodeAlias = '';

  /**
   * Outcomes keyed by entity type, ID, then langcode.
   */
  protected ?array $outcomes = NULL;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PrivateTempStoreFactory $tempStoreFactory,
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
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());
    $this->field_alias = $this->query->addField($this->tableAlias, $entity_type->getKey('id'));
    if ($langcode_key = $entity_type->getKey('langcode')) {
      $this->langcodeAlias = $this->query->addField($this->tableAlias, $langcode_key);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string {
    $id = $this->getValue($values);
    if ($id === NULL || $id === '') {
      return '';
    }

    $outcome = $this->getOutcome(
      $this->getEntityType(),
      (string) $id,
      $this->langcodeAlias ? (string) ($values->{$this->langcodeAlias} ?? '') : '',
    );

    return match ($outcome) {
      'new' => (string) $this->t('New'),
      'updated' => (string) $this->t('Updated'),
      'unchanged' => (string) $this->t('Unchanged'),
      default => '',
    };
  }

  /**
   * Looks up the recorded outcome for one row of the results table.
   *
   * A translation the import didn't touch can still be listed, since a
   * revision covers every translation of an entity. It's reported as
   * Unchanged. An entity with no recorded outcome at all, such as after the
   * tempstore has expired, gets no status rather than a guess.
   *
   * @return string|null
   *   'new', 'updated', 'unchanged', or NULL when nothing was recorded.
   */
  protected function getOutcome(string $entity_type_id, string $id, string $langcode): ?string {
    $this->outcomes ??= $this->tempStoreFactory->get('mukurtu_import')->get(self::TEMPSTORE_KEY) ?? [];
    $by_langcode = $this->outcomes[$entity_type_id][$id] ?? NULL;
    if (!$by_langcode) {
      return NULL;
    }
    return $by_langcode[$langcode] ?? 'unchanged';
  }

}
