<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;


/**
 * Test the ability of import to lookup entities by different ID types.
 */
class ImportTaxonomyTermsTest extends MukurtuImportTestBase {
  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $vocabulary = Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords']);
    $vocabulary->save();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_keywords',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_name' => 'field_keywords',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Keywords',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'keywords' => 'keywords',
          ],
          'auto_create' => TRUE,
        ],
      ],
    ])->save();
  }

  /**
   * Testing multiple taxonomy terms.
   */
  public function testMultipleTaxonomyTerms() {
    $node = Node::create([
      'title' => 'Keyword Test',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();

    $data = [
      ['nid', 'keywords'],
      [$node->id(), 'Keyword 1;Keyword 2;Keyword 3'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_keywords', 'source' => 'keywords'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);

    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $node = $this->entityTypeManager->getStorage('node')->load($node->id());

    $terms = $node->get('field_keywords')->referencedEntities();
    $this->assertCount(3, $terms);

    // Testing ordering and existence.
    $this->assertEquals('Keyword 1', $terms[0]->getName());
    $this->assertEquals('Keyword 2', $terms[1]->getName());
    $this->assertEquals('Keyword 3', $terms[2]->getName());

    // Create a new node via import. Use some of the same terms created above.
    $data2 = [
      ['title', 'keywords'],
      ["Existing Term Test", 'Keyword 1;Keyword 3'],
    ];
    $import_file2 = $this->createCsvFile($data2);
    $mapping2 = [
      ['target' => 'title', 'source' => 'title'],
      ['target' => 'field_keywords', 'source' => 'keywords'],
    ];

    $result2 = $this->importCsvFile($import_file2, $mapping2);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result2);
    /** @var \Drupal\node\NodeInterface $node2 */
    $titleQuery = $this->entityTypeManager->getStorage('node')->loadByProperties(['title'=> 'Existing Term Test']);
    $this->assertCount(1, $titleQuery);
    $node2 = reset($titleQuery);
    $terms2 = $node2->get('field_keywords')->referencedEntities();
    $this->assertCount(2, $terms2);
    $this->assertEquals('Keyword 1', $terms2[0]->getName());
    $this->assertEquals('Keyword 3', $terms2[1]->getName());

    // Make sure the terms got reused rather than created twice.
    $this->assertEquals($terms[0]->id(), $terms2[0]->id());
    $this->assertEquals($terms[2]->id(), $terms2[1]->id());
  }

}
