<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests importing nested taxonomy terms (#2520).
 *
 * @see \Drupal\mukurtu_import\Plugin\migrate\process\TermParent
 * @see \Drupal\mukurtu_import\TermParentResolver
 */
#[Group('mukurtu_import')]
class ImportNestedTermsTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    Vocabulary::create(['vid' => 'location', 'name' => 'Location'])->save();
  }

  /**
   * Imports rows into Location.
   *
   * @param array $rows
   *   Rows of the columns in $header.
   * @param array $header
   *   The CSV header, mapped by its own lowercase names.
   *
   * @return array
   *   Each Location term's parent name, or '' at the top level, keyed by name.
   */
  protected function importRows(array $rows, array $header = ['Name', 'Parent']): array {
    $mapping = array_map(fn ($column) => ['source' => $column, 'target' => strtolower($column)], $header);
    $this->importCsvFile($this->createCsvFile([$header, ...$rows]), $mapping, 'taxonomy_term', 'location');

    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $storage->resetCache();
    $parents = [];
    foreach ($storage->loadByProperties(['vid' => 'location']) as $term) {
      $parents[$term->getName()] = (string) $term->get('parent')->entity?->getName();
    }
    ksort($parents);
    return $parents;
  }

  /**
   * Parents are found whatever the row order, by name.
   */
  public function testParentsOnLaterRows(): void {
    $parents = $this->importRows([
      ['Hillyard', 'Spokane'],
      ['Spokane', 'Washington'],
      ['Washington', ''],
    ]);

    $this->assertSame(['Hillyard' => 'Spokane', 'Spokane' => 'Washington', 'Washington' => ''], $parents);
    $this->assertSame(0, $this->lastExecutable->getUnresolvedParentCount());
  }

  /**
   * Parents are found by UUID, including on later rows.
   */
  public function testParentsByUuid(): void {
    $washington = '6f1c1c2e-3b7a-4c55-9a7e-0f4b2f9a1d01';
    $spokane = '6f1c1c2e-3b7a-4c55-9a7e-0f4b2f9a1d02';
    $parents = $this->importRows([
      ['Spokane', $spokane, $washington],
      ['Washington', $washington, ''],
    ], ['Name', 'UUID', 'Parent']);

    $this->assertSame(['Spokane' => 'Washington', 'Washington' => ''], $parents);
  }

  /**
   * A parent that can't be found, or matches more than one term, is counted.
   *
   * The term is still imported, at the top level. A name only matches terms in
   * the vocabulary being imported.
   */
  public function testUnresolvedParentsAreCounted(): void {
    Term::create(['vid' => 'location', 'name' => 'Springfield'])->save();
    Term::create(['vid' => 'location', 'name' => 'Springfield'])->save();
    Vocabulary::create(['vid' => 'other', 'name' => 'Other'])->save();
    Term::create(['vid' => 'other', 'name' => 'Oregon'])->save();

    $parents = $this->importRows([
      ['Main Street', 'Springfield'],
      ['Portland', 'Oregon'],
      ['Spokane', 'Nowhere'],
    ]);

    $this->assertSame('', $parents['Main Street']);
    $this->assertSame('', $parents['Portland']);
    $this->assertSame('', $parents['Spokane']);
    $this->assertSame(3, $this->lastExecutable->getUnresolvedParentCount());
  }

  /**
   * A parent that's the term itself is never set.
   */
  public function testSelfParentIsCounted(): void {
    $parents = $this->importRows([['Spokane', 'Spokane']]);

    $this->assertSame(['Spokane' => ''], $parents);
    $this->assertSame(1, $this->lastExecutable->getUnresolvedParentCount());
  }

}
