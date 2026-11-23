<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Decides which taxonomy terms a visitor can see (#2643).
 *
 * A term's name can be sensitive, such as a person's name or a restricted
 * word, so the name is only shown to someone who can see content using it. A
 * term shows if the visitor can see a content item tagged with it, or with any
 * term nested under it, because many parents have no content of their own.
 *
 * This only looks at content items, through core's taxonomy_index table. Terms
 * on media, paragraphs and communities don't count as used.
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
   * Visible term IDs, keyed by vocabulary ID and then user ID.
   */
  protected array $visible = [];

  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
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
    $visible = $this->withAncestors($this->getTermIdsOnContent($vid, $account), $parents);

    $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($vid);
    if ($vocabulary?->getThirdPartySetting('mukurtu_taxonomy', 'show_unused_terms')) {
      $used = $this->withAncestors($this->getTermIdsOnContent($vid), $parents);
      $visible = array_merge($visible, array_diff(array_keys($parents), $used));
    }

    return $this->visible[$vid][$uid] = array_values(array_unique($visible));
  }

  /**
   * Gets the cacheability of anything that depends on this check.
   */
  public function getCacheability(string $vid): CacheableMetadata {
    return (new CacheableMetadata())
      ->addCacheContexts(['user.node_grants:view', 'user.permissions'])
      ->addCacheTags(['node_list', "taxonomy_term_list:$vid", "config:taxonomy.vocabulary.$vid"]);
  }

  /**
   * Gets the terms in a vocabulary used on published content.
   *
   * @param string $vid
   *   The vocabulary ID.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   Only count content this account can view, or NULL to count all of it.
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
      $query->addTag('node_access');
      $query->addMetaData('account', $account);
      $query->addMetaData('base_table', 'node_field_data');
    }
    return array_map('intval', $query->execute()->fetchCol());
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
