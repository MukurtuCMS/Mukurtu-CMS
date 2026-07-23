<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\node\Entity\Node;

/**
 * Tests LocalContextsProject::getReferencingNodeIds() and inUse().
 *
 * @group mukurtu_local_contexts
 */
class LegacyProjectReferencingNodeIdsTest extends LocalContextsTestBase {

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
   * A project referenced only via the whole-project field.
   */
  public function testProjectFieldOnly() {
    $this->seedSiteProject('proj_a', 'Project A');
    $node = $this->createTestNode();
    $node->set('field_local_contexts_projects', ['proj_a']);
    $node->save();

    $project = new LocalContextsProject('proj_a');
    $refs = $project->getReferencingNodeIds();

    $this->assertEquals([(int) $node->id()], $refs['project']);
    $this->assertEquals([], $refs['labels_and_notices']);
    $this->assertTrue($project->inUse());
  }

  /**
   * A project referenced only via an individual label.
   */
  public function testLabelOnly() {
    $this->seedSiteProject('proj_b', 'Project B');
    $this->seedLabel('label_1', 'proj_b', 'Label One');
    $node = $this->createTestNode();
    $node->set('field_local_contexts_labels_and_notices', ['proj_b:label_1:label']);
    $node->save();

    $project = new LocalContextsProject('proj_b');
    $refs = $project->getReferencingNodeIds();

    $this->assertEquals([], $refs['project']);
    $this->assertEquals(['label_1' => [(int) $node->id()]], $refs['labels_and_notices']);
    $this->assertTrue($project->inUse());
  }

  /**
   * A project referenced only via an individual notice.
   */
  public function testNoticeOnly() {
    $this->seedSiteProject('proj_c', 'Project C');
    $this->seedNotice('notice_type_1', 'proj_c', 'Notice One');
    $node = $this->createTestNode();
    $node->set('field_local_contexts_labels_and_notices', ['proj_c:notice_type_1:notice']);
    $node->save();

    $project = new LocalContextsProject('proj_c');
    $refs = $project->getReferencingNodeIds();

    $this->assertEquals([], $refs['project']);
    $this->assertEquals(['notice_type_1' => [(int) $node->id()]], $refs['labels_and_notices']);
    $this->assertTrue($project->inUse());
  }

  /**
   * Regression test for the `else if` bug in the original inUse(): a
   * project with both a label reference (on one node) and a notice
   * reference (on another node) must have BOTH detected, not just the
   * label.
   */
  public function testLabelAndNoticeBothDetected() {
    $this->seedSiteProject('proj_d', 'Project D');
    $this->seedLabel('label_2', 'proj_d', 'Label Two');
    $this->seedNotice('notice_type_2', 'proj_d', 'Notice Two');

    $labelNode = $this->createTestNode();
    $labelNode->set('field_local_contexts_labels_and_notices', ['proj_d:label_2:label']);
    $labelNode->save();

    $noticeNode = $this->createTestNode();
    $noticeNode->set('field_local_contexts_labels_and_notices', ['proj_d:notice_type_2:notice']);
    $noticeNode->save();

    $project = new LocalContextsProject('proj_d');
    $refs = $project->getReferencingNodeIds();

    $this->assertEquals([
      'label_2' => [(int) $labelNode->id()],
      'notice_type_2' => [(int) $noticeNode->id()],
    ], $refs['labels_and_notices']);
    $this->assertTrue($project->inUse());
  }

  /**
   * A project with no content referencing it at all.
   */
  public function testUnused() {
    $this->seedSiteProject('proj_e', 'Project E');
    $project = new LocalContextsProject('proj_e');
    $refs = $project->getReferencingNodeIds();

    $this->assertEquals([], $refs['project']);
    $this->assertEquals([], $refs['labels_and_notices']);
    $this->assertFalse($project->inUse());
  }

}
