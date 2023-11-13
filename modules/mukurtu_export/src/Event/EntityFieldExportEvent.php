<?php

namespace Drupal\mukurtu_export\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

/**
 * Event when an entity field is being exported.
 */
class EntityFieldExportEvent extends Event {
  const EVENT_NAME = 'mukurtu_export_entity_field_export';

  /**
   * The exporter plugin ID.
   *
   * @var string
   */
  public $exporter_id;

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  public $entity;

  /**
   * The field name.
   *
   * @var string
   */
  public $field_name;

  /**
   * The batch process context.
   *
   * @var mixed.
   */
  public $context;

  /**
   * The field value to export.
   *
   * @var mixed
   */
  protected $value;

  public function __construct($exporter_id, EntityInterface $entity, $field_name, &$context) {
    $this->exporter_id = $exporter_id;
    $this->entity = $entity;
    $this->field_name = $field_name;
    $this->value = [];
    $this->context = $context;
  }

  /**
   * Get the exported value.
   */
  public function getValue() {
    return $this->value;
  }

  /**
   * Set the exported value.
   */
  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  /**
   * Package a binary file for export.
   *
   * @param string $uri
   *   The URI of the file to package.
   *
   * @param string $entryname
   *   The name to use for the file in the ZIP archive.
   */
  public function packageFile($uri, $entryname) {
    $this->context['results']['deliverables']['files'][] = ['uri' => $uri, 'entryname' => $entryname];
  }

  /**
   * Add an entity for export.
   *
   * During export, it may be necessary to export entities beyond what the user
   * has selected (e.g., media, paragraphs).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to export.
   */
  public function exportAdditionalEntity(EntityInterface $entity) {
    $this->context['results']['entities'][$entity->getEntityTypeId()][$entity->id()] = $entity->id();
  }

}
