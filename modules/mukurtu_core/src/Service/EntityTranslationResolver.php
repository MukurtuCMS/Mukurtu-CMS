<?php

namespace Drupal\mukurtu_core\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\TranslatableInterface;

/**
 * Resolves a loaded entity to its translation for the active content
 * language.
 *
 * A plain $storage->load($id) always returns an entity in its default/
 * original language, so code that then calls ->label()/->getName() shows
 * the wrong-language value whenever a visitor's active language differs
 * from the entity's original one. Use this instead of loading directly
 * whenever the loaded entity's label/name will be read for display.
 *
 * Callers that display the result in a render-cached context (a Views
 * filter's option list, a Facets result, an admin listing) must still add
 * the 'languages:language_content' cache context to their OWN cacheable
 * metadata - getTranslationFromContext() only adds it to the returned
 * entity object, which isn't automatically bubbled into an options array
 * or a facet result's display value.
 */
class EntityTranslationResolver {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * Returns the entity's translation for the active content language.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity, in any translation.
   * @param string|null $langcode
   *   A specific langcode to resolve to, or NULL for the active content
   *   language.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity's translation, or the entity itself if it isn't
   *   translatable.
   */
  public function translate(EntityInterface $entity, ?string $langcode = NULL): EntityInterface {
    if ($entity instanceof TranslatableInterface) {
      return $this->entityRepository->getTranslationFromContext($entity, $langcode);
    }
    return $entity;
  }

  /**
   * Loads an entity and returns its translation for the active content
   * language, in one call.
   *
   * @param string $entityTypeId
   *   The entity type ID, e.g. 'protocol'.
   * @param int|string $id
   *   The entity ID.
   * @param string|null $langcode
   *   A specific langcode to resolve to, or NULL for the active content
   *   language.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity's translation, or NULL if no such entity exists.
   */
  public function loadTranslated(string $entityTypeId, string|int $id, ?string $langcode = NULL): ?EntityInterface {
    $entity = $this->entityTypeManager->getStorage($entityTypeId)->load($id);
    return $entity ? $this->translate($entity, $langcode) : NULL;
  }

}
