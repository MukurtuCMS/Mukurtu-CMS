<?php

namespace Drupal\mukurtu_core\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\mukurtu_core\Service\EntityTranslationResolver;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters content nodes by protocol.
 *
 * @ViewsFilter("mukurtu_node_protocol_filter")
 */
class NodeProtocolFilter extends InOperator {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityTranslationResolver $entityTranslationResolver,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('mukurtu_core.entity_translation_resolver'),
    );
  }

  public function getValueOptions(): array {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }
    $protocols = $this->entityTypeManager->getStorage('protocol')->loadMultiple();
    $options = [];
    foreach ($protocols as $protocol) {
      $protocol = $this->entityTranslationResolver->translate($protocol);
      $options[$protocol->id()] = $protocol->label();
    }
    asort($options);
    $this->valueOptions = $options;
    return $this->valueOptions;
  }

  public function query(): void {
    if (empty($this->value)) {
      return;
    }

    // Match nodes whose protocols column contains any selected protocol ID.
    // The column stores pipe-delimited IDs like |1| |3|.
    $this->ensureMyTable();
    $or_group = $this->query->setWhereGroup('OR');
    foreach ($this->value as $pid) {
      $this->query->addWhere($or_group, "$this->tableAlias.field_cultural_protocols__protocols", '%|' . $pid . '|%', 'LIKE');
    }
  }

  /**
   * {@inheritdoc}
   *
   * The exposed filter's option labels now vary on the active content
   * language (see getValueOptions()), so the cached render output must vary
   * on it too.
   */
  public function getCacheContexts() {
    return array_merge(parent::getCacheContexts(), ['languages:' . LanguageInterface::TYPE_CONTENT]);
  }

}
