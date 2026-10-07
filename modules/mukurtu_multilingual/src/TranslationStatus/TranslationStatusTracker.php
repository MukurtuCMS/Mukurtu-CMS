<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multilingual\TranslationStatus;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Works out and stores how completely each translation has been translated.
 *
 * For every tracked entity there is one row per configurable language other
 * than the entity's own, in the mukurtu_translation_status table. A field
 * counts towards the total when it is translatable and has a value in the
 * original, and counts as translated when the translation's value is
 * non-empty and differs from the original. Content translation copies every
 * value into a new translation, so "identical to the original" is the usual
 * sign that a field hasn't been touched.
 *
 * Paragraphs are translated per paragraph rather than through the host's
 * reference field, so their translatable fields are counted as part of the
 * host.
 */
class TranslationStatusTracker {

  /**
   * The status table.
   */
  public const TABLE = 'mukurtu_translation_status';

  /**
   * The queue used for full rebuilds.
   */
  public const QUEUE = 'mukurtu_multilingual_translation_status';

  /**
   * State key flagging that a full rebuild should be queued on next cron.
   */
  public const REBUILD_STATE = 'mukurtu_multilingual.translation_status_rebuild_requested';

  /**
   * No translation exists, or nothing in it differs from the original.
   */
  public const STATUS_NONE = 0;

  /**
   * Some, but not all, fields are translated.
   */
  public const STATUS_PARTIAL = 1;

  /**
   * Every field with a value in the original is translated.
   */
  public const STATUS_COMPLETE = 2;

  /**
   * Entity types the translation status pages cover.
   */
  public const ENTITY_TYPES = ['node', 'media', 'taxonomy_term', 'community', 'protocol'];

  /**
   * Fields that never count, even when translatable.
   *
   * These are metadata, not content someone translates.
   */
  protected const SKIPPED_FIELDS = [
    'changed',
    'created',
    'uid',
    'status',
    'path',
    'moderation_state',
    'metatag',
    'parent_id',
    'parent_type',
    'parent_field_name',
    'behavior_settings',
    'promote',
    'sticky',
    // Media generates its thumbnail; nobody translates it.
    'thumbnail',
  ];

  /**
   * Field types that never count: flags, dates and bookkeeping values.
   */
  protected const SKIPPED_TYPES = ['boolean', 'created', 'changed', 'timestamp', 'language', 'uuid'];

