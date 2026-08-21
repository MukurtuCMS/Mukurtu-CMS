<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;

/**
 * Reproduces the review-reported failure on PR #2035: importing a CSV whose
 * headers match what mukurtu_export actually writes, through the real
 * shipped place_record_all_fields template's Cultural Protocols and title
 * mapping, threw a hard MigrateException and left the title blank.
 *
 * Uses the actual shipped YAML (not a hand-rolled mapping) so a future
 * regression in the template file itself would be caught here, not just in
 * the static consistency checks.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/pull/2035#pullrequestreview-4994800088
 * @see ImportTemplateExportConsistencyTest
 */
class ShippedTemplateImportTest extends MukurtuImportTestBase {

  /**
   * The test node imported/updated by each test.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * A protocol available to assign via the imported "Protocols" column.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Protocol
   */
  protected $protocol2;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $node = Node::create([
      'title' => 'Original Title',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;

    $protocol = Protocol::create([
      'name' => 'Protocol 2',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $protocol->addMember($this->currentUser, ['protocol_steward']);
    $this->protocol2 = $protocol;
  }

  /**
   * Reads a shipped mukurtu_import_strategy template's mapping array.
   */
  private function getShippedMapping(string $template_id): array {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    $config = (new FileStorage($module_path . '/config/install'))
      ->read("mukurtu_import.mukurtu_import_strategy.{$template_id}");
    return $config['mapping'];
  }

  /**
   * The place_record template's title and Cultural Protocols mapping must
   * populate both fields correctly from a real-header CSV, with no
   * exception from the strict-mode Explode process plugin.
   */
  public function testPlaceRecordTitleAndProtocolsMapping() {
    $full_mapping = $this->getShippedMapping('place_record_all_fields');
    $wanted_targets = ['title', 'field_cultural_protocols/protocols'];
    $mapping = array_values(array_filter($full_mapping, fn (array $m) => in_array($m['target'], $wanted_targets, TRUE)));
    $mapping[] = ['source' => 'nid', 'target' => 'nid'];

    $data = [
      ['nid', 'Title', 'Protocols'],
      [$this->node->id(), 'Updated Place Title', (string) $this->protocol2->id()],
    ];
    $import_file = $this->createCsvFile($data);

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertSame('Updated Place Title', $updated_node->getTitle());
    $this->assertEquals([$this->protocol2->id()], $updated_node->getProtocols());
  }

  /**
   * The person_record template's title and Biographical Information
   * Sections columns must resolve to the correct real target fields.
   */
  public function testPersonRecordTitleMapping() {
    $full_mapping = $this->getShippedMapping('person_record_all_fields');
    $wanted_targets = ['title'];
    $mapping = array_values(array_filter($full_mapping, fn (array $m) => in_array($m['target'], $wanted_targets, TRUE)));
    $mapping[] = ['source' => 'nid', 'target' => 'nid'];

    $data = [
      ['nid', 'Title'],
      [$this->node->id(), 'Updated Person Title'],
    ];
    $import_file = $this->createCsvFile($data);

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertSame('Updated Person Title', $updated_node->getTitle());
  }

  /**
   * The collection template's title and Cultural Protocols mapping (also
   * affected by the "Cultural Protocols > " prefix bug) must work too.
   */
  public function testCollectionTitleAndProtocolsMapping() {
    $full_mapping = $this->getShippedMapping('collection_all_fields');
    $wanted_targets = ['title', 'field_cultural_protocols/sharing_setting'];
    $mapping = array_values(array_filter($full_mapping, fn (array $m) => in_array($m['target'], $wanted_targets, TRUE)));
    $mapping[] = ['source' => 'nid', 'target' => 'nid'];

    $data = [
      ['nid', 'Title', 'Sharing Setting'],
      [$this->node->id(), 'Updated Collection Title', 'all'],
    ];
    $import_file = $this->createCsvFile($data);

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertSame('Updated Collection Title', $updated_node->getTitle());
    $this->assertEquals('all', $updated_node->getSharingSetting());
  }

  /**
   * The Cultural Protocols field process plugin's dropdown/template labels
   * for its two sub-properties must be the bare "Protocols"/"Sharing
   * Setting" mukurtu_export actually writes, not the generic "{field
   * label} > {property label}" format the base plugin class falls back to
   * (which would show as "Cultural Protocols > Protocols" and break the
   * "Download CSV Template" feature's round trip).
   */
  public function testCulturalProtocolsSupportedPropertyLabels() {
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'protocol_aware_content');
    $field_definition = $field_definitions['field_cultural_protocols'];

    $plugin = \Drupal::service('plugin.manager.mukurtu_import_field_process')->getInstance(['field_definition' => $field_definition]);
    $properties = $plugin->getSupportedProperties($field_definition);

    $this->assertSame('Protocols', (string) $properties['protocols']['label']);
    $this->assertSame('Sharing Setting', (string) $properties['sharing_setting']['label']);
  }

}
