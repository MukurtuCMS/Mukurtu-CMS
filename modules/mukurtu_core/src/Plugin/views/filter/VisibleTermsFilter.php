<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\views\filter;

use Drupal\Core\Cache\Cache;
use Drupal\mukurtu_core\Service\TermVisibility;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Limits a term view to the terms this visitor can see (#2643).
 *
 * @see \Drupal\mukurtu_core\Service\TermVisibility
 */
#[ViewsFilter('mukurtu_visible_terms')]
class VisibleTermsFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  protected $alwaysMultiple = TRUE;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly TermVisibility $termVisibility,
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
      $container->get('mukurtu_core.term_visibility'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['vocabulary'] = ['default' => 'category'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $tids = $this->termVisibility->getVisibleTermIds($this->options['vocabulary']);
    if (!$tids) {
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }
    $this->ensureMyTable();
    $this->query->addWhere($this->options['group'], "$this->tableAlias.tid", $tids, 'IN');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), $this->termVisibility->getCacheability($this->options['vocabulary'])->getCacheContexts());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), $this->termVisibility->getCacheability($this->options['vocabulary'])->getCacheTags());
  }

}
