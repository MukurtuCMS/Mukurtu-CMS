<?php

namespace Drupal\twig_tweak;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\media\Source\OEmbedInterface;

/**
 * URI extractor service.
 */
class UriExtractor {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a UrlExtractor object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Returns a URI to the file.
   *
   * @param Object|null $input
   *   An object that contains the URI.
   *
   * @return string|null
   *   A URI that may be used to access the file.
   */
  public function extractUri(?object $input): ?string {
    $entity = $input;
    if ($input instanceof EntityReferenceFieldItemListInterface) {
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $item */
      if ($item = $input->first()) {
        $entity = $item->entity;
      }
    }
    elseif ($input instanceof EntityReferenceItem) {
      $entity = $input->entity;
    }
    // Drupal does not clean up references to deleted entities. So that the
    // entity property might be empty while the field item might not.
    // @see https://www.drupal.org/project/drupal/issues/2723323
    return $entity instanceof ContentEntityInterface ?
      $this->getUriFromEntity($entity) : NULL;
  }

  /**
   * Extracts file URI from content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity object that contains information about the file.
   *
   * @return string|null
   *   A URI that can be used to access the file.
   */
  private function getUriFromEntity(ContentEntityInterface $entity): ?string {
    if ($entity instanceof MediaInterface) {
      $source = $entity->getSource();
      $value = $source->getSourceFieldValue($entity);
      if ($source instanceof OEmbedInterface) {
        return $value;
      }
      /** @var \Drupal\file\FileInterface $file */
      $file = $this->entityTypeManager->getStorage('file')->load($value);
      if ($file) {
        return $file->getFileUri();
      }
    }
    elseif ($entity instanceof FileInterface) {
      return $entity->getFileUri();
    }
    return NULL;
  }

}
