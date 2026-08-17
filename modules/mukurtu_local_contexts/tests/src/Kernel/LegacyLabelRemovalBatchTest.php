<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\mukurtu_local_contexts\Batch\LegacyLabelRemovalBatch;
use Drupal\node\Entity\Node;

/**
 * Tests LegacyLabelRemovalBatch::run().
 *
 * @group mukurtu_local_contexts
 */
class LegacyLabelRemovalBatchTest extends LocalContextsTestBase {

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
   * A fresh batch context.
   */
  protected function newContext(): array {
    return ['sandbox' => [], 'results' => []];
  }

  /**
   * Removing a selected node's matching value leaves an unselected node
   * with the same label reference untouched - the core "not a blanket
   * action" guarantee.
   */
  public function testOnlySelectedNodeIsChanged() {
    $this->seedSiteProject('legacy_1', 'Legacy One');
    $this->seedLabel('label_1', 'legacy_1', 'Label One');

    $selected = $this->createTestNode();
    $selected->set('field_local_contexts_labels_and_notices', ['legacy_1:label_1:label']);
    $selected->save();

    $notSelected = $this->createTestNode();
    $notSelected->set('field_local_contexts_labels_and_notices', ['legacy_1:label_1:label']);
    $notSelected->save();

    $context = $this->newContext();
    LegacyLabelRemovalBatch::run('legacy_1', 'label', 'label_1', [(int) $selected->id()], $context);

    $this->assertEquals(1, $context['results']['removed']);

    $reloadedSelected = Node::load($selected->id());
    $this->assertEquals([], $reloadedSelected->get('field_local_contexts_labels_and_notices')->getValue());

    $reloadedNotSelected = Node::load($notSelected->id());
    $this->assertEquals(
      ['legacy_1:label_1:label'],
      array_column($reloadedNotSelected->get('field_local_contexts_labels_and_notices')->getValue(), 'value')
    );
  }

  /**
   * A node with two different label/notice references only has the
   * targeted one removed.
   */
  public function testOtherReferencesOnSameNodePreserved() {
    $this->seedSiteProject('legacy_2', 'Legacy Two');
    $this->seedLabel('label_a', 'legacy_2', 'Label A');
    $this->seedLabel('label_b', 'legacy_2', 'Label B');

    $node = $this->createTestNode();
    $node->set('field_local_contexts_labels_and_notices', [
      'legacy_2:label_a:label',
      'legacy_2:label_b:label',
    ]);
    $node->save();

    $context = $this->newContext();
    LegacyLabelRemovalBatch::run('legacy_2', 'label', 'label_a', [(int) $node->id()], $context);

    $this->assertEquals(1, $context['results']['removed']);
    $reloaded = Node::load($node->id());
    $this->assertEquals(
      ['legacy_2:label_b:label'],
      array_column($reloaded->get('field_local_contexts_labels_and_notices')->getValue(), 'value')
    );
  }

  /**
   * A node whose value was already removed before the batch runs is
   * counted as skipped, not touched.
   */
  public function testAlreadyRemovedValueIsSkipped() {
    $this->seedSiteProject('legacy_3', 'Legacy Three');
    $node = $this->createTestNode();
    $node->save();

    $context = $this->newContext();
    LegacyLabelRemovalBatch::run('legacy_3', 'label', 'label_1', [(int) $node->id()], $context);

    $this->assertEquals(0, $context['results']['removed']);
    $this->assertEquals(1, $context['results']['skipped']);
  }

  /**
   * Notice removal mirrors label removal.
   */
  public function testNoticeRemoval() {
    $this->seedSiteProject('legacy_4', 'Legacy Four');
    $this->seedNotice('notice_1', 'legacy_4', 'Notice One');

    $node = $this->createTestNode();
    $node->set('field_local_contexts_labels_and_notices', ['legacy_4:notice_1:notice']);
    $node->save();

    $context = $this->newContext();
    LegacyLabelRemovalBatch::run('legacy_4', 'notice', 'notice_1', [(int) $node->id()], $context);

    $this->assertEquals(1, $context['results']['removed']);
    $reloaded = Node::load($node->id());
    $this->assertEquals([], $reloaded->get('field_local_contexts_labels_and_notices')->getValue());
  }

  /**
   * Multi-pass chunking: more nodes than one chunk (BATCH_SIZE = 25) are
   * all processed exactly once.
   */
  public function testMultiPassChunking() {
    $this->seedSiteProject('legacy_5', 'Legacy Five');
    $this->seedLabel('label_1', 'legacy_5', 'Label One');

    $nids = [];
    for ($i = 0; $i < 30; $i++) {
      $node = $this->createTestNode();
      $node->set('field_local_contexts_labels_and_notices', ['legacy_5:label_1:label']);
      $node->save();
      $nids[] = (int) $node->id();
    }

    $context = $this->newContext();
    $passes = 0;
    do {
      LegacyLabelRemovalBatch::run('legacy_5', 'label', 'label_1', $nids, $context);
      $passes++;
    } while ($context['finished'] < 1.0 && $passes < 10);

    $this->assertGreaterThan(1, $passes);
    $this->assertEquals(1.0, $context['finished']);
    $this->assertEquals(30, $context['results']['removed']);

    foreach ($nids as $nid) {
      $reloaded = Node::load($nid);
      $this->assertEquals([], $reloaded->get('field_local_contexts_labels_and_notices')->getValue());
    }
  }

  /**
   * A node ID that fails to load is skipped gracefully.
   */
  public function testMissingNodeIsSkippedGracefully() {
    $context = [
      'sandbox' => ['nids' => [999999], 'max' => 1, 'progress' => 0],
      'results' => [
        'project_id' => 'legacy_6',
        'ref_type' => 'label',
        'ref_id' => 'label_1',
        'removed' => 0,
        'skipped' => 0,
        'errors' => 0,
        'error_log' => [],
      ],
    ];
    LegacyLabelRemovalBatch::run('legacy_6', 'label', 'label_1', [], $context);

    $this->assertEquals(0, $context['results']['removed']);
    $this->assertEquals(0, $context['results']['errors']);
    $this->assertEquals(1.0, $context['finished']);
  }

}
