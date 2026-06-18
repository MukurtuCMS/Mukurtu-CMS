<?php

namespace Drupal\mukurtu_core\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters media entities by protocol.
 *
 * @ViewsFilter("mukurtu_media_protocol_filter")
 */
class MediaProtocolFilter extends InOperator {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  public function getValueOptions(): array {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }
    $protocols = $this->entityTypeManager->getStorage('protocol')->loadMultiple();
    $options = [];
    foreach ($protocols as $protocol) {
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

    // Match media whose protocols column contains any selected protocol ID.
    // The column stores pipe-delimited IDs like |1| |3|.
    $this->ensureMyTable();
    $or_group = $this->query->setWhereGroup('OR');
    foreach ($this->value as $pid) {
      $this->query->addWhere($or_group, "$this->tableAlias.field_cultural_protocols__protocols", '%|' . $pid . '|%', 'LIKE');
    }
  }

}
