<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Decides which taxonomy terms a visitor can see (#2643, #2785).
 *
 * A term's name can be sensitive, such as a person's name or a restricted
 * word, so the name is only shown to someone who can see something using it.
 * A term shows if the visitor can see a content item, a media item, or a
 * paragraph on a content item that uses it, or that uses any term nested
 * under it, because many parents have no content of their own.
 *
 * People who can create content that uses a vocabulary see all of its terms.
 * The term field's suggestions already offer them every term as they type,
 * and a new term on their own draft must not show as restricted.
 */
class TermVisibility {

  /**
   * Vocabularies that aren't used on content items, so have no public pages.
   */
  const NON_CONTENT_VOCABULARIES = [
    'community_type',
    'interpersonal_relationship',
    'media_tag',
  ];

  /**
   * The most levels of nested paragraphs followed up to a content item.
   */
  const MAX_PARAGRAPH_DEPTH = 10;

  /**
   * The most IDs put in one IN condition.
   *
   * Each ID is its own placeholder, and MySQL allows 65,535 per statement.
   */
  const CHUNK_SIZE = 1000;

  /**
   * Visible term IDs, keyed by vocabulary ID and then user ID.
   */
  protected array $visible = [];

  /**
   * Term reference fields, keyed by entity type ID and then vocabulary ID.
   */
  protected array $termFields = [];

  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected FieldTypePluginManagerInterface $fieldTypeManager,
    protected array $cacheContexts = [],
  ) {}

  /**
   * Whether a vocabulary's terms can appear on public pages.
   */
  public function isPublicVocabulary(string $vid): bool {
    return !in_array($vid, static::NON_CONTENT_VOCABULARIES, TRUE);
  }

  /**
   * Whether someone can see a term.
   *
   * People who can edit a term always can, so they can reach what they manage.
   */
  public function isVisible(TermInterface $term, ?AccountInterface $account = NULL): bool {
    $account ??= $this->currentUser;
    return $term->access('update', $account)
      || in_array((int) $term->id(), $this->getVisibleTermIds($term->bundle(), $account), TRUE);
  }

  /**
   * Gets the IDs of the terms in a vocabulary that someone can see.
   *
   * @return int[]
   *   Term IDs.
   */
  public function getVisibleTermIds(string $vid, ?AccountInterface $account = NULL): array {
    $account ??= $this->currentUser;
    $uid = (int) $account->id();
    if (isset($this->visible[$vid][$uid])) {
      return $this->visible[$vid][$uid];
    }
    if (!$this->isPublicVocabulary($vid)) {
      return $this->visible[$vid][$uid] = [];
    }

    $parents = $this->getParents($vid);
    if ($this->canTag($vid, $account)) {
      return $this->visible[$vid][$uid] = array_keys($parents);
    }

    $visible = $this->withAncestors($this->getUsedTermIds($vid, $account), $parents);

    $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($vid);
    if ($vocabulary?->getThirdPartySetting('mukurtu_taxonomy', 'show_unused_terms')) {
      $used = $this->withAncestors($this->getUsedTermIds($vid), $parents);
      $visible = array_merge($visible, array_diff(array_keys($parents), $used));
    }

    return $this->visible[$vid][$uid] = array_values(array_unique($visible));
  }

  /**
   * Gets the cacheability of anything that depends on this check.
   */
  public function getCacheability(string $vid): CacheableMetadata {
    $contexts = ['user.node_grants:view', 'user.permissions'];
    // Media access and the right to create content follow the person's
    // protocol and community memberships.
    if (in_array('mukurtu_memberships', $this->cacheContexts, TRUE)) {
      $contexts[] = 'mukurtu_memberships';
    }
    return (new CacheableMetadata())
      ->addCacheContexts($contexts)
      ->addCacheTags([
        'node_list',
        'media_list',
        'paragraph_list',
        "taxonomy_term_list:$vid",
        "config:taxonomy.vocabulary.$vid",
      ]);
  }

  /**
   * Gets the terms in a vocabulary used on published items.
   *
   * @param string $vid
   *   The vocabulary ID.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   Only count items this account can view, or NULL to count all of them.
   *
   * @return int[]
   *   Term IDs.
   */
  protected function getUsedTermIds(string $vid, ?AccountInterface $account = NULL): array {
    return array_values(array_unique(array_merge(
      $this->getTermIdsOnContent($vid, $account),
      $this->getTermIdsOnMedia($vid, $account),
      $this->getTermIdsOnParagraphs($vid, $account),
    )));
  }

  /**
   * Gets the terms in a vocabulary used on published content items.
   *
   * @return int[]
   *   Term IDs.
   */
  protected function getTermIdsOnContent(string $vid, ?AccountInterface $account = NULL): array {
    $query = $this->database->select('taxonomy_index', 'ti');
    $query->join('taxonomy_term_data', 'td', 'td.tid = ti.tid');
    $query->condition('td.vid', $vid);
    $query->addField('ti', 'tid');
    $query->distinct();
    if ($account) {
      $query->join('node_field_data', 'nfd', 'nfd.nid = ti.nid AND nfd.default_langcode = 1');
      $query->condition('nfd.status', 1);
      $this->addNodeAccess($query, $account);
    }
    return array_map('intval', $query->execute()->fetchCol());
  }

  /**
   * Gets the terms in a vocabulary used on published media.
   *
   * Core's taxonomy_index only records content items, so each media term
   * field's own table is read instead.
   *
   * @return int[]
   *   Term IDs.
   */
  protected function getTermIdsOnMedia(string $vid, ?AccountInterface $account = NULL): array {
    if (!$this->entityTypeManager->hasDefinition('media')) {
      return [];
    }
    $tids = [];
    foreach ($this->getTermFields('media', $vid) as [$table, $column]) {
      $query = $this->database->select($table, 'f');
      $query->join('taxonomy_term_data', 'td', "td.tid = f.$column");
      $query->join('media_field_data', 'mfd', 'mfd.mid = f.entity_id AND mfd.default_langcode = 1');
      $query->condition('td.vid', $vid);
      $query->condition('f.deleted', 0);
      $query->condition('mfd.status', 1);
      $query->addField('f', $column, 'tid');
      $query->distinct();
      if ($account) {
        // Filtered by protocol in mukurtu_protocol_query_media_access_alter().
        $query->addTag('media_access');
        $query->addMetaData('account', $account);
      }
      $tids = array_merge($tids, $query->execute()->fetchCol());
    }
    return array_map('intval', $tids);
  }

  /**
   * Gets the terms in a vocabulary used on paragraphs of published content.
   *
   * A paragraph counts if it is still part of a content item, through any
   * number of nested paragraphs, and that content item is published and
   * viewable. Paragraphs left behind by older revisions don't count.
   *
   * @return int[]
   *   Term IDs.
   */
  protected function getTermIdsOnParagraphs(string $vid, ?AccountInterface $account = NULL): array {
    if (!$this->entityTypeManager->hasDefinition('paragraph')) {
      return [];
    }

    // Term IDs keyed by paragraph ID.
    $terms = [];
    foreach ($this->getTermFields('paragraph', $vid) as [$table, $column]) {
      $query = $this->database->select($table, 'f');
      $query->join('taxonomy_term_data', 'td', "td.tid = f.$column");
      $query->join('paragraphs_item_field_data', 'p', 'p.id = f.entity_id AND p.default_langcode = 1');
      $query->condition('td.vid', $vid);
      $query->condition('f.deleted', 0);
      $query->condition('p.status', 1);
      $query->addField('f', 'entity_id', 'pid');
      $query->addField('f', $column, 'tid');
      foreach ($query->execute() as $row) {
        $terms[(int) $row->pid][(int) $row->tid] = TRUE;
      }
    }
    if (!$terms) {
      return [];
    }

    // Walk each paragraph up to the content item it's on.
    $hosts = $this->getParagraphHosts(array_keys($terms));
    if (!$hosts) {
      return [];
    }

    $viewable = [];
    foreach (array_chunk(array_unique($hosts), static::CHUNK_SIZE) as $nids) {
      $query = $this->database->select('node_field_data', 'nfd');
      $query->addField('nfd', 'nid');
      $query->condition('nfd.nid', $nids, 'IN');
      $query->condition('nfd.default_langcode', 1);
      $query->condition('nfd.status', 1);
      if ($account) {
        $this->addNodeAccess($query, $account);
      }
      $viewable += array_fill_keys(array_map('intval', $query->execute()->fetchCol()), TRUE);
    }

    $tids = [];
    foreach ($hosts as $pid => $nid) {
      if (isset($viewable[$nid])) {
        $tids += $terms[$pid];
      }
    }
    return array_keys($tids);
  }

  /**
   * Finds the content item each paragraph is part of.
   *
   * @param int[] $pids
   *   Paragraph IDs.
   *
   * @return int[]
   *   Content item IDs keyed by paragraph ID. Paragraphs that aren't part of
   *   a content item's current revision are left out.
   */
  protected function getParagraphHosts(array $pids): array {
    // The paragraph each starting paragraph has reached, keyed by the
    // starting paragraph's ID.
    $current = array_combine($pids, $pids);
    $hosts = [];
    for ($depth = 0; $current && $depth < static::MAX_PARAGRAPH_DEPTH; $depth++) {
      $parents = $this->getParagraphParents(array_unique($current));
      $next = [];
      foreach ($current as $start => $pid) {
        [$type, $id] = $parents[$pid] ?? [NULL, NULL];
        if ($type === 'node') {
          $hosts[$start] = $id;
        }
        elseif ($type === 'paragraph') {
          $next[$start] = $id;
        }
      }
      $current = $next;
    }
    return $hosts;
  }

  /**
   * Gets the parent of each paragraph, if it still references the paragraph.
   *
   * @param int[] $pids
   *   Paragraph IDs.
   *
   * @return array
   *   [parent entity type ID, parent ID] pairs, keyed by paragraph ID.
   */
  protected function getParagraphParents(array $pids): array {
    $rows = [];
    foreach (array_chunk($pids, static::CHUNK_SIZE) as $chunk) {
      $rows = array_merge($rows, $this->database->select('paragraphs_item_field_data', 'p')
        ->fields('p', ['id', 'parent_type', 'parent_id', 'parent_field_name'])
        ->condition('p.id', $chunk, 'IN')
        ->condition('p.default_langcode', 1)
        ->execute()
        ->fetchAll());
    }

    // Paragraphs to confirm, grouped by the parent field that should hold them.
    $claims = [];
    foreach ($rows as $row) {
      if (in_array($row->parent_type, ['node', 'paragraph'], TRUE) && $row->parent_field_name) {
        $claims[$row->parent_type][$row->parent_field_name][(int) $row->id] = (int) $row->parent_id;
      }
    }

    $parents = [];
    foreach ($claims as $type => $fields) {
      $storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($type);
      $table_mapping = $this->entityTypeManager->getStorage($type)->getTableMapping();
      foreach ($fields as $field_name => $claimed) {
        $storage_definition = $storage_definitions[$field_name] ?? NULL;
        if (!$storage_definition || !$table_mapping->requiresDedicatedTableStorage($storage_definition)) {
          continue;
        }
        $column = $table_mapping->getFieldColumnName($storage_definition, 'target_id');
        foreach (array_chunk(array_keys($claimed), static::CHUNK_SIZE) as $chunk) {
          $query = $this->database->select($table_mapping->getDedicatedDataTableName($storage_definition), 'f');
          $query->addField('f', 'entity_id');
          $query->addField('f', $column, 'pid');
          $query->condition("f.$column", $chunk, 'IN');
          $query->condition('f.deleted', 0);
          foreach ($query->execute() as $row) {
            $pid = (int) $row->pid;
            if (($claimed[$pid] ?? NULL) === (int) $row->entity_id) {
              $parents[$pid] = [$type, (int) $row->entity_id];
            }
          }
        }
      }
    }
    return $parents;
  }

  /**
   * Whether someone can create content or media that uses a vocabulary.
   */
  protected function canTag(string $vid, AccountInterface $account): bool {
    foreach (['node', 'media'] as $entity_type_id) {
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        continue;
      }
      $handler = $this->entityTypeManager->getAccessControlHandler($entity_type_id);
      foreach ($this->getTermFields($entity_type_id, $vid) as [, , $bundles]) {
        foreach ($bundles as $bundle) {
          if ($handler->createAccess($bundle, $account)) {
            return TRUE;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Gets the fields on an entity type that can reference a vocabulary.
   *
   * @return array
   *   [table name, target ID column, bundles] for each field, keyed by field
   *   name. Only fields stored in their own table are included.
   */
  protected function getTermFields(string $entity_type_id, string $vid): array {
    if (isset($this->termFields[$entity_type_id][$vid])) {
      return $this->termFields[$entity_type_id][$vid];
    }
    $fields = [];
    $map = $this->entityFieldManager->getFieldMap()[$entity_type_id] ?? [];
    if ($map) {
      $field_types = $this->fieldTypeManager->getDefinitions();
      $storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      $table_mapping = $this->entityTypeManager->getStorage($entity_type_id)->getTableMapping();
      foreach ($map as $field_name => $info) {
        $storage_definition = $storage_definitions[$field_name] ?? NULL;
        // Any entity reference field type, such as Mukurtu's People fields,
        // matching how core decides what goes in taxonomy_index.
        $class = $field_types[$info['type']]['class'] ?? '';
        if (!$storage_definition
          || !is_a($class, EntityReferenceItem::class, TRUE)
          || $storage_definition->getSetting('target_type') !== 'taxonomy_term'
          || !$table_mapping->requiresDedicatedTableStorage($storage_definition)) {
          continue;
        }
        $bundles = [];
        foreach ($info['bundles'] as $bundle) {
          $definition = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle)[$field_name] ?? NULL;
          $targets = $definition?->getSetting('handler_settings')['target_bundles'] ?? NULL;
          if ($definition && (empty($targets) || isset($targets[$vid]))) {
            $bundles[] = $bundle;
          }
        }
        if ($bundles) {
          $fields[$field_name] = [
            $table_mapping->getDedicatedDataTableName($storage_definition),
            $table_mapping->getFieldColumnName($storage_definition, 'target_id'),
            $bundles,
          ];
        }
      }
    }
    return $this->termFields[$entity_type_id][$vid] = $fields;
  }

  /**
   * Limits a query on node_field_data to content someone can view.
   */
  protected function addNodeAccess(SelectInterface $query, AccountInterface $account): void {
    $query->addTag('node_access');
    $query->addMetaData('account', $account);
    $query->addMetaData('base_table', 'node_field_data');
  }

  /**
   * Gets each term's parent in a vocabulary.
   *
   * @return int[]
   *   Parent IDs keyed by term ID, with 0 for a top-level term.
   */
  protected function getParents(string $vid): array {
    $rows = $this->database->select('taxonomy_term__parent', 'p')
      ->fields('p', ['entity_id', 'parent_target_id'])
      ->condition('p.bundle', $vid)
      ->execute()
      ->fetchAllKeyed();
    return array_map('intval', $rows);
  }

  /**
   * Adds every term's ancestors to a list of terms.
   *
   * @return int[]
   *   Term IDs.
   */
  protected function withAncestors(array $tids, array $parents): array {
    $found = array_fill_keys($tids, TRUE);
    foreach ($tids as $tid) {
      while (($tid = $parents[$tid] ?? 0) && !isset($found[$tid])) {
        $found[$tid] = TRUE;
      }
    }
    return array_keys($found);
  }

}
