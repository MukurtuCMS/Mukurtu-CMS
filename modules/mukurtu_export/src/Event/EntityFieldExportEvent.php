<?php

namespace Drupal\mukurtu_export\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\mukurtu_export\ExportItemIdentity;

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
   * The sub field name.
   *
   * @var string
   */
  public $sub_field_name;

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
    $fieldComponents = explode('/', $field_name);
    $this->field_name = $fieldComponents[0];
    $this->sub_field_name = $fieldComponents[1] ?? NULL;
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
   * The langcode of the row entity being exported, or NULL if it's being
   * exported in its own original language.
   *
   * Referenced-entity lookups (taxonomy terms, protocols, usernames, etc.)
   * should resolve to this same language so a translated export doesn't
   * mix in default-language names for the entities it references.
   */
  public function getLangcode(): ?string {
    if ($this->entity instanceof TranslatableInterface && !$this->entity->isDefaultTranslation()) {
      return $this->entity->language()->getId();
    }
    return NULL;
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
    $langcode = $entity instanceof TranslatableInterface && !$entity->isDefaultTranslation()
      ? $entity->language()->getId()
      : NULL;
    $key = ExportItemIdentity::encode($entity->id(), $langcode);
    $this->context['results']['entities'][$entity->getEntityTypeId()][$key] = $key;
  }

}
