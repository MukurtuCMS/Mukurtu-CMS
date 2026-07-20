<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\mukurtu_local_contexts\Batch\LegacyProjectRemapBatch;
use Drupal\node\Entity\Node;

/**
 * Tests LegacyProjectRemapBatch::run().
 *
 * @group mukurtu_local_contexts
 */
class LegacyProjectRemapBatchTest extends LocalContextsTestBase {

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
   * A fresh batch context, as the form's first call would provide.
   */
  protected function newContext(): array {
    return ['sandbox' => [], 'results' => []];
  }

  /**
   * A straight whole-project field swap.
   */
  public function testProjectFieldSwap() {
    $this->seedSiteProject('legacy_1', 'Legacy One');
    $node = $this->createTestNode();
    $node->set('field_local_contexts_projects', ['legacy_1']);
    $node->save();

    $context = $this->newContext();
    LegacyProjectRemapBatch::run('legacy_1', 'target_1', [], $context);

    $this->assertEquals(1.0, $context['finished']);
    $this->assertEquals(1, $context['results']['rewritten']);

    $reloaded = Node::load($node->id());
    $this->assertEquals(['target_1'], array_column($reloaded->get('field_local_contexts_projects')->getValue(), 'value'));
  }

  /**
   * A mapped label is rewritten to its target project + target label.
   */
  public function testMappedLabelSwap() {
    $this->seedSiteProject('legacy_2', 'Legacy Two');
    $this->seedLabel('legacy_label_a', 'legacy_2', 'Legacy Label A');
    $node = $this->createTestNode();
    $node->set('field_local_contexts_labels_and_notices', ['legacy_2:legacy_label_a:label']);
    $node->save();

    $context = $this->newContext();
    LegacyProjectRemapBatch::run('legacy_2', 'target_2', ['legacy_label_a' => 'real_label_a'], $context);

    $this->assertEquals(1, $context['results']['rewritten']);
    $reloaded = Node::load($node->id());
    $this->assertEquals(['target_2:real_label_a:label'], array_column($reloaded->get('field_local_contexts_labels_and_notices')->getValue(), 'value'));
  }

  /**
   * A node referenced only via an unmapped label is left untouched and
   * isn't even queued for processing.
   */
  public function testUnmappedLabelLeftUntouched() {
    $this->seedSiteProject('legacy_3', 'Legacy Three');
    $this->seedLabel('legacy_label_b', 'legacy_3', 'Legacy Label B');
    $node = $this->createTestNode();
    $node->set('field_local_contexts_labels_and_notices', ['legacy_3:legacy_label_b:label']);
    $node->save();

    $context = $this->newContext();
    LegacyProjectRemapBatch::run('legacy_3', 'target_3', [], $context);

    $this->assertEquals(0, $context['results']['rewritten']);
    $this->assertEquals(0, $context['sandbox']['max']);

    $reloaded = Node::load($node->id());
    $this->assertEquals(['legacy_3:legacy_label_b:label'], array_column($reloaded->get('field_local_contexts_labels_and_notices')->getValue(), 'value'));
  }

  /**
   * Notices are rewritten the same way labels are.
   */
  public function testMappedNoticeSwap() {
    $this->seedSiteProject('legacy_4', 'Legacy Four');
    $this->seedNotice('legacy_notice_a', 'legacy_4', 'Legacy Notice A');
    $node = $this->createTestNode();
    $node->set('field_local_contexts_labels_and_notices', ['legacy_4:legacy_notice_a:notice']);
    $node->save();

    $context = $this->newContext();
    LegacyProjectRemapBatch::run('legacy_4', 'target_4', ['legacy_notice_a' => 'real_notice_a'], $context);

    $this->assertEquals(1, $context['results']['rewritten']);
    $reloaded = Node::load($node->id());
    $this->assertEquals(['target_4:real_notice_a:notice'], array_column($reloaded->get('field_local_contexts_labels_and_notices')->getValue(), 'value'));
  }

