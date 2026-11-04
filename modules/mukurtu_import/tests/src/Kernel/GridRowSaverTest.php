<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_import\BulkEdit\GridRowSaver;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests saving one row of the per-item bulk edit grid.
 *
 * Cells take the CSV importer's text and go through its destination, but
 * only the changed fields are validated.
 */
#[Group('mukurtu_import')]
class GridRowSaverTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['original_date'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_keywords',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_keywords',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Keywords',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['keywords' => 'keywords'], 'auto_create' => TRUE],
      ],
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_original_date',
      'entity_type' => 'node',
      'type' => 'original_date',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_original_date',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Original Date',
    ])->save();
    foreach (['field_summary' => FALSE, 'field_source' => TRUE] as $field => $required) {
      FieldStorageConfig::create(['field_name' => $field, 'entity_type' => 'node', 'type' => 'string'])->save();
      FieldConfig::create([
        'field_name' => $field,
        'entity_type' => 'node',
        'bundle' => 'protocol_aware_content',
        'label' => $field === 'field_summary' ? 'Summary' : 'Source',
        'required' => $required,
      ])->save();
    }
  }

  /**
   * Creates an item, leaving the required Source empty like migrated content.
   */
  protected function item(): NodeInterface {
    $node = Node::create([
      'title' => 'Canoe',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    return $node;
  }

  /**
   * Reloads an item.
   */
  protected function reload(NodeInterface $node): NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache([$node->id()]);
    return $storage->load($node->id());
  }

  /**
   * Counts an item's revisions.
   */
  protected function revisions(NodeInterface $node): int {
    return (int) $this->entityTypeManager->getStorage('node')->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE)
      ->condition('nid', $node->id())
      ->count()
      ->execute();
  }

  /**
   * A change saves even when another field is already invalid.
   */
  public function testSavesChangedFieldOnly(): void {
    $node = $this->item();
    $before = $this->revisions($node);

    $errors = (new GridRowSaver())->save($node, ['field_summary' => 'Carved by hand.'], 'Grid log');

    $this->assertSame([], $errors);
    $node = $this->reload($node);
    $this->assertSame('Carved by hand.', $node->get('field_summary')->value);
    $this->assertSame($before + 1, $this->revisions($node));
    $this->assertSame('Grid log', $node->getRevisionLogMessage());
    $this->assertSame((int) $this->currentUser->id(), (int) $node->getRevisionUserId());
  }

  /**
   * An invalid change isn't saved and is reported against its field.
   */
  public function testInvalidChangeReported(): void {
    $node = $this->item();
    $before = $this->revisions($node);

    $errors = (new GridRowSaver())->save($node, ['title' => '', 'field_summary' => 'Changed'], 'Grid log');

    $this->assertArrayHasKey('title', $errors);
    $this->assertArrayNotHasKey('field_source', $errors);
    $node = $this->reload($node);
    $this->assertSame('Canoe', $node->label());
    $this->assertNull($node->get('field_summary')->value);
    $this->assertSame($before, $this->revisions($node));
  }

  /**
   * Term names are looked up or created, as in a CSV import.
   */
  public function testTermsByName(): void {
    Term::create(['vid' => 'keywords', 'name' => 'Basketry'])->save();
    $node = $this->item();

    $this->assertSame([], (new GridRowSaver())->save($node, ['field_keywords' => 'Basketry;Cedar'], 'Grid log'));

    $names = array_map(fn($term) => $term->label(), $this->reload($node)->get('field_keywords')->referencedEntities());
    $this->assertSame(['Basketry', 'Cedar'], $names);
    $this->assertCount(2, $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => 'keywords']));
  }

  /**
   * A value the importer can't convert is reported against its cell.
   */
  public function testConversionErrorReported(): void {
    // Only lookups, not term creation, refuse an ambiguous name.
    $field = FieldConfig::loadByName('node', 'protocol_aware_content', 'field_keywords');
    $settings = $field->getSetting('handler_settings');
    $settings['auto_create'] = FALSE;
    $field->setSetting('handler_settings', $settings)->save();
    // Two terms with one name: the importer can't tell which is meant.
    Term::create(['vid' => 'keywords', 'name' => 'Twice'])->save();
    Term::create(['vid' => 'keywords', 'name' => 'Twice'])->save();
    $node = $this->item();
    $before = $this->revisions($node);

    $errors = (new GridRowSaver())->save($node, ['field_keywords' => 'Twice'], 'Grid log');

    $this->assertArrayHasKey('field_keywords', $errors);
    $this->assertSame($before, $this->revisions($node));
  }

  /**
   * A value a field refuses while it's set is reported against its cell.
   */
  public function testRefusedValueReported(): void {
    $node = $this->item();
    $before = $this->revisions($node);

    $errors = (new GridRowSaver())->save($node, ['field_original_date' => 'not a date', 'field_summary' => 'Changed'], 'Grid log');

    $this->assertSame(['field_original_date' => ['Dates must be in YYYY, YYYY-MM, or YYYY-MM-DD format.']], $errors);
    $this->assertSame($before, $this->revisions($node));
  }

  /**
   * An unexpected error's message loses the server path core adds.
   */
  public function testServerPathRemoved(): void {
    $this->assertSame('Something broke.', GridRowSaver::withoutServerPath('Something broke. (/var/www/html/web/modules/x/src/Y.php:141)'));
    $this->assertSame('Keep (these) words.', GridRowSaver::withoutServerPath('Keep (these) words.'));
  }

  /**
   * No changes, no save.
   */
  public function testNothingToSave(): void {
    $node = $this->item();
    $before = $this->revisions($node);

    $this->assertSame([], (new GridRowSaver())->save($node, [], 'Grid log'));
    $this->assertSame($before, $this->revisions($node));
  }

}
