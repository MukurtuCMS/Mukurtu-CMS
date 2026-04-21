<?php

namespace Drupal\entity_reference_revisions\Plugin\DataType;

use Drupal\Core\Entity\Plugin\DataType\Deriver\EntityDeriver;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\entity_reference_revisions\TypedData\EntityRevisionDataDefinition;

/**
 * Defines the "entity" data type.
 *
 * Instances of this class wrap entity objects and allow to deal with entities
 * based upon the Typed Data API.
 *
 * In addition to the "entity" data type, this exposes derived
 * "entity:$entity_type" and "entity:$entity_type:$bundle" data types.
 *
 * @DataType(
 *   id = "entity_revision",
 *   label = @Translation("Entity Revision"),
 *   description = @Translation("All kind of entities with revision information, e.g. nodes, comments or users."),
 *   deriver = "\Drupal\Core\Entity\Plugin\DataType\Deriver\EntityDeriver",
 *   definition_class = "\Drupal\entity_reference_revisions\TypedData\EntityRevisionDataDefinition"
 * )
 */
#[DataType(
  id: 'entity_revision',
  label: new TranslatableMarkup('Entity Revision'),
  description: new TranslatableMarkup('All kind of entities with revision information, e.g. nodes, comments or users.'),
  definition_class: EntityRevisionDataDefinition::class,
  deriver: EntityDeriver::class,
)]
class EntityRevisionsAdapter extends EntityAdapter implements \IteratorAggregate, ComplexDataInterface {

}
