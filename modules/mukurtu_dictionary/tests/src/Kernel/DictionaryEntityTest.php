<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\mukurtu_core\Entity\BundleSpecificCheckCreateAccessInterface;
use Drupal\mukurtu_dictionary\Entity\DictionaryWord;
use Drupal\mukurtu_dictionary\Entity\DictionaryWordEntry;
use Drupal\mukurtu_dictionary\Entity\DictionaryWordEntryInterface;
use Drupal\mukurtu_dictionary\Entity\DictionaryWordInterface;
use Drupal\mukurtu_dictionary\Entity\SampleSentence;
use Drupal\mukurtu_dictionary\Entity\SampleSentenceInterface;
use Drupal\mukurtu_dictionary\Entity\WordList;
use Drupal\mukurtu_dictionary\Entity\WordListInterface;
use Drupal\mukurtu_drafts\Entity\MukurtuDraftInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

/**
 * Tests the Mukurtu Dictionary entity bundle classes, interfaces, and fields.
 *
 * Covers: bundle class assignment via hook_entity_bundle_info_alter, interface
 * implementation per bundle, required vs optional fields, field cardinality,
 * DictionaryWord::preSave glossary_entry auto-fill,
 * DictionaryWord::bundleCheckCreateAccess language requirement, and
 * protocol field persistence.
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_dictionary')]
class DictionaryEntityTest extends DictionaryTestBase {

  /**
   * Test that loading a dictionary_word node returns the DictionaryWord bundle
   * class and implements all required interfaces.
   */
  public function testDictionaryWordBundleClassAndInterfaces(): void {
    $word = $this->buildDictionaryWord('Test Word');
    $word->save();

    $loaded = Node::load($word->id());

    $this->assertInstanceOf(DictionaryWord::class, $loaded);
    $this->assertInstanceOf(DictionaryWordInterface::class, $loaded);
    $this->assertInstanceOf(CulturalProtocolControlledInterface::class, $loaded);
    $this->assertInstanceOf(BundleSpecificCheckCreateAccessInterface::class, $loaded);
    $this->assertInstanceOf(MukurtuDraftInterface::class, $loaded);
  }

  /**
   * Test that loading a word_list node returns the WordList bundle class
   * and implements all required interfaces.
   */
  public function testWordListBundleClassAndInterfaces(): void {
    $list = $this->buildWordList('Test List');
    $list->save();

    $loaded = Node::load($list->id());

    $this->assertInstanceOf(WordList::class, $loaded);
    $this->assertInstanceOf(WordListInterface::class, $loaded);
    $this->assertInstanceOf(CulturalProtocolControlledInterface::class, $loaded);
    $this->assertInstanceOf(MukurtuDraftInterface::class, $loaded);
  }

  /**
   * Test that loading a dictionary_word_entry paragraph returns the
   * DictionaryWordEntry bundle class and implements its interface.
   */
  public function testDictionaryWordEntryBundleClass(): void {
    $entry = Paragraph::create(['type' => 'dictionary_word_entry']);
    $entry->save();

    $loaded = Paragraph::load($entry->id());

    $this->assertInstanceOf(DictionaryWordEntry::class, $loaded);
    $this->assertInstanceOf(DictionaryWordEntryInterface::class, $loaded);
  }

  /**
   * Test that loading a sample_sentence paragraph returns the SampleSentence
   * bundle class and implements its interface.
   */
  public function testSampleSentenceBundleClass(): void {
    $sentence = Paragraph::create(['type' => 'sample_sentence']);
    $sentence->save();

    $loaded = Paragraph::load($sentence->id());

    $this->assertInstanceOf(SampleSentence::class, $loaded);
    $this->assertInstanceOf(SampleSentenceInterface::class, $loaded);
  }

  /**
   * Test field required/optional status on the dictionary_word bundle.
   */
  public function testDictionaryWordFieldRequiredStatus(): void {
    $definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('node', 'dictionary_word');

    // Language is required.
    $this->assertTrue($definitions['field_dictionary_word_language']->isRequired());

    // Optional fields.
    $this->assertFalse($definitions['field_alternate_spelling']->isRequired());
    $this->assertFalse($definitions['field_glossary_entry']->isRequired());
    $this->assertFalse($definitions['field_keywords']->isRequired());
    $this->assertFalse($definitions['field_definition']->isRequired());
    $this->assertFalse($definitions['field_contributor']->isRequired());
    $this->assertFalse($definitions['field_translation']->isRequired());
    $this->assertFalse($definitions['field_word_type']->isRequired());
    $this->assertFalse($definitions['field_source']->isRequired());
  }

  /**
   * Test field cardinality on the dictionary_word bundle.
   */
  public function testDictionaryWordFieldCardinality(): void {
    $definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('node', 'dictionary_word');

    // Single-value fields.
    $this->assertEquals(1, $definitions['field_dictionary_word_language']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(1, $definitions['field_alternate_spelling']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(1, $definitions['field_glossary_entry']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(1, $definitions['field_definition']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(1, $definitions['field_source']->getFieldStorageDefinition()->getCardinality());

    // Multi-value fields.
    $this->assertEquals(-1, $definitions['field_keywords']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_contributor']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_translation']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_word_type']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_recording']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_sample_sentences']->getFieldStorageDefinition()->getCardinality());
    $this->assertEquals(-1, $definitions['field_additional_word_entries']->getFieldStorageDefinition()->getCardinality());
  }

  /**
   * Test that preSave auto-fills field_glossary_entry from the first character
   * of the title when the field is empty.
   */
  public function testDictionaryWordPreSaveGlossaryEntryAutoFill(): void {
    $word = $this->buildDictionaryWord('Salmon');
    $word->save();

    $loaded = Node::load($word->id());
    $this->assertEquals('S', $loaded->get('field_glossary_entry')->value);
  }

  /**
   * Test that preSave does NOT overwrite a manually set field_glossary_entry.
   */
  public function testDictionaryWordPreSaveGlossaryEntryNotOverwritten(): void {
    $word = $this->buildDictionaryWord('Salmon');
    $word->set('field_glossary_entry', 'X');
    $word->save();

    $loaded = Node::load($word->id());
    $this->assertEquals('X', $loaded->get('field_glossary_entry')->value);
  }

  /**
   * Test that preSave handles multi-byte first character correctly.
   */
  public function testDictionaryWordPreSaveGlossaryEntryMultiByte(): void {
    $word = $this->buildDictionaryWord('áyiiyi');
    $word->save();

    $loaded = Node::load($word->id());
    $this->assertEquals('á', $loaded->get('field_glossary_entry')->value);
  }

  /**
   * Test that bundleCheckCreateAccess returns allowed when a language term exists.
   */
  public function testBundleCheckCreateAccessAllowedWithLanguage(): void {
    // setUp() already creates one language term, so access should be allowed.
    $account = $this->currentUser;
    $result = DictionaryWord::bundleCheckCreateAccess($account, []);
    $this->assertTrue($result->isAllowed());
  }

  /**
   * Test that bundleCheckCreateAccess returns forbidden when no language exists.
   */
  public function testBundleCheckCreateAccessForbiddenWithoutLanguage(): void {
    // Delete the language term created in setUp().
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'language']);
    foreach ($terms as $term) {
      $term->delete();
    }

    $account = $this->currentUser;
    $result = DictionaryWord::bundleCheckCreateAccess($account, []);
    $this->assertFalse($result->isAllowed());
  }

  /**
   * Test that protocol sharing setting and protocol IDs persist on save.
   */
  public function testDictionaryWordProtocolFieldPersistence(): void {
    $word = $this->buildDictionaryWord('Protocol Persistence Test');
    $word->setSharingSetting('all');
    $word->setProtocols([$this->protocol]);
    $word->save();

    $loaded = Node::load($word->id());
    $this->assertEquals('all', $loaded->getSharingSetting());
    $this->assertContains((int) $this->protocol->id(), $loaded->getProtocols());
  }

  /**
   * Test that the language field references the correct term after save.
   */
  public function testDictionaryWordLanguageFieldPersistence(): void {
    $word = $this->buildDictionaryWord('Language Persistence Test');
    $word->save();

    $loaded = Node::load($word->id());
    $languages = $loaded->get('field_dictionary_word_language')->referencedEntities();
    $this->assertCount(1, $languages);
    $this->assertEquals($this->language->id(), $languages[0]->id());
  }

  /**
   * Test that auto_create is enabled on the keywords and word_type taxonomy
   * fields but disabled on language (language terms must be pre-existing).
   */
  public function testDictionaryWordAutoCreateSettings(): void {
    $definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('node', 'dictionary_word');

    $this->assertTrue(
      $definitions['field_keywords']->getSetting('handler_settings')['auto_create'],
      'field_keywords should auto-create taxonomy terms.'
    );

    $this->assertTrue(
      $definitions['field_word_type']->getSetting('handler_settings')['auto_create'],
      'field_word_type should auto-create taxonomy terms.'
    );

    $this->assertFalse(
      $definitions['field_dictionary_word_language']->getSetting('handler_settings')['auto_create'],
      'field_dictionary_word_language must NOT auto-create terms.'
    );
  }

  /**
   * Test that a second language term can be created and used on a new word.
   */
  public function testDictionaryWordWithDifferentLanguage(): void {
    $language2 = Term::create(['name' => 'Second Language', 'vid' => 'language']);
    $language2->save();

    $word = Node::create([
      'type' => 'dictionary_word',
      'title' => 'Word in Second Language',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_dictionary_word_language' => [['target_id' => $language2->id()]],
    ]);
    $word->setSharingSetting('any');
    $word->setProtocols([$this->protocol]);
    $word->save();

    $loaded = Node::load($word->id());
    $languages = $loaded->get('field_dictionary_word_language')->referencedEntities();
    $this->assertCount(1, $languages);
    $this->assertEquals('Second Language', $languages[0]->getName());
  }

}
