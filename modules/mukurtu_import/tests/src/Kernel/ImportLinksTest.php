<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;


/**
 * Test the import of link fields.
 */
class ImportLinksTest extends MukurtuImportTestBase {
  protected $node;

  protected static $modules = ['link'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['link']);
//    $this->installSchema('link',[]);

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_links',
      'entity_type' => 'node',
      'type' => 'link',
      'cardinality' => -1,
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_name' => 'field_links',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Links',
      'settings' => [
        'title' => TRUE,
        'link_type' => 16,
      ],
    ])->save();

    $node = Node::create([
      'title' => 'Title',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;
  }

  /**
   * Test importing a single URL.
   */
  public function testSingleLinkImport() {
    $data = [
      ['nid', 'Links'],
      [$this->node->id(), "[WSU](https://www.wsu.edu)"],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_links', 'source' => 'Links'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $links = $updated_node->get('field_links')->getValue();
    $this->assertEquals("WSU", $links[0]['title']);
    $this->assertEquals("https://www.wsu.edu", $links[0]['uri']);
  }

  /**
   * Test importing multiple URLs.
   */
  public function testMultipleLinkImport() {
    $data = [
      ['nid', 'Links'],
      [$this->node->id(), "[WSU](https://www.wsu.edu);[Mukurtu] (https://mukurtu.org)"],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_links', 'source' => 'Links'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $links = $updated_node->get('field_links')->getValue();
    $this->assertEquals("WSU", $links[0]['title']);
    $this->assertEquals("https://www.wsu.edu", $links[0]['uri']);
    $this->assertEquals("Mukurtu", $links[1]['title']);
    $this->assertEquals("https://mukurtu.org", $links[1]['uri']);
  }

}
