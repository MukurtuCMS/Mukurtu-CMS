<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_search\Kernel;

use Drupal\search_api\Item\ItemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the taxonomy_term_aggregates Search API processor.
 *
 * @see \Drupal\mukurtu_search\Plugin\search_api\processor\TaxonomyTermAggregates
 */
#[Group('mukurtu_search')]
class TaxonomyTermAggregatesTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
    'search_api',
    'search_api_db',
    'mukurtu_search',
  ];

  /**
   * The index under test.
   */
  protected Index $index;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installConfig('search_api');

    ConfigurableLanguage::createFromLangcode('mi')->save();

    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    Vocabulary::create(['vid' => 'places', 'name' => 'Places'])->save();

    foreach (['field_keywords' => 'keywords', 'field_place_type' => 'places'] as $field_name => $vid) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'cardinality' => -1,
        'settings' => ['target_type' => 'taxonomy_term'],
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => 'protocol_aware_content',
        'label' => $field_name,
        'settings' => ['handler_settings' => ['target_bundles' => [$vid => $vid]]],
      ])->save();
    }

    Server::create([
      'id' => 'test_search_server',
      'name' => 'Test search server',
      'backend' => 'search_api_db',
      'backend_config' => [
        'database' => 'default:default',
        'min_chars' => 3,
        'matching' => 'words',
      ],
      'status' => TRUE,
    ])->save();

    $this->index = Index::create([
      'id' => 'test_aggregates_index',
      'name' => 'Test aggregates index',
      'status' => TRUE,
      'server' => 'test_search_server',
      'datasource_settings' => ['entity:node' => []],
      'tracker_settings' => ['default' => []],
      'processor_settings' => ['taxonomy_term_aggregates' => []],
      'field_settings' => [
        'all_taxonomy_term_names' => [
          'label' => 'All taxonomy term names',
          'property_path' => 'all_taxonomy_term_names',
          'type' => 'text',
        ],
        'all_taxonomy_term_uuids' => [
          'label' => 'All taxonomy term UUIDs',
          'property_path' => 'all_taxonomy_term_uuids',
          'type' => 'string',
        ],
      ],
      'options' => ['index_directly' => FALSE],
    ]);
    $this->index->save();
  }

  /**
   * Preprocesses one node into a Search API item and returns it.
   */
  private function itemFor(Node $node, string $langcode): ItemInterface {
    $fields_helper = \Drupal::getContainer()->get('search_api.fields_helper');
    $datasource = $this->index->getDatasource('entity:node');
    $item_id = $datasource->getItemId($node->getTypedData()) . ':' . $langcode;
    $item = $fields_helper->createItem($this->index, $item_id, $datasource);
    $item->setOriginalObject($node->getTranslation($langcode)->getTypedData());
    $this->index->preprocessIndexItems([$item_id => $item]);
    return $item;
  }

  /**
   * Returns a field's values as plain strings (unwrapping TextValue objects).
   *
   * @return string[]
   *   The scalar field values.
   */
  private function fieldStrings(ItemInterface $item, string $field_id): array {
    return array_map('strval', $item->getField($field_id)->getValues());
  }

  /**
   * Term names and UUIDs from every taxonomy reference field are aggregated.
   */
  public function testAggregatesAcrossAllTaxonomyReferenceFields(): void {
    $art = Term::create(['vid' => 'keywords', 'name' => 'Art']);
    $art->save();
    $sacred = Term::create(['vid' => 'places', 'name' => 'Sacred site']);
    $sacred->save();

    $node = Node::create([
      'type' => 'protocol_aware_content',
      'title' => 'Test',
      'field_keywords' => [$art->id()],
      'field_place_type' => [$sacred->id()],
    ]);
    $node->save();

    $item = $this->itemFor($node, 'en');

    $names = $this->fieldStrings($item, 'all_taxonomy_term_names');
    $uuids = $item->getField('all_taxonomy_term_uuids')->getValues();

    sort($names);
    $this->assertSame(['Art', 'Sacred site'], $names);
    $this->assertContains($art->uuid(), $uuids);
    $this->assertContains($sacred->uuid(), $uuids);
  }

  /**
   * A translated index row gets the term name in the item's language.
   */
  public function testTermNamesResolveInItemLanguage(): void {
    $term = Term::create(['vid' => 'keywords', 'name' => 'Water', 'langcode' => 'en']);
    $term->save();
    $term->addTranslation('mi', ['name' => 'Wai'])->save();

    $node = Node::create([
      'type' => 'protocol_aware_content',
      'title' => 'Test',
      'langcode' => 'en',
      'field_keywords' => [$term->id()],
    ]);
    $node->save();
    $node->addTranslation('mi', ['title' => 'Whakamatau', 'field_keywords' => [$term->id()]])->save();

    $en = $this->fieldStrings($this->itemFor($node, 'en'), 'all_taxonomy_term_names');
    $mi = $this->fieldStrings($this->itemFor($node, 'mi'), 'all_taxonomy_term_names');

    $this->assertSame(['Water'], $en);
    $this->assertSame(['Wai'], $mi);
  }

  /**
   * A newly added taxonomy reference field is picked up with no config change.
   */
  public function testNewTaxonomyReferenceFieldNeedsNoIndexChange(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_ad_hoc_vocab',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_ad_hoc_vocab',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Ad hoc vocab',
    ])->save();

    $term = Term::create(['vid' => 'keywords', 'name' => 'Basketry']);
    $term->save();

    $node = Node::create([
      'type' => 'protocol_aware_content',
      'title' => 'Test',
      'field_ad_hoc_vocab' => [$term->id()],
    ]);
    $node->save();

    $names = $this->fieldStrings($this->itemFor($node, 'en'), 'all_taxonomy_term_names');
    $this->assertSame(['Basketry'], $names, 'The ad hoc field was aggregated without touching index config.');
  }

}
