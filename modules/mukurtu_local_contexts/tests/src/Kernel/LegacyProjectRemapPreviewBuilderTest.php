<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\mukurtu_local_contexts\LegacyProjectRemapPreviewBuilder;
use Drupal\node\Entity\Node;

/**
 * Tests LegacyProjectRemapPreviewBuilder::build().
 *
 * @group mukurtu_local_contexts
 */
class LegacyProjectRemapPreviewBuilderTest extends LocalContextsTestBase {

  /**
   * Creates and saves a new test node.
   */
  protected function createTestNode(): Node {
    $node = Node::create([
      'type' => static::TEST_BUNDLE,
      'title' => $this->randomString(),
    ]);
    $node->save();
    return $node;
  }

  /**
   * Rows are correctly classified as mapped vs. unmapped.
   */
  public function testMappedAndUnmappedClassification() {
    $this->seedSiteProject('legacy_p', 'Legacy P');
    $this->seedLabel('mapped_label', 'legacy_p', 'Mapped Label');
    $this->seedLabel('unmapped_label', 'legacy_p', 'Unmapped Label');

    $mappedNode = $this->createTestNode();
    $mappedNode->set('field_local_contexts_labels_and_notices', ['legacy_p:mapped_label:label']);
    $mappedNode->save();

    $unmappedNode = $this->createTestNode();
    $unmappedNode->set('field_local_contexts_labels_and_notices', ['legacy_p:unmapped_label:label']);
    $unmappedNode->save();

    $builder = new LegacyProjectRemapPreviewBuilder();
    $preview = $builder->build('legacy_p', 'target_p', ['mapped_label' => 'real_label']);

    $rowsById = [];
    foreach ($preview['rows'] as $row) {
      $rowsById[$row['id']] = $row;
    }

    $this->assertTrue($rowsById['mapped_label']['mapped']);
    $this->assertFalse($rowsById['unmapped_label']['mapped']);
    $this->assertEquals(1, $preview['total']);
  }

  /**
   * A node with both a whole-project reference and a mapped label
   * reference must count once in the headline total, not twice, even
   * though it appears in two rows.
   */
  public function testHeadlineTotalIsDeduplicated() {
    $this->seedSiteProject('legacy_q', 'Legacy Q');
    $this->seedLabel('mapped_label_q', 'legacy_q', 'Mapped Label Q');

    $node = $this->createTestNode();
    $node->set('field_local_contexts_projects', ['legacy_q']);
    $node->set('field_local_contexts_labels_and_notices', ['legacy_q:mapped_label_q:label']);
    $node->save();

    $builder = new LegacyProjectRemapPreviewBuilder();
    $preview = $builder->build('legacy_q', 'target_q', ['mapped_label_q' => 'real_label_q']);

    $rowCountSum = array_sum(array_map(fn ($row) => count($row['nids']), $preview['rows']));
    $this->assertEquals(2, $rowCountSum);
    $this->assertEquals(1, $preview['total']);
  }

  /**
   * A project with no referencing content produces an empty preview.
   */
  public function testNoReferences() {
    $this->seedSiteProject('legacy_r', 'Legacy R');
    $builder = new LegacyProjectRemapPreviewBuilder();
    $preview = $builder->build('legacy_r', 'target_r', []);

    $this->assertEquals([], $preview['rows']);
    $this->assertEquals(0, $preview['total']);
  }

}
