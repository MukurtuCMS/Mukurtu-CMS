<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\views\field;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders one column of the bulk edit page from the row's entity.
 *
 * Most Mukurtu fields are defined in code and have no Views field handler,
 * so this renders any field by name instead. "protocols", "sharing" and
 * "community" are columns built from the cultural protocol field. Rows are
 * rendered in their own language.
 *
 * @see \Drupal\mukurtu_core\BulkEdit\BulkEditColumns
 */
#[ViewsField('mukurtu_bulk_edit_column')]
class BulkEditColumn extends FieldPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityRepositoryInterface $entityRepository,
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
      $container->get('entity.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['column'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->addAdditionalFields(['langcode' => 'langcode']);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);
    if (!$entity instanceof FieldableEntityInterface) {
      return '';
    }
    $langcode = $this->getValue($values, 'langcode');
    if ($langcode && $entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }

    $column = $this->options['column'];
    if ($entity instanceof CulturalProtocolControlledInterface) {
      $value = match ($column) {
        'protocols' => $this->labels($entity->getProtocolEntities()),
        'sharing' => $entity->getSharingSetting() === 'any' ? $this->t('Any') : $this->t('All'),
        'community' => $this->labels($entity->getCommunities()),
        default => NULL,
      };
      if ($value !== NULL) {
        return $this->isSynced($entity) ? $this->synced((string) $value, $column === 'protocols') : $value;
      }
    }

    if (!$entity->hasField($column) || !$entity->get($column)->access('view')) {
      return '';
    }
    $display = ['label' => 'hidden'];
    // The default moderation state formatter prints the machine name.
    if ($column === 'moderation_state') {
      $display['type'] = 'content_moderation_state';
    }
    // Media has no moderation states; say what the media list says.
    elseif ($column === 'status') {
      $display['type'] = 'boolean';
      $display['settings'] = [
        'format' => 'custom',
        'format_custom_true' => (string) $this->t('Published'),
        'format_custom_false' => (string) $this->t('Unpublished'),
      ];
    }
    return $entity->get($column)->view($display);
  }

  /**
   * Whether media takes its protocols from its parent item.
   *
   * Protocol sync overwrites such media's protocols whenever the parent is
   * saved, so the bulk protocol actions skip it.
   */
  protected function isSynced(FieldableEntityInterface $entity): bool {
    return $entity->hasField('field_sync_protocols') && (bool) $entity->get('field_sync_protocols')->value;
  }

  /**
   * Shows a synced value as one that can't be changed here.
   */
  protected function synced(string $value, bool $with_note): array {
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => ['class' => ['mukurtu-bulk-edit-synced']],
      'value' => ['#plain_text' => $value],
    ];
    if ($with_note) {
      $build['note'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['class' => ['mukurtu-bulk-edit-synced__note']],
        '#value' => $this->t('Synced from its parent item'),
      ];
    }
    return $build;
  }

  /**
   * Joins entity labels in the current content language.
   */
  protected function labels(array $entities): string {
    return implode(', ', array_map(
      fn($entity) => $this->entityRepository->getTranslationFromContext($entity)->label(),
      $entities,
    ));
  }

}
