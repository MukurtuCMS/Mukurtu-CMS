<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests blank UUID, langcode and ID cells in a CSV import.
 *
 * A round-tripped export carries these columns, and a blank cell on a new
 * row means "let the system assign it", not "set it to an empty string".
 */
#[Group('mukurtu_import')]
class ImportBlankIdentifierTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
  ];

  /**
   * An existing node to update.
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ConfigurableLanguage::createFromLangcode('en')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();

    $node = Node::create([
      'title' => 'Before Import',
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
   * Builds a mapping for the given CSV headers, plus protocols and sharing.
   *
   * @param array $targets
   *   Map of CSV header => import target.
   *
   * @return array
   *   The import mapping.
   */
  protected function mapping(array $targets): array {
    $mapping = [];
    foreach ($targets as $source => $target) {
      $mapping[] = ['target' => $target, 'source' => $source];
    }
    $mapping[] = ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'];
    $mapping[] = ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'Sharing Setting'];
    return $mapping;
  }

  /**
   * Loads the single node with the given title.
   */
  protected function loadByTitle(string $title): NodeInterface {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['title' => $title]);
    $this->assertCount(1, $nodes, "Exactly one node titled '$title'.");
    return reset($nodes);
  }

  /**
   * Blank UUID cells on new rows get distinct generated UUIDs.
   */
  public function testBlankUuidOnNewRows(): void {
    $protocol = (string) $this->protocol->id();
    $data = [
      ['Title', 'UUID', 'Protocols', 'Sharing Setting'],
      ['First Blank UUID', '', $protocol, 'any'],
      ['Second Blank UUID', '', $protocol, 'any'],
    ];
    $mapping = $this->mapping(['Title' => 'title', 'UUID' => 'uuid']);

    $result = $this->importCsvFile($this->createCsvFile($data), $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $first = $this->loadByTitle('First Blank UUID');
    $second = $this->loadByTitle('Second Blank UUID');
    $this->assertNotEmpty($first->uuid());
    $this->assertNotEmpty($second->uuid());
    $this->assertNotSame($first->uuid(), $second->uuid());
  }

  /**
   * A blank langcode cell on a new row gets the bundle's default language.
   */
  public function testBlankLangcodeOnNewRow(): void {
    $data = [
      ['Title', 'Locale', 'Protocols', 'Sharing Setting'],
      ['Blank Locale', '', (string) $this->protocol->id(), 'any'],
    ];
    $mapping = $this->mapping(['Title' => 'title', 'Locale' => 'langcode']);

    $result = $this->importCsvFile($this->createCsvFile($data), $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $default = Node::create(['type' => 'protocol_aware_content'])->language()->getId();
    $this->assertSame($default, $this->loadByTitle('Blank Locale')->language()->getId());
  }

  /**
   * The same on a translation-enabled bundle, where mapping langcode turns
   * the import into a translation import.
   */
  public function testBlankLangcodeOnNewRowTranslatableBundle(): void {
    \Drupal::service('content_translation.manager')->setEnabled('node', 'protocol_aware_content', TRUE);
    $this->testBlankLangcodeOnNewRow();
  }

  /**
   * Blank langcode and UUID cells on an update keep the stored values.
   */
  public function testBlankLangcodeAndUuidOnUpdate(): void {
    \Drupal::service('content_translation.manager')->setEnabled('node', 'protocol_aware_content', TRUE);
    $original_uuid = $this->node->uuid();
    $original_langcode = $this->node->language()->getId();

    $data = [
      ['ID', 'Title', 'UUID', 'Locale'],
      [$this->node->id(), 'After Import', '', ''],
    ];
    $mapping = [
      ['target' => 'nid', 'source' => 'ID'],
      ['target' => 'title', 'source' => 'Title'],
      ['target' => 'uuid', 'source' => 'UUID'],
      ['target' => 'langcode', 'source' => 'Locale'],
    ];

    $result = $this->importCsvFile($this->createCsvFile($data), $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $node = $this->entityTypeManager->getStorage('node')->loadUnchanged($this->node->id());
    $this->assertSame('After Import', $node->getTitle());
    $this->assertSame($original_uuid, $node->uuid());
    $this->assertSame($original_langcode, $node->language()->getId());
    $this->assertCount(1, $node->getTranslationLanguages());
  }

  /**
   * Whitespace around a langcode doesn't make it invalid.
   */
  public function testPaddedLangcodeOnNewRow(): void {
    $data = [
      ['Title', 'Locale', 'Protocols', 'Sharing Setting'],
      ['Padded Locale', ' es ', (string) $this->protocol->id(), 'any'],
    ];
    $mapping = $this->mapping(['Title' => 'title', 'Locale' => 'langcode']);

    $result = $this->importCsvFile($this->createCsvFile($data), $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $this->assertSame('es', $this->loadByTitle('Padded Locale')->language()->getId());
  }

  /**
   * In one translation import, a blank-langcode row updates the original
   * while a row with a langcode still adds that translation.
   */
  public function testMixedBlankAndSetLangcodeOnTranslatableBundle(): void {
    \Drupal::service('content_translation.manager')->setEnabled('node', 'protocol_aware_content', TRUE);
    $other = Node::create([
      'title' => 'Other Before Import',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $other->setSharingSetting('any');
    $other->setProtocols([$this->protocol]);
    $other->save();

    $data = [
      ['ID', 'Title', 'Locale'],
      [$this->node->id(), 'Original Updated', ''],
      [$other->id(), 'Traducción', 'es'],
    ];
    $mapping = [
      ['target' => 'nid', 'source' => 'ID'],
      ['target' => 'title', 'source' => 'Title'],
      ['target' => 'langcode', 'source' => 'Locale'],
    ];

    $result = $this->importCsvFile($this->createCsvFile($data), $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $storage = $this->entityTypeManager->getStorage('node');
    $node = $storage->loadUnchanged($this->node->id());
    $this->assertSame('Original Updated', $node->getTitle());
    $this->assertFalse($node->hasTranslation('es'));

    $other = $storage->loadUnchanged($other->id());
    $this->assertSame('Other Before Import', $other->getTitle());
    $this->assertTrue($other->hasTranslation('es'));
    $this->assertSame('Traducción', $other->getTranslation('es')->getTitle());
  }

  /**
   * A blank ID cell on a new row creates new content.
   */
  public function testBlankIdOnNewRow(): void {
    $data = [
      ['ID', 'Title', 'Protocols', 'Sharing Setting'],
      ['', 'Blank ID', (string) $this->protocol->id(), 'any'],
    ];
    $mapping = $this->mapping(['ID' => 'nid', 'Title' => 'title']);

    $result = $this->importCsvFile($this->createCsvFile($data), $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $this->assertNotEquals($this->node->id(), $this->loadByTitle('Blank ID')->id());
  }

}
