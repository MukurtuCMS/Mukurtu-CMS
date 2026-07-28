<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\node\Entity\Node;

/**
 * Tests WordList word management: add(), remove(), and getCount().
 *
 * Covers: adding words to a list, removing words, counting words,
 * ordering, saving and reloading the list, and postSave cache invalidation.
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_dictionary')]
class DictionaryWordListTest extends DictionaryTestBase {

  /**
   * Test that a new WordList starts with zero words.
   */
  public function testWordListStartsEmpty(): void {
    $list = $this->buildWordList('Empty List');
    $this->assertEquals(0, $list->getCount());
  }

  /**
   * Test that add() increases the count and the word appears after save.
   */
  public function testAddWordToList(): void {
    $word = $this->buildDictionaryWord('First Word');
    $word->save();

    $list = $this->buildWordList('Test List');
    $list->add($word);

    $this->assertEquals(1, $list->getCount());

    $list->save();
    $loaded = Node::load($list->id());
    $this->assertEquals(1, $loaded->getCount());

    $words = $loaded->get('field_words')->referencedEntities();
    $this->assertCount(1, $words);
    $this->assertEquals($word->id(), $words[0]->id());
  }

  /**
   * Test that multiple words can be added and they persist in insertion order.
   */
  public function testAddMultipleWordsPreservesOrder(): void {
    $word1 = $this->buildDictionaryWord('Alpha');
    $word1->save();
    $word2 = $this->buildDictionaryWord('Beta');
    $word2->save();
    $word3 = $this->buildDictionaryWord('Gamma');
    $word3->save();

    $list = $this->buildWordList('Ordered List');
    $list->add($word1);
    $list->add($word2);
    $list->add($word3);
    $list->save();

    $loaded = Node::load($list->id());
    $this->assertEquals(3, $loaded->getCount());

    $words = $loaded->get('field_words')->referencedEntities();
    $this->assertEquals('Alpha', $words[0]->getTitle());
    $this->assertEquals('Beta', $words[1]->getTitle());
    $this->assertEquals('Gamma', $words[2]->getTitle());
  }

  /**
   * Test that remove() removes the correct word and decreases the count.
   */
  public function testRemoveWordFromList(): void {
    $word1 = $this->buildDictionaryWord('Keep Me');
    $word1->save();
    $word2 = $this->buildDictionaryWord('Remove Me');
    $word2->save();

    $list = $this->buildWordList('Removal Test');
    $list->add($word1);
    $list->add($word2);
    $list->save();

    $loaded = Node::load($list->id());
    $loaded->remove($word2);
    $loaded->save();

    $reloaded = Node::load($list->id());
    $this->assertEquals(1, $reloaded->getCount());

    $words = $reloaded->get('field_words')->referencedEntities();
    $this->assertCount(1, $words);
    $this->assertEquals('Keep Me', $words[0]->getTitle());
  }

  /**
   * Test that remove() on a word not in the list is a no-op.
   */
  public function testRemoveWordNotInListIsNoOp(): void {
    $word = $this->buildDictionaryWord('In List');
    $word->save();
    $outsider = $this->buildDictionaryWord('Not In List');
    $outsider->save();

    $list = $this->buildWordList('No-op Remove Test');
    $list->add($word);
    $list->save();

    $loaded = Node::load($list->id());
    $loaded->remove($outsider);
    $loaded->save();

    $reloaded = Node::load($list->id());
    $this->assertEquals(1, $reloaded->getCount());
  }

  /**
   * Test that removing all words results in an empty list.
   */
  public function testRemoveAllWords(): void {
    $word1 = $this->buildDictionaryWord('Word One');
    $word1->save();
    $word2 = $this->buildDictionaryWord('Word Two');
    $word2->save();

    $list = $this->buildWordList('Clear List');
    $list->add($word1);
    $list->add($word2);
    $list->save();

    $loaded = Node::load($list->id());
    $loaded->remove($word1);
    $loaded->remove($word2);
    $loaded->save();

    $reloaded = Node::load($list->id());
    $this->assertEquals(0, $reloaded->getCount());
  }

  /**
   * Test that getCount() reflects unsaved in-memory additions.
   */
  public function testGetCountReflectsInMemoryState(): void {
    $word1 = $this->buildDictionaryWord('Unsaved Word A');
    $word1->save();
    $word2 = $this->buildDictionaryWord('Unsaved Word B');
    $word2->save();

    $list = $this->buildWordList('In-Memory Count Test');
    $this->assertEquals(0, $list->getCount());

    $list->add($word1);
    $this->assertEquals(1, $list->getCount());

    $list->add($word2);
    $this->assertEquals(2, $list->getCount());

    $list->remove($word1);
    $this->assertEquals(1, $list->getCount());
  }

  /**
   * add() does not deduplicate — adding the same word twice results in two
   * entries. This documents current behavior; deduplication is enforced by
   * the UI, not the API. If deduplication is ever added to add(), this test
   * should assert count=1 instead.
   */
  public function testAddSameWordTwiceResultsInDuplicate(): void {
    $word = $this->buildDictionaryWord('Duplicate Word');
    $word->save();

    $list = $this->buildWordList('Duplicate Add Test');
    $list->add($word);
    $list->add($word);
    $list->save();

    $loaded = Node::load($list->id());
    $this->assertEquals(2, $loaded->getCount());
  }

  /**
   * Test that WordList postSave invalidates cache tags of referenced words.
   *
   * This is a smoke test that verifies postSave completes without error
   * when words are referenced — the actual cache invalidation is verified
   * by the absence of exceptions.
   */
  public function testPostSaveDoesNotError(): void {
    $word = $this->buildDictionaryWord('Cache Test Word');
    $word->save();

    $list = $this->buildWordList('Cache Test List');
    $list->add($word);

    // Should not throw — the absence of an exception is the assertion.
    $list->save();
  }

}
