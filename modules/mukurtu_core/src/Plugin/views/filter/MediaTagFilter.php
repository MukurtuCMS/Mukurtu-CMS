<?php

namespace Drupal\mukurtu_core\Plugin\views\filter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mukurtu_core\Service\TermPathOptions;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters media entities by media tag taxonomy term.
 *
 * Choosing a term includes media tagged with the terms nested under it (#2524).
 */
#[ViewsFilter('mukurtu_media_tag_filter')]
class MediaTagFilter extends InOperator {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly Connection $database,
    protected readonly TermPathOptions $termPathOptions,
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
      $container->get('mukurtu_core.term_path_options'),
    );
  }

  public function getValueOptions(): array {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }
    $this->valueOptions = $this->termPathOptions->getOptions('media_tag');
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['languages:language_content']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['taxonomy_term_list:media_tag']);
  }

  public function query(): void {
    if (empty($this->value)) {
      return;
    }

    // Include the terms nested under the selected tags.
    $tids = (array) $this->value;
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    foreach ((array) $this->value as $tid) {
      foreach ($storage->loadTree('media_tag', (int) $tid) as $term) {
        $tids[] = $term->tid;
      }
    }

    // Find media IDs that have any of the selected tags.
    $media_ids = $this->database->select('media__field_media_tags', 'mmt')
      ->fields('mmt', ['entity_id'])
      ->condition('mmt.field_media_tags_target_id', $tids, 'IN')
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
