<?php

namespace Drupal\mukurtu_dictionary\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\mukurtu_dictionary\Entity\WordList;
use Drupal\node\NodeInterface;

/**
 * Contributes "Add to Word List" to the browse-card quick-actions system
 * (see hook_mukurtu_browse_quick_actions_alter() in mukurtu_browse.module).
 */
class BrowseHooks
{
    #[Hook('mukurtu_browse_quick_actions_alter')]
    public function browseQuickActionsAlter(array &$actions, NodeInterface $node): void
    {
        // Word lists can only ever contain dictionary_word items - this
        // gates everything below regardless of the user's general
        // word_list creation access.
        if ($node->bundle() !== 'dictionary_word') {
            return;
        }

        $createAccess = \Drupal::entityTypeManager()
            ->getAccessControlHandler('node')
            ->createAccess('word_list', \Drupal::currentUser(), [], true);

        if (!$this->hasAddableWordListFor($node) && !$createAccess->isAllowed()) {
            return;
        }

        // mukurtu_modal opts the form into the AJAX modal treatment its
        // full-page tab already uses (see mukurtu_dictionary.module).
        // mukurtu_stay tells that shared ajax callback to stay on this
        // browse listing instead of redirecting to the item's own page,
        // which is what it does for the tab (where you're already there).
        $actions['add_to_word_list'] = [
            'title' => t('Add to Word List'),
            'url' => Url::fromRoute('mukurtu_dictionary.add_word_to_list', ['node' => $node->id()], ['query' => ['mukurtu_modal' => '1', 'mukurtu_stay' => '1']]),
            'group' => 'overflow',
            'weight' => 15,
            'attributes' => [
                'class' => ['use-ajax'],
                'data-dialog-type' => 'modal',
                'data-dialog-options' => Json::encode(['width' => 560]),
                'data-quick-action-trigger' => 'word-list-' . $node->id(),
            ],
            'cache' => [
                'tags' => array_merge($this->getEditableWordListsData()['tags'], $createAccess->getCacheTags()),
                'contexts' => array_merge(['user'], $createAccess->getCacheContexts()),
            ],
        ];
    }

    /**
     * Whether the current user has at least one editable word list that
     * doesn't already contain the given node.
     *
     * MukurtuAddWordToListController::getValidWordLists() runs two entity
     * queries plus a load-and-access-check of every candidate word list -
     * fine for a single node's "Add to Word List" page, but that cost would
     * repeat once per row on a browse listing. This mirrors the fix already
     * applied for collections (see
     * Drupal\mukurtu_collection\CollectionQuickActionAccessHelper): compute
     * "word lists the current user can edit" once per request
     * (drupal_static()) and once per user across requests (Cache API).
     */
    private function hasAddableWordListFor(NodeInterface $node): bool
    {
        if ($node->bundle() !== 'dictionary_word') {
            return false;
        }
        foreach ($this->getEditableWordListsData()['lists'] as $wlid => $items) {
            if ((int) $wlid === (int) $node->id()) {
                continue;
            }
            if (!isset($items[$node->id()])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array{lists: array<int, array<int, bool>>, tags: string[]}
     */
    private function getEditableWordListsData(): array
    {
        $data = &drupal_static(__CLASS__ . '_data');
        if ($data !== null) {
            return $data;
        }

        $current_user = \Drupal::currentUser();
        $uid = $current_user->id();
        if (!$uid) {
            return $data = ['lists' => [], 'tags' => []];
        }

        $cache = \Drupal::cache();
        $cid = 'mukurtu_dictionary:editable_word_lists:' . $uid;
        if ($cached = $cache->get($cid)) {
            return $data = $cached->data;
        }

        $storage = \Drupal::entityTypeManager()->getStorage('node');
        $nids = $storage->getQuery()
            ->condition('type', 'word_list')
            ->accessCheck(true)
            ->execute();

        $editable = [];
        $tags = [];
        foreach ($storage->loadMultiple($nids) as $list) {
            $tags = array_merge($tags, $list->getCacheTags());
            if (!$list->access('update', $current_user)) {
                continue;
            }
            $items = [];
            foreach ($list->get(WordList::WORDS_FIELD) as $item) {
                if (!empty($item->target_id)) {
                    $items[$item->target_id] = true;
                }
            }
            $editable[$list->id()] = $items;
        }

        $result = ['lists' => $editable, 'tags' => $tags];
        // Bounded lifetime as a safety net for a brand-new word list
        // becoming eligible without any existing list node being saved (the
        // tags above cover every other membership/ownership change
        // immediately).
        $cache->set($cid, $result, time() + 3600, $tags);

        return $data = $result;
    }
}
