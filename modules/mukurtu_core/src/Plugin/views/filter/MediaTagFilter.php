<?php

namespace Drupal\mukurtu_core\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters media entities by media tag taxonomy term.
 *
 * @ViewsFilter("mukurtu_media_tag_filter")
 */
class MediaTagFilter extends InOperator {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('database'),
    );
  }

  public function getValueOptions(): array {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'media_tag']);
    $options = [];
    foreach ($terms as $term) {
      $options[$term->id()] = $term->label();
    }
    asort($options);
    $this->valueOptions = $options;
    return $this->valueOptions;
  }

  public function query(): void {
    if (empty($this->value)) {
      return;
    }

    // Find media IDs that have any of the selected tags.
    $media_ids = $this->database->select('media__field_media_tags', 'mmt')
      ->fields('mmt', ['entity_id'])
      ->condition('mmt.field_media_tags_target_id', $this->value, 'IN')
      ->execute()
      ->fetchCol();

    if (empty($media_ids)) {
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }

    $this->ensureMyTable();
    $this->query->addWhere($this->options['group'], "$this->tableAlias.mid", $media_ids, 'IN');
  }

}