  /**
   * A node with one mapped and one unmapped value in the same multi-valued
   * field - only the mapped one changes, and the node is saved once.
   */
  public function testMixedMappedAndUnmappedOnSameNode() {
    $this->seedSiteProject('legacy_5', 'Legacy Five');
    $this->seedLabel('mapped_label', 'legacy_5', 'Mapped Label');
    $this->seedLabel('unmapped_label', 'legacy_5', 'Unmapped Label');
    $node = $this->createTestNode();
    $node->set('field_local_contexts_labels_and_notices', [
      'legacy_5:mapped_label:label',
      'legacy_5:unmapped_label:label',
    ]);
    $node->save();

    $context = $this->newContext();
    LegacyProjectRemapBatch::run('legacy_5', 'target_5', ['mapped_label' => 'real_label'], $context);

    $this->assertEquals(1, $context['results']['rewritten']);
    $this->assertEquals(1, $context['results']['skipped_by_id']['unmapped_label']);

    $reloaded = Node::load($node->id());
    $values = array_column($reloaded->get('field_local_contexts_labels_and_notices')->getValue(), 'value');
    $this->assertContains('target_5:real_label:label', $values);
    $this->assertContains('legacy_5:unmapped_label:label', $values);
  }

  /**
   * Multi-pass chunking: more nodes than one chunk (BATCH_SIZE = 25) are
   * all processed exactly once, across multiple calls.
   */
  public function testMultiPassChunking() {
    $this->seedSiteProject('legacy_6', 'Legacy Six');
    $nodes = [];
    for ($i = 0; $i < 30; $i++) {
      $node = $this->createTestNode();
      $node->set('field_local_contexts_projects', ['legacy_6']);
      $node->save();
      $nodes[] = $node;
    }

    $context = $this->newContext();
    $passes = 0;
    do {
      LegacyProjectRemapBatch::run('legacy_6', 'target_6', [], $context);
      $passes++;
    } while ($context['finished'] < 1.0 && $passes < 10);

    $this->assertGreaterThan(1, $passes);
    $this->assertEquals(1.0, $context['finished']);
    $this->assertEquals(30, $context['results']['rewritten']);

    foreach ($nodes as $node) {
      $reloaded = Node::load($node->id());
      $this->assertEquals(['target_6'], array_column($reloaded->get('field_local_contexts_projects')->getValue(), 'value'));
    }
  }

  /**
   * A legacy project can be remapped across multiple passes as more labels
   * get mapped over time - later passes must still find and rewrite
   * remaining legacy-referencing values, anchored on the legacy project ID
   * within the compound value (not on the whole-project field, which was
   * never set on this node).
   */
  public function testIncrementalPartialRemap() {
    $this->seedSiteProject('legacy_7', 'Legacy Seven');
    $this->seedLabel('label_x', 'legacy_7', 'Label X');
    $this->seedLabel('label_y', 'legacy_7', 'Label Y');
    $node = $this->createTestNode();
    $node->set('field_local_contexts_labels_and_notices', [
      'legacy_7:label_x:label',
      'legacy_7:label_y:label',
    ]);
    $node->save();

    // Pass 1: only map label_x.
    $context = $this->newContext();
    LegacyProjectRemapBatch::run('legacy_7', 'target_7', ['label_x' => 'real_x'], $context);
    $this->assertEquals(1, $context['results']['rewritten']);

    $reloaded = Node::load($node->id());
    $values = array_column($reloaded->get('field_local_contexts_labels_and_notices')->getValue(), 'value');
    $this->assertContains('target_7:real_x:label', $values);
    $this->assertContains('legacy_7:label_y:label', $values);

    // Pass 2: now map label_y too.
    $context = $this->newContext();
    LegacyProjectRemapBatch::run('legacy_7', 'target_7', ['label_y' => 'real_y'], $context);
    $this->assertEquals(1, $context['results']['rewritten']);

    $reloaded = Node::load($node->id());
    $values = array_column($reloaded->get('field_local_contexts_labels_and_notices')->getValue(), 'value');
    $this->assertContains('target_7:real_x:label', $values);
    $this->assertContains('target_7:real_y:label', $values);
  }

  /**
   * A node ID that fails to load is skipped gracefully, without being
   * counted as rewritten or aborting the rest of the batch.
   */
  public function testMissingNodeIsSkippedGracefully() {
    $context = [
      'sandbox' => ['nids' => [999999], 'max' => 1, 'progress' => 0],
      'results' => [
        'legacy_project_id' => 'legacy_8',
        'rewritten' => 0,
        'errors' => 0,
        'error_log' => [],
        'skipped_by_id' => [],
      ],
    ];
    LegacyProjectRemapBatch::run('legacy_8', 'target_8', [], $context);

    $this->assertEquals(0, $context['results']['rewritten']);
    $this->assertEquals(0, $context['results']['errors']);
    $this->assertEquals(1.0, $context['finished']);
  }

}
