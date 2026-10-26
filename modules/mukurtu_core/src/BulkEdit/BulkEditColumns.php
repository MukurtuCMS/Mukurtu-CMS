<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\BulkEdit;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserDataInterface;

/**
 * Decides which columns the bulk edit page offers and shows.
 *
 * The page lists one content type at a time. Besides the title, it offers
 * a few columns every type has (language, protocols, sharing, community,
 * status, updated) plus the fields of the type's "bulk_edit" form mode, so
 * the table shows what bulk editing can change. Before a type has that form
 * mode, keywords and category stand in. Each editor's choice is saved per
 * type in user data.
 */
class BulkEditColumns {

  use StringTranslationTrait;

  /**
   * The view that renders the bulk edit page.
   */
  public const VIEW_ID = 'mukurtu_bulk_edit_content';

  /**
   * Content types with a bulk edit page, in tab order.
   */
  public const TYPES = ['digital_heritage', 'dictionary_word', 'word_list', 'collection', 'person', 'place'];

  /**
   * Columns that aren't fields of the type, shown under "Item details".
   */
  public const ITEM_COLUMNS = ['langcode', 'protocols', 'sharing', 'community', 'moderation_state', 'changed'];

  /**
   * Fields offered when a type has no bulk_edit form mode yet.
   */
  protected const FALLBACK_FIELDS = ['field_keywords', 'field_category'];

  /**
   * Columns shown until an editor chooses their own.
   */
  protected const DEFAULT_COLUMNS = ['langcode', 'protocols', 'sharing', 'moderation_state', 'changed'];

  /**
   * Fields that have their own column or aren't shown as one.
   */
  protected const NOT_FIELD_COLUMNS = [
    'title',
    'langcode',
    'field_cultural_protocols',
    'moderation_state',
    'changed',
    'revision_log',
  ];

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
    protected readonly UserDataInterface $userData,
  ) {}

  /**
   * Returns the content types with a bulk edit page, keyed by ID.
   *
   * @return \Drupal\node\NodeTypeInterface[]
   *   Existing types from TYPES, in tab order.
   */
  public function types(): array {
    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple(self::TYPES);
    return array_replace(array_intersect_key(array_flip(self::TYPES), $types), $types);
  }

  /**
   * Returns the columns a type offers, keyed by column ID, in display order.
   *
   * @return array<string, string|\Stringable>
   *   Column labels.
   */
  public function available(string $type): array {
    $definitions = $this->entityFieldManager->getFieldDefinitions('node', $type);

    $columns = ['langcode' => $this->t('Translation language')];
    if (isset($definitions['field_cultural_protocols'])) {
      $columns['protocols'] = $this->t('Cultural Protocols');
      $columns['sharing'] = $this->t('Sharing Setting');
      $columns['community'] = $this->t('Community');
    }

    foreach ($this->fieldNames($type) as $name) {
      if (isset($definitions[$name]) && !in_array($name, self::NOT_FIELD_COLUMNS, TRUE)) {
        $columns[$name] = $definitions[$name]->getLabel();
      }
    }

    if (isset($definitions['moderation_state'])) {
      $columns['moderation_state'] = $this->t('Status');
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
   * Returns the field names in a type's bulk_edit form mode, or the fallback.
   */
  protected function fieldNames(string $type): array {
    $display = $this->entityTypeManager->getStorage('entity_form_display')->load("node.$type.bulk_edit");
    if ($display && $display->status()) {
      $components = $display->getComponents();
      uasort($components, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
      return array_keys($components);
    }
    return self::FALLBACK_FIELDS;
  }

  /**
   * Returns the user data key for a type's column choice.
   */
  protected function key(string $type): string {
    return "bulk_edit_columns.$type";
  }

}
