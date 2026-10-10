<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multilingual\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mukurtu_multilingual\TranslationStatus\TranslationStatusTracker;

/**
 * Keeps the stored translation status current as content changes.
 *
 * Saving any translation recalculates every language for that entity,
 * since editing the original can make a translated field match it again.
 * Adding or removing a language, or changing which bundles are
 * translatable, can change every row, so those request a full rebuild that
 * cron queues and works through.
 *
 * @see \Drupal\mukurtu_multilingual\TranslationStatus\TranslationStatusTracker
 */
class TranslationStatusHooks {

  public function __construct(protected TranslationStatusTracker $tracker) {}

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    $this->refresh($entity);
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $this->refresh($entity);
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if (in_array($entity->getEntityTypeId(), TranslationStatusTracker::ENTITY_TYPES, TRUE)) {
      $this->tracker->delete($entity->getEntityTypeId(), (int) $entity->id());
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for configurable_language.
   */
  #[Hook('configurable_language_insert')]
  public function languageInsert(EntityInterface $language): void {
    $this->tracker->requestRebuild();
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for configurable_language.
   */
  #[Hook('configurable_language_delete')]
  public function languageDelete(EntityInterface $language): void {
    $this->tracker->requestRebuild();
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for language_content_settings.
   */
  #[Hook('language_content_settings_insert')]
  public function contentSettingsInsert(EntityInterface $settings): void {
    $this->tracker->requestRebuild();
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for language_content_settings.
   */
  #[Hook('language_content_settings_update')]
  public function contentSettingsUpdate(EntityInterface $settings): void {
    $this->tracker->requestRebuild();
  }

  /**
   * Implements hook_cron().
   *
   * Runs before the queue worker in the same cron run, so a requested
   * rebuild starts straight away.
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->tracker->queueRequestedRebuild();
  }

  /**
   * Recalculates a saved entity if it's one the status pages cover.
   */
  protected function refresh(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface && in_array($entity->getEntityTypeId(), TranslationStatusTracker::ENTITY_TYPES, TRUE)) {
      $this->tracker->refresh($entity);
    }
  }

}
