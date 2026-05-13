<?php

namespace Drupal\mukurtu_dictionary\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mukurtu_dictionary\Entity\WordList;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntity;

/**
 * Hook implementations for mukurtu_dictionary node operations.
 */
class NodeHooks
{
    #[Hook('node_insert')]
    public function nodeInsert(EntityInterface $node): void
    {
        if ($node->bundle() === 'word_list') {
            $this->reindexWordListWords($node);
        }
    }

    #[Hook('node_update')]
    public function nodeUpdate(EntityInterface $node): void
    {
        if ($node->bundle() === 'word_list') {
            $this->reindexWordListWords($node, $node->original ?? null);
        }
    }

    #[Hook('node_delete')]
    public function nodeDelete(EntityInterface $node): void
    {
        if ($node->bundle() === 'word_list') {
            $this->reindexWordListWords($node);
        }
    }

    /**
     * Marks affected dictionary_word nodes for re-indexing in Search API.
     *
     * The field_in_word_list computed field is evaluated at index time, so
     * when a word list's membership changes the affected words must be
     * re-indexed or the word list facet will reflect stale data.
     */
    private function reindexWordListWords(EntityInterface $word_list, ?EntityInterface $original = null): void
    {
        if (!\Drupal::moduleHandler()->moduleExists('search_api')) {
            return;
        }

        $word_ids = [];

        foreach ($word_list->get(WordList::WORDS_FIELD) as $item) {
            if ($item->target_id) {
                $word_ids[$item->target_id] = $item->target_id;
            }
        }

        // Include words removed in an update so their index entry is also cleared.
        if ($original) {
            foreach ($original->get(WordList::WORDS_FIELD) as $item) {
                if ($item->target_id) {
                    $word_ids[$item->target_id] = $item->target_id;
                }
            }
        }

        if (empty($word_ids)) {
            return;
        }

        $nodes = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->loadMultiple($word_ids);

        foreach ($nodes as $word_node) {
            ContentEntity::handleEntityChange($word_node, null);
        }
    }
}
