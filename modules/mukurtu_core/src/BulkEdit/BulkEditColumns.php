<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\BulkEdit;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserDataInterface;

/**
 * Decides which columns a bulk edit page offers and shows.
 *
 * There's a page for content and one for media; each service instance
 * serves one of them. A page lists one type at a time. Besides the title,
 * it offers a few columns every type has (language, protocols, sharing,
 * community, status, updated) plus the fields of the type's "bulk_edit" form
 * mode, in alphabetical order, so the table shows what bulk editing can
 * change.
 * Before a type has that form mode, the fields of its regular edit form
 * stand in. Each editor's choice is saved per type in user data.
 */
class BulkEditColumns {

  use StringTranslationTrait;

  /**
   * The view that renders the bulk edit page.
   */
  public const VIEW_ID = 'mukurtu_bulk_edit_content';

  /**
   * The view that renders the bulk edit media page.
   */
  public const MEDIA_VIEW_ID = 'mukurtu_bulk_edit_media';

  /**
   * Content types with a bulk edit page, in tab order.
   */
  public const TYPES = ['digital_heritage', 'dictionary_word', 'word_list', 'collection', 'person', 'place'];

  /**
   * Columns that aren't fields of the type, shown under "Item details".
   */
  public const ITEM_COLUMNS = ['langcode', 'protocols', 'sharing', 'community', 'moderation_state', 'status', 'changed'];

  /**
   * Fields offered when a type has no form display at all.
   */
  protected const FALLBACK_FIELDS = ['field_keywords', 'field_category'];

  /**
   * Columns shown until an editor chooses their own.
   */
  protected const DEFAULT_COLUMNS = ['langcode', 'protocols', 'sharing', 'moderation_state', 'status', 'changed'];

  /**
   * Fields that have their own column or aren't shown as one.
   */
  protected const NOT_FIELD_COLUMNS = [
    'title',
    'name',
    'langcode',
    'field_cultural_protocols',
    'moderation_state',
    'changed',
    'revision_log',
    'revision_log_message',
    // Settings rather than content, or duplicated by Status.
    'path',
    'promote',
    'sticky',
    'status',
  ];

  /**
   * Constructs the service for one bulk edit page.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\user\UserDataInterface $userData
   *   Where each editor's column choice is saved.
   * @param string $entityTypeId
   *   The entity type the page lists.
   * @param string $viewId
   *   The view that renders the page.
   * @param string[] $typeIds
   *   The bundles with a page, in tab order.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
    protected readonly UserDataInterface $userData,
    protected readonly string $entityTypeId = 'node',
    protected readonly string $viewId = self::VIEW_ID,
    protected readonly array $typeIds = self::TYPES,
  ) {}

  /**
   * Returns the entity type the page lists.
   */
  public function entityTypeId(): string {
    return $this->entityTypeId;
  }

  /**
   * Returns the ID of the view that renders the page.
   */
  public function viewId(): string {
    return $this->viewId;
  }

  /**
   * Returns the route of the page for one type, which takes it as arg_0.
   */
  public function route(): string {
    return "view.{$this->viewId}.page";
  }

  /**
   * Returns the types with a bulk edit page, keyed by ID.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface[]
   *   Existing bundles, in tab order.
   */
  public function types(): array {
    $bundle_type = $this->entityTypeManager->getDefinition($this->entityTypeId)->getBundleEntityType();
    $types = $this->entityTypeManager->getStorage($bundle_type)->loadMultiple($this->typeIds);
    return array_replace(array_intersect_key(array_flip($this->typeIds), $types), $types);
  }

  /**
   * Returns the columns a type offers, keyed by column ID, in display order.
   *
   * @return array<string, string|\Stringable>
   *   Column labels.
   */
  public function available(string $type): array {
    $definitions = $this->entityFieldManager->getFieldDefinitions($this->entityTypeId, $type);

    $columns = ['langcode' => $this->t('Translation language')];
    if (isset($definitions['field_cultural_protocols'])) {
      $columns['protocols'] = $this->t('Cultural Protocols');
      $columns['sharing'] = $this->t('Sharing Setting');
      $columns['community'] = $this->t('Community');
    }

    $fields = [];
    foreach ($this->fieldNames($type) as $name) {
      if (isset($definitions[$name]) && !in_array($name, self::NOT_FIELD_COLUMNS, TRUE) && $definitions[$name]->getType() !== 'comment') {
        $fields[$name] = $definitions[$name]->getLabel();
      }
    }
    // Alphabetical, so a long list is quick to scan.
    uasort($fields, fn($a, $b) => strnatcasecmp((string) $a, (string) $b));
    $columns += $fields;

    if (isset($definitions['moderation_state'])) {
      $columns['moderation_state'] = $this->t('Status');
    }
    // Media has no moderation states; it's published or not.
    elseif ($this->entityTypeId === 'media' && isset($definitions['status'])) {
      $columns['status'] = $this->t('Status');
    }
    $columns['changed'] = $this->t('Updated');

    return $columns;
  }

  /**
   * Returns the column IDs to show for a type, in display order.
   */
  public function selected(string $type, AccountInterface $account): array {
    $available = $this->available($type);
    $saved = $this->userData->get('mukurtu_core', (int) $account->id(), $this->key($type));
    $chosen = is_array($saved) ? $saved : self::DEFAULT_COLUMNS;
    return array_values(array_intersect(array_keys($available), $chosen));
  }

  /**
   * Saves an editor's column choice for a type.
   *
   * @param string $type
   *   The content type.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The editor.
   * @param string[] $columns
   *   Column IDs. Unknown ones are dropped.
   */
  public function save(string $type, AccountInterface $account, array $columns): void {
    $valid = array_values(array_intersect(array_keys($this->available($type)), $columns));
    $this->userData->set('mukurtu_core', (int) $account->id(), $this->key($type), $valid);
  }

  /**
   * Returns the field names of a type's bulk_edit form, or its edit form.
   */
  protected function fieldNames(string $type): array {
    $storage = $this->entityTypeManager->getStorage('entity_form_display');
    foreach (['bulk_edit', 'default'] as $mode) {
      $display = $storage->load("{$this->entityTypeId}.$type.$mode");
      if ($display && $display->status()) {
        $components = $display->getComponents();
        uasort($components, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
        return array_keys($components);
      }
    }
    return self::FALLBACK_FIELDS;
  }

  /**
   * Returns the user data key for a type's column choice.
   */
  protected function key(string $type): string {
    // Content keys predate the media page.
    return $this->entityTypeId === 'node' ? "bulk_edit_columns.$type" : "bulk_edit_columns.{$this->entityTypeId}.$type";
  }

}
