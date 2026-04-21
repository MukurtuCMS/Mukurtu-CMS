<?php

namespace Drupal\Tests\search_api\Kernel\Processor;

use Drupal\node\Entity\NodeType;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Kernel\PostRequestIndexingTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Language (with fallback)" processor at a higher level.
 *
 * @group search_api
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\processor\LanguageWithFallback
 */
#[RunTestsInSeparateProcesses]
class LanguageWithFallbackTest extends ProcessorTestBase {

  use PostRequestIndexingTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'search_api_test_language_fallback',
    'language_fallback_fix',
  ];

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('language_with_fallback');

    // search_api_test_language_fallback.module adds a fallback from 'fr' to
    // 'es'. When we then leave 'en' as site default language and set 'de' as
    // original node language, we are able to spot false fallbacks to either of
    // those.
    foreach (['de', 'fr', 'es'] as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->enable()->save();
    }

    NodeType::create([
      'type' => 'article',
    ])->save();

    $lwf_field = new Field($this->index, 'language_with_fallback');
    $lwf_field->setType('string');
    $lwf_field->setPropertyPath('language_with_fallback');
    $lwf_field->setLabel('Language (with fallback)');
    $this->index->addField($lwf_field);
    $this->index->setOption('index_directly', TRUE);
    $this->index->save();
  }

  /**
   * Tests indexing.
   *
   * Expected fallbacks: search_api_test_language_fallback.module has these:
   * - no fallbacks
   * - except 'fr' has fallback 'es'
   *
   * Note that language_fallback_fix.module (which is a test dependency) ensures
   * that there can be languages without fallback, which we test here.
   *
   * @covers ::addFieldValues
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testIndexing() {
    $nodeValues = [
      'title' => 'Test',
      'type' => 'article',
    ];

    // First test with a German node.
    $node = Node::create($nodeValues + ['langcode' => 'de']);
    $node->save();
    $this->node = $node;

    $this->triggerPostRequestIndexing();
    $expected[$this->getItemIdForLanguage('de')] = ['de'];
    $this->assertEquals($expected, $this->getLanguageWithFallbackValues(), 'Added default translation is indexed correctly.');

    $node->addTranslation('es', $nodeValues);
    $node->save();
    $this->triggerPostRequestIndexing();
    $expected[$this->getItemIdForLanguage('es')] = ['es', 'fr'];
    $this->assertEquals($expected, $this->getLanguageWithFallbackValues(), 'Added translation with fallback is indexed correctly.');

    $node->addTranslation('fr', $nodeValues);
    $node->save();
    $this->triggerPostRequestIndexing();
    $expected[$this->getItemIdForLanguage('es')] = ['es'];
    $expected[$this->getItemIdForLanguage('fr')] = ['fr'];
    $this->assertEquals($expected, $this->getLanguageWithFallbackValues(), 'Added translation is indexed correctly and former fallback removed.');

    $node->removeTranslation('fr');
    $node->save();
    $this->triggerPostRequestIndexing();
    unset($expected[$this->getItemIdForLanguage('fr')]);
    $expected[$this->getItemIdForLanguage('es')] = ['es', 'fr'];
    $this->assertEquals($expected, $this->getLanguageWithFallbackValues(), 'Removed translation is unindexed correctly and fallback re-added.');

    $node->removeTranslation('es');
    $node->save();
    $this->triggerPostRequestIndexing();
    unset($expected[$this->getItemIdForLanguage('es')]);
    $this->assertEquals($expected, $this->getLanguageWithFallbackValues(), 'Removed translation is unindexed correctly.');

    $node->delete();
    $this->triggerPostRequestIndexing();
    $expected = [];
    $this->assertEquals($expected, $this->getLanguageWithFallbackValues(), 'Removed default translation is unindexed correctly.');

    // Then test with a Spanish node.
    $node = Node::create($nodeValues + ['langcode' => 'es']);
    $node->save();
    $this->node = $node;

    $this->triggerPostRequestIndexing();
    $expected[$this->getItemIdForLanguage('es')] = ['es', 'fr'];
    $this->assertEquals($expected, $this->getLanguageWithFallbackValues(), 'Added default translation with fallback is indexed correctly.');

    $node->addTranslation('de', $nodeValues);
    $node->save();
    $this->triggerPostRequestIndexing();
    $expected[$this->getItemIdForLanguage('de')] = ['de'];
    $this->assertEquals($expected, $this->getLanguageWithFallbackValues(), 'Added translation is indexed correctly.');

    $node->addTranslation('fr', $nodeValues);
    $node->save();
    $this->triggerPostRequestIndexing();
    $expected[$this->getItemIdForLanguage('es')] = ['es'];
    $expected[$this->getItemIdForLanguage('fr')] = ['fr'];
    $this->assertEquals($expected, $this->getLanguageWithFallbackValues(), 'Added translation is indexed correctly and former fallback removed.');

    $node->removeTranslation('de');
    $node->save();
    $this->triggerPostRequestIndexing();
    unset($expected[$this->getItemIdForLanguage('de')]);
    $this->assertEquals($expected, $this->getLanguageWithFallbackValues(), 'Removed translation is unindexed correctly.');

    $node->removeTranslation('fr');
    $node->save();
    $this->triggerPostRequestIndexing();
    unset($expected[$this->getItemIdForLanguage('fr')]);
    $expected[$this->getItemIdForLanguage('es')] = ['es', 'fr'];
    $this->assertEquals($expected, $this->getLanguageWithFallbackValues(), 'Removed translation is unindexed correctly and fallback re-added.');

    $node->delete();
    $this->triggerPostRequestIndexing();
    $expected = [];
    $this->assertEquals($expected, $this->getLanguageWithFallbackValues(), 'Removed default translation is unindexed correctly.');
  }

  /**
   * Retrieves the indexed values.
   *
   * @return array
   *   The indexed "language_with_fallback" field values for all indexed items,
   *   keyed by item ID.
   */
  protected function getLanguageWithFallbackValues() {
    $query = $this->index->query();
    // We don't need a query condition as we have only one node anyway.
    $results = $query->execute();
    $values = [];
    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $fieldValues = $result->getField('language_with_fallback')->getValues();
      sort($fieldValues);
      $values[$result->getId()] = $fieldValues;
    }
    return $values;
  }

  /**
   * Retrieves the test node's item ID for the given language.
   *
   * @param string $langcode
   *   The language's code.
   *
   * @return string
   *   The Search API item ID for the test node in the given language.
   */
  protected function getItemIdForLanguage($langcode) {
    $nid = $this->node->id();
    return Utility::createCombinedId('entity:node', "$nid:$langcode");
  }

}