  /**
   * Field-name prefixes that never count.
   */
  protected const SKIPPED_PREFIXES = ['revision_', 'content_translation_'];

  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected ContentTranslationManagerInterface $contentTranslationManager,
    protected QueueFactory $queueFactory,
    protected EntityTypeBundleInfoInterface $bundleInfo,
    protected StateInterface $state,
  ) {}

  /**
   * Returns the human-readable label for each status.
   *
   * @return array<int, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Labels keyed by status constant.
   */
  public static function statusLabels(): array {
    return [
      self::STATUS_COMPLETE => new TranslatableMarkup('Translated'),
      self::STATUS_PARTIAL => new TranslatableMarkup('Partly translated'),
      self::STATUS_NONE => new TranslatableMarkup('Not translated'),
    ];
  }

  /**
   * Whether the status pages track this entity.
   */
  public function isTracked(ContentEntityInterface $entity): bool {
    return in_array($entity->getEntityTypeId(), self::ENTITY_TYPES, TRUE)
      && $entity->isTranslatable()
      && !$entity->getUntranslated()->language()->isLocked()
      && $this->contentTranslationManager->isEnabled($entity->getEntityTypeId(), $entity->bundle());
  }

  /**
   * Recalculates and stores every language's status for one entity.
   *
   * Uses the latest revision, so a translation saved as a draft counts
   * straight away rather than only once it is published.
   */
  public function refresh(ContentEntityInterface $entity): void {
    $entity = $this->latestRevision($entity)->getUntranslated();
    $this->delete($entity->getEntityTypeId(), (int) $entity->id());
    if (!$this->isTracked($entity)) {
      return;
    }

    $original_langcode = $entity->language()->getId();
    $insert = $this->database->insert(self::TABLE)
      ->fields(['entity_type', 'entity_id', 'langcode', 'status', 'translated_count', 'total_count']);
    foreach (array_keys($this->languageManager->getLanguages()) as $langcode) {
      if ($langcode === $original_langcode) {
        continue;
      }
      $counts = $this->calculate($entity, $langcode);
      $insert->values([
        'entity_type' => $entity->getEntityTypeId(),
        'entity_id' => (int) $entity->id(),
        'langcode' => $langcode,
        'status' => $this->status($counts['translated'], $counts['total'], $entity->hasTranslation($langcode)),
        'translated_count' => $counts['translated'],
        'total_count' => $counts['total'],
      ]);
    }
    $insert->execute();
  }

  /**
   * Removes every stored row for an entity.
   */
  public function delete(string $entity_type_id, int $entity_id): void {
    $this->database->delete(self::TABLE)
      ->condition('entity_type', $entity_type_id)
      ->condition('entity_id', $entity_id)
      ->execute();
  }

  /**
   * Counts translatable and translated fields for one target language.
   *
   * @return array{translated: int, total: int}
   *   The number of fields translated, and the number that need it.
   */
  public function calculate(ContentEntityInterface $original, string $langcode): array {
    $translation = $original->hasTranslation($langcode) ? $original->getTranslation($langcode) : NULL;
    $counts = ['translated' => 0, 'total' => 0];

    foreach ($original->getFieldDefinitions() as $name => $definition) {
      if ($this->isParagraphReference($definition)) {
        foreach ($original->get($name)->referencedEntities() as $paragraph) {
          if ($paragraph instanceof ContentEntityInterface && $paragraph->isTranslatable()) {
            $nested = $this->calculate($paragraph->getUntranslated(), $langcode);
            $counts['translated'] += $nested['translated'];
            $counts['total'] += $nested['total'];
          }
        }
        continue;
      }
      if (!$this->counts($definition) || $original->get($name)->isEmpty()) {
        continue;
      }
      $counts['total']++;
      if ($translation && !$translation->get($name)->isEmpty() && !$translation->get($name)->equals($original->get($name))) {
        $counts['translated']++;
      }
    }

    return $counts;
  }

  /**
   * Asks for a full rebuild on the next cron run.
   *
   * Used when a language is added or removed, or translation settings
   * change, since any of those can change every row. Only a flag is set
   * here: installing the module alone saves dozens of translation settings,
   * and queueing every entity for each one would flood the queue.
   */
  public function requestRebuild(): void {
    $this->state->set(self::REBUILD_STATE, TRUE);
  }

  /**
   * Queues a full rebuild if one was requested.
   *
   * @return int
   *   The number of entities queued.
   */
  public function queueRequestedRebuild(): int {
    if (!$this->state->get(self::REBUILD_STATE)) {
      return 0;
    }
    $this->state->delete(self::REBUILD_STATE);
    return $this->queueRebuild();
  }

  /**
   * Queues every tracked entity for a status rebuild on cron.
   *
   * @return int
   *   The number of entities queued.
   */
  public function queueRebuild(): int {
    $queue = $this->queueFactory->get(self::QUEUE);
    $total = 0;
    foreach ($this->trackedEntityIds() as $entity_type_id => $ids) {
      foreach (array_chunk($ids, 50) as $chunk) {
        $queue->createItem(['entity_type' => $entity_type_id, 'ids' => $chunk]);
      }
      $total += count($ids);
    }
    return $total;
  }

  /**
   * Refreshes a batch of entities of one type.
   *
   * @param string $entity_type_id
   *   The entity type.
   * @param int[] $ids
   *   The entity IDs. Missing entities have their rows removed.
   */
  public function refreshMultiple(string $entity_type_id, array $ids): void {
    $entities = $this->entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids);
    foreach ($ids as $id) {
      if (isset($entities[$id]) && $entities[$id] instanceof ContentEntityInterface) {
        $this->refresh($entities[$id]);
      }
      else {
        $this->delete($entity_type_id, (int) $id);
      }
    }
  }

  /**
   * Lists the IDs of every entity in a tracked type and translatable bundle.
   *
   * @return array<string, int[]>
   *   Entity IDs keyed by entity type.
   */
  public function trackedEntityIds(): array {
    $ids = [];
    foreach (self::ENTITY_TYPES as $entity_type_id) {
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        continue;
      }
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $query = $this->entityTypeManager->getStorage($entity_type_id)->getQuery()->accessCheck(FALSE);
      if ($bundle_key = $entity_type->getKey('bundle')) {
        $bundles = array_filter(
          array_keys($this->bundleInfo->getBundleInfo($entity_type_id)),
          fn ($bundle) => $this->contentTranslationManager->isEnabled($entity_type_id, $bundle),
        );
        if (!$bundles) {
          continue;
        }
        $query->condition($bundle_key, $bundles, 'IN');
      }
      elseif (!$this->contentTranslationManager->isEnabled($entity_type_id, $entity_type_id)) {
        continue;
      }
      $ids[$entity_type_id] = array_map('intval', array_values($query->execute()));
    }
    return $ids;
  }

  /**
   * Turns field counts into a status.
   */
  public function status(int $translated, int $total, bool $translation_exists): int {
    if (!$translation_exists || ($translated === 0 && $total > 0)) {
      return self::STATUS_NONE;
    }
    return $translated >= $total ? self::STATUS_COMPLETE : self::STATUS_PARTIAL;
  }

  /**
   * Whether a field counts towards translation progress.
   */
  protected function counts(FieldDefinitionInterface $definition): bool {
    $name = $definition->getName();
    if (!$definition->isTranslatable() || $definition->isComputed() || $definition->isReadOnly()) {
      return FALSE;
    }
    if (in_array($name, self::SKIPPED_FIELDS, TRUE) || in_array($definition->getType(), self::SKIPPED_TYPES, TRUE)) {
      return FALSE;
    }
    foreach (self::SKIPPED_PREFIXES as $prefix) {
      if (str_starts_with($name, $prefix)) {
        return FALSE;
      }
    }
    // Entity keys (ID, UUID, bundle, langcode and so on) are bookkeeping.
    $entity_type = $this->entityTypeManager->getDefinition($definition->getTargetEntityTypeId());
    return !in_array($name, array_filter($entity_type->getKeys()), TRUE) || $name === $entity_type->getKey('label');
  }

  /**
   * Whether a field references paragraphs, which carry their own translations.
   */
  protected function isParagraphReference(FieldDefinitionInterface $definition): bool {
    return $definition->getType() === 'entity_reference_revisions'
      && $definition->getSetting('target_type') === 'paragraph';
  }

  /**
   * Loads the latest revision of an entity, if its storage has revisions.
   */
  protected function latestRevision(ContentEntityInterface $entity): ContentEntityInterface {
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    if ($entity->isNew() || !$storage instanceof RevisionableStorageInterface || !$entity->getEntityType()->isRevisionable()) {
      return $entity;
    }
    $latest_id = $storage->getLatestRevisionId($entity->id());
    if ($latest_id && $latest_id != $entity->getRevisionId()) {
      $latest = $storage->loadRevision($latest_id);
      if ($latest instanceof ContentEntityInterface) {
        return $latest;
      }
    }
    return $entity;
  }

}
