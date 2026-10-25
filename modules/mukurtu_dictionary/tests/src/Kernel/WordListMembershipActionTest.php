<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_dictionary\Entity\WordList;
use Drupal\mukurtu_dictionary\Entity\WordListInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Add to Word List and Remove from Word List bulk actions.
 */
#[Group('mukurtu_dictionary')]
class WordListMembershipActionTest extends DictionaryTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views_bulk_operations'];

  /**
   * A protocol steward who isn't uid 1, so access checks really run.
   */
  protected UserInterface $editor;

  /**
   * A word list the editor can update.
   */
  protected WordListInterface $list;

  /**
   * {@inheritdoc}
   */
  protected function getProtocolStewardPermissions(): array {
    // Without a group audience field, OG names the update permission
    // "update any <bundle> node".
    return array_merge(parent::getProtocolStewardPermissions(), [
      'update any word_list node',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->list = $this->buildWordList('List A');
    $this->list->save();

    $this->editor = User::create(['name' => 'editor']);
    $this->editor->save();
    $this->community->addMember($this->editor);
    $this->protocol->addMember($this->editor, ['protocol_steward']);
    $this->container->get('current_user')->setAccount($this->editor);
  }

  /**
   * Returns a configured action instance.
   */
  protected function action(string $plugin_id = 'mukurtu_add_to_word_list_action') {
    $action = $this->container->get('plugin.manager.views_bulk_operations_action')
      ->createInstance($plugin_id);
    $action->setConfiguration(['container' => $this->list->id()]);
    return $action;
  }

  /**
   * Returns the word IDs in the list, reloaded from storage.
   */
  protected function wordIds(): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$this->list->id()]);
    return array_map('intval', array_column($storage->load($this->list->id())->get(WordList::WORDS_FIELD)->getValue(), 'target_id'));
  }

  /**
   * The editor is a real, non-admin account with update access to the list.
   */
  public function testEditorIsNotUidOne(): void {
    $this->assertNotEquals(1, (int) $this->editor->id());
    $this->assertTrue($this->list->access('update', $this->editor));
  }

  /**
   * Words are added once, and words already in the list are skipped.
   */
  public function testAddsWordsAndSkipsDuplicates(): void {
    $a = $this->buildDictionaryWord('a');
    $a->save();
    $b = $this->buildDictionaryWord('b');
    $b->save();

    $first = $this->action()->executeMultiple([$a]);
    $second = $this->action()->executeMultiple([$a, $b]);

    $this->assertSame([(int) $a->id(), (int) $b->id()], $this->wordIds());
    $this->assertSame('Added to List A', $first[0]['message']);
    $this->assertSame(['warning', 'status'], array_column($second, 'type'));
    $this->assertSame('Already in List A', $second[0]['message']);
  }

  /**
   * Only dictionary words pass the per-item access check.
   */
  public function testAccessRequiresDictionaryWord(): void {
    $word = $this->buildDictionaryWord('word');
    $word->save();
    $other_list = $this->buildWordList('List B');
    $other_list->save();

    $this->assertTrue($this->action()->access($word, $this->editor));

    $result = $this->action()->access($other_list, $this->editor, TRUE);
    $this->assertTrue($result->isForbidden());
    $this->assertSame('Only dictionary words can be added to a word list.', $result->getReason());
  }

  /**
   * With no list to pick, the form explains why and hides Apply.
   */
  public function testPickerWithNoWordLists(): void {
    $outsider = User::create(['name' => 'outsider']);
    $outsider->save();
    $this->container->get('current_user')->setAccount($outsider);

    $form = $this->action()->buildConfigurationForm(['actions' => ['submit' => []]], new FormState());

    $this->assertSame('There are no word lists you can add words to.', (string) $form['container']['#markup']);
    $this->assertFalse($form['actions']['submit']['#access']);
  }

  /**
   * Removing takes words out and skips words not in the list.
   */
  public function testRemovesWordsAndSkipsMissing(): void {
    $a = $this->buildDictionaryWord('a');
    $a->save();
    $b = $this->buildDictionaryWord('b');
    $b->save();
    $this->action()->executeMultiple([$a]);

    $results = $this->action('mukurtu_remove_from_word_list_action')->executeMultiple([$a, $b]);

    $this->assertSame([], $this->wordIds());
    $this->assertSame('Removed from List A', $results[0]['message']);
    $this->assertSame('Not in List A', $results[1]['message']);
    $this->assertSame('warning', $results[1]['type']);
  }

  /**
   * With no list to pick, the remove form says so in its own words.
   */
  public function testRemovePickerWithNoWordLists(): void {
    $outsider = User::create(['name' => 'outsider']);
    $outsider->save();
    $this->container->get('current_user')->setAccount($outsider);

    $form = $this->action('mukurtu_remove_from_word_list_action')->buildConfigurationForm(['actions' => ['submit' => []]], new FormState());

    $this->assertSame('There are no word lists you can remove words from.', (string) $form['container']['#markup']);
  }

}
