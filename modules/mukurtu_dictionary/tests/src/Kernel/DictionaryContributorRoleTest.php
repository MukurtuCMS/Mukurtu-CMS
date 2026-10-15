<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\mukurtu_core\Traits\EntityReferenceRoleUpdateTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests roles on dictionary Contributor fields, and their update path.
 *
 * Covers both storages: the dictionary word's node field (shared with
 * digital heritage) and the word entry paragraph's own field.
 */
#[Group('mukurtu_dictionary')]
class DictionaryContributorRoleTest extends DictionaryTestBase {

  use EntityReferenceRoleUpdateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['flat_taxonomy'];

  /**
   * Creates and saves a term.
   */
  protected function term(string $vid, string $name): Term {
    $term = Term::create(['vid' => $vid, 'name' => $name]);
    $term->save();
    return $term;
  }

  /**
   * Roles persist on the word and on a word entry.
   */
  public function testRolesPersistOnWordAndEntry(): void {
    Vocabulary::create(['vid' => 'role', 'name' => 'Role'])->save();
    $role = $this->term('role', 'Speaker');
    $contributor = $this->term('contributor', 'Elder Mary');

    $word = $this->buildDictionaryWord('Word');
    $word->set('field_contributor', [['target_id' => $contributor->id(), 'role_target_id' => $role->id()]]);
    $word->save();
    $entry = Paragraph::create([
      'type' => 'dictionary_word_entry',
      'field_contributor' => [['target_id' => $contributor->id(), 'role_target_id' => $role->id()]],
    ]);
    $entry->save();

    $this->assertEquals($role->id(), $this->reloadEntity('node', $word->id())->get('field_contributor')->role_target_id);
    $this->assertEquals($role->id(), $this->reloadEntity('paragraph', $entry->id())->get('field_contributor')->role_target_id);
  }

  /**
   * The update converts both existing Contributor storages.
   */
  public function testUpdateConvertsWordAndEntryContributor(): void {
    $contributor = $this->term('contributor', 'Elder Mary');
    $word = $this->buildDictionaryWord('Word');
    $word->set('field_contributor', [['target_id' => $contributor->id()]]);
    $word->save();
    $entry = Paragraph::create([
      'type' => 'dictionary_word_entry',
      'field_contributor' => [['target_id' => $contributor->id()]],
    ]);
    $entry->save();

    $targets = ['node' => $word->id(), 'paragraph' => $entry->id()];
    foreach (array_keys($targets) as $entity_type_id) {
      $this->rollBackToEntityReference($entity_type_id, 'field_contributor');
      $this->assertStoragePending(TRUE, $entity_type_id, 'field_contributor');
    }

    $this->runRoleUpdate();

    $role = $this->term('role', 'Speaker');
    foreach ($targets as $entity_type_id => $id) {
      $this->assertStoragePending(FALSE, $entity_type_id, 'field_contributor');
      $loaded = $this->reloadEntity($entity_type_id, $id);
      $this->assertEquals($contributor->id(), $loaded->get('field_contributor')->target_id, $entity_type_id);
      $this->assertNull($loaded->get('field_contributor')->role_target_id, $entity_type_id);
      $loaded->get('field_contributor')->role_target_id = $role->id();
      $loaded->save();
      $this->assertEquals($role->id(), $this->reloadEntity($entity_type_id, $id)->get('field_contributor')->role_target_id, $entity_type_id);
    }
  }

}
