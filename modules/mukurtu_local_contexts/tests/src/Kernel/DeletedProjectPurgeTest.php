<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\node\Entity\Node;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests confirming a genuinely deleted Local Contexts Hub project (a
 * persistent not_found/404) triggers a purge of its local cache and any
 * content references, while unauthorized (401), forbidden (403), and
 * generic error results - no matter how long they persist - never do.
 */
#[Group('mukurtu_local_contexts')]
class DeletedProjectPurgeTest extends LocalContextsTestBase {

  /**
   * The Local Contexts supported project manager.
   *
   * @var \Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->manager = $this->container->get('mukurtu_local_contexts.supported_project_manager');

    // Use small, explicit thresholds so tests don't depend on the module's
    // (provisional) default values.
    $this->config('mukurtu_local_contexts.settings')
      ->set('deleted_project_grace_period', 100)
      ->set('deleted_project_min_consecutive_failures', 3)
      ->save();
  }

  /**
   * Creates a new, unsaved test node.
   */
  protected function createTestNode(): Node {
    return Node::create([
      'type' => static::TEST_BUNDLE,
      'title' => $this->randomString(),
    ]);
  }

  /**
   * Drains the reference cleanup queue, processing every item.
   */
  protected function drainReferenceCleanupQueue(): void {
    $queue = $this->container->get('queue')->get('mukurtu_local_contexts_reference_cleanup');
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance('mukurtu_local_contexts_reference_cleanup');
    while ($item = $queue->claimItem()) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
    }
  }

  /**
   * Not enough time has elapsed since the not_found streak began - the
   * project must not be treated as confirmed deleted, even though the
   * count threshold is met.
   */
  public function testGracePeriodTimeNotElapsedLeavesProjectAlone() {
    $now = \Drupal::time()->getRequestTime();
    $this->seedSiteProject('recent-not-found', 'Recent', LocalContextsProject::STATUS_NOT_FOUND, FALSE, $now, 5);

    $project = new LocalContextsProject('recent-not-found');
    $this->assertFalse($project->isConfirmedDeleted());

    $result = $this->manager->purgeDeletedProject('recent-not-found');
    $this->assertSame(['labels' => 0, 'notices' => 0, 'nodes_queued' => 0], $result);
    $this->assertNotFalse((new LocalContextsProject('recent-not-found'))->isValid());
  }

  /**
   * Enough time has elapsed, but not enough consecutive failed syncs - the
   * project must not be treated as confirmed deleted.
   */
  public function testGracePeriodCountNotMetLeavesProjectAlone() {
    $now = \Drupal::time()->getRequestTime();
    $this->seedSiteProject('old-not-found', 'Old', LocalContextsProject::STATUS_NOT_FOUND, FALSE, $now - 200, 1);

    $project = new LocalContextsProject('old-not-found');
    $this->assertFalse($project->isConfirmedDeleted());

    $result = $this->manager->purgeDeletedProject('old-not-found');
    $this->assertSame(['labels' => 0, 'notices' => 0, 'nodes_queued' => 0], $result);
    $this->assertNotFalse((new LocalContextsProject('old-not-found'))->isValid());
  }

  /**
   * Once both the elapsed-time and consecutive-count thresholds are met,
   * the project is confirmed deleted and purged.
   */
  public function testBothThresholdsMetConfirmsDeletionAndPurges() {
    $now = \Drupal::time()->getRequestTime();
    $this->seedSiteProject('confirmed-deleted', 'Confirmed Deleted', LocalContextsProject::STATUS_NOT_FOUND, FALSE, $now - 200, 3);

    $project = new LocalContextsProject('confirmed-deleted');
    $this->assertTrue($project->isConfirmedDeleted());

    $result = $this->manager->purgeDeletedProject('confirmed-deleted');
    $this->assertSame(['labels' => 0, 'notices' => 0, 'nodes_queued' => 0], $result);
    $this->assertFalse((new LocalContextsProject('confirmed-deleted'))->isValid());
  }

  /**
   * Unauthorized (401) must never be treated as confirmed deletion, no
   * matter how long it persists or how low the configured thresholds are -
   * the guard is on status, not just elapsed time/count.
   */
  public function testUnauthorizedNeverConfirmsRegardlessOfDuration() {
    $this->config('mukurtu_local_contexts.settings')
      ->set('deleted_project_grace_period', 0)
      ->set('deleted_project_min_consecutive_failures', 0)
      ->save();

    // Seed with a stale not_found streak left over from before the status
    // changed, to prove the guard checks status, not just the streak.
    $this->seedSiteProject('unauthorized-project', 'Unauthorized', LocalContextsProject::STATUS_UNAUTHORIZED, FALSE, 1, 999);

    $project = new LocalContextsProject('unauthorized-project');
    $this->assertFalse($project->isConfirmedDeleted());

    $result = $this->manager->purgeDeletedProject('unauthorized-project');
    $this->assertSame(['labels' => 0, 'notices' => 0, 'nodes_queued' => 0], $result);
    $this->assertNotFalse((new LocalContextsProject('unauthorized-project'))->isValid());
  }

  /**
   * Forbidden (403) must never be treated as confirmed deletion, no matter
   * how long it persists.
   */
  public function testForbiddenNeverConfirmsRegardlessOfDuration() {
    $this->config('mukurtu_local_contexts.settings')
      ->set('deleted_project_grace_period', 0)
      ->set('deleted_project_min_consecutive_failures', 0)
      ->save();

    $this->seedSiteProject('forbidden-project', 'Forbidden', LocalContextsProject::STATUS_FORBIDDEN, FALSE, 1, 999);

    $project = new LocalContextsProject('forbidden-project');
    $this->assertFalse($project->isConfirmedDeleted());
    $this->assertSame(['labels' => 0, 'notices' => 0, 'nodes_queued' => 0], $this->manager->purgeDeletedProject('forbidden-project'));
  }

  /**
   * A generic sync error must never be treated as confirmed deletion, no
   * matter how long it persists.
   */
  public function testGenericErrorNeverConfirmsRegardlessOfDuration() {
    $this->config('mukurtu_local_contexts.settings')
      ->set('deleted_project_grace_period', 0)
      ->set('deleted_project_min_consecutive_failures', 0)
      ->save();

    $this->seedSiteProject('error-project', 'Error', LocalContextsProject::STATUS_ERROR, FALSE, 1, 999);

    $project = new LocalContextsProject('error-project');
    $this->assertFalse($project->isConfirmedDeleted());
    $this->assertSame(['labels' => 0, 'notices' => 0, 'nodes_queued' => 0], $this->manager->purgeDeletedProject('error-project'));
  }

  /**
   * A successful sync (recovery) resets the not_found streak to
   * NULL/0, matching the contract LocalContextsProject::fetchFromHub()
   * writes to the database on its success branch - so a project that
   * recovers mid-streak does not carry a stale count into a future streak.
   */
  public function testSuccessfulSyncResetsNotFoundStreak() {
    $now = \Drupal::time()->getRequestTime();
    $this->seedSiteProject('recovered-project', 'Recovered', LocalContextsProject::STATUS_NOT_FOUND, FALSE, $now - 200, 3);

    // Simulate the database state a successful fetchFromHub() call would
    // write (see the success branch's reset of not_found_since/count).
    $this->container->get('database')->update('mukurtu_local_contexts_projects')
      ->condition('id', 'recovered-project')
      ->fields([
        'status' => LocalContextsProject::STATUS_ACTIVE,
        'not_found_since' => NULL,
        'not_found_count' => 0,
      ])
      ->execute();

    $project = new LocalContextsProject('recovered-project');
    $this->assertNull($project->getNotFoundSince());
    $this->assertSame(0, $project->getNotFoundCount());
    $this->assertFalse($project->isConfirmedDeleted());
  }

  /**
   * A purge removes the project, its labels, and its notices, and clears
   * the supported-project mapping for every scope (e.g. both a site and a
   * community can support the same shared project ID) - not just the
   * scope whose API key happened to detect the deletion.
   */
  public function testPurgeRemovesCacheRowsAndSupportedProjectMappingsAcrossScopes() {
    $now = \Drupal::time()->getRequestTime();
    $this->seedSiteProject('shared-project', 'Shared', LocalContextsProject::STATUS_NOT_FOUND, FALSE, $now - 200, 3);
    $this->seedLabel('shared-label', 'shared-project', 'Shared Label');
    $this->seedNotice('attribution-incomplete', 'shared-project', 'Shared Notice');

    // Add a second (community) scope supporting the same project.
    $this->container->get('database')->insert('mukurtu_local_contexts_supported_projects')
      ->fields(['project_id' => 'shared-project', 'type' => 'community', 'group_id' => 42])
      ->execute();

    $this->manager->purgeDeletedProject('shared-project');

    $db = $this->container->get('database');
    $this->assertFalse((new LocalContextsProject('shared-project'))->isValid());
    $this->assertSame(0, (int) $db->select('mukurtu_local_contexts_labels', 'l')->condition('project_id', 'shared-project')->countQuery()->execute()->fetchField());
    $this->assertSame(0, (int) $db->select('mukurtu_local_contexts_notices', 'n')->condition('project_id', 'shared-project')->countQuery()->execute()->fetchField());
    $this->assertSame(0, (int) $db->select('mukurtu_local_contexts_notice_translations', 't')->condition('project_id', 'shared-project')->countQuery()->execute()->fetchField());
    $this->assertSame(0, (int) $db->select('mukurtu_local_contexts_supported_projects', 'sp')->condition('project_id', 'shared-project')->countQuery()->execute()->fetchField());

    // A purge_log row should exist for BOTH scopes.
    $this->assertCount(1, $this->manager->getUndismissedPurgeNotices('site', 0));
    $this->assertCount(1, $this->manager->getUndismissedPurgeNotices('community', 42));
  }

  /**
   * Purging a project strips only its own reference from a node's project
   * field, leaving an unrelated, still-active project's reference intact.
   */
  public function testPurgeStripsProjectFieldReferenceFromNode() {
    $now = \Drupal::time()->getRequestTime();
    $this->seedSiteProject('deleted-project', 'Deleted', LocalContextsProject::STATUS_NOT_FOUND, FALSE, $now - 200, 3);
    $this->seedSiteProject('active-project', 'Active', LocalContextsProject::STATUS_ACTIVE);

    $node = $this->createTestNode();
    $node->set('field_local_contexts_projects', ['deleted-project', 'active-project']);
    $node->save();

    $this->manager->purgeDeletedProject('deleted-project');
    $this->drainReferenceCleanupQueue();

    $reloaded = $this->entityTypeManager->getStorage('node')->loadUnchanged($node->id());
    $this->assertEquals(['active-project'], array_column($reloaded->get('field_local_contexts_projects')->getValue(), 'value'));
  }

  /**
   * Purging a project strips only its own labels'/notices' compound
   * values from a node's label-and-notice field, leaving values from an
   * unrelated project intact.
   */
  public function testPurgeStripsLabelAndNoticeReferencesFromNode() {
    $now = \Drupal::time()->getRequestTime();
    $this->seedSiteProject('deleted-project', 'Deleted', LocalContextsProject::STATUS_NOT_FOUND, FALSE, $now - 200, 3);
    $this->seedLabel('deleted-label', 'deleted-project', 'Deleted Label');
    $this->seedNotice('attribution-incomplete', 'deleted-project', 'Deleted Notice');

    $this->seedSiteProject('active-project', 'Active', LocalContextsProject::STATUS_ACTIVE);
    $this->seedLabel('active-label', 'active-project', 'Active Label');

    $node = $this->createTestNode();
    $node->set('field_local_contexts_labels_and_notices', [
      'deleted-project:deleted-label:label',
      'deleted-project:attribution-incomplete:notice',
      'active-project:active-label:label',
    ]);
    $node->save();

    $this->manager->purgeDeletedProject('deleted-project');
    $this->drainReferenceCleanupQueue();

    $reloaded = $this->entityTypeManager->getStorage('node')->loadUnchanged($node->id());
    $this->assertEquals(
      ['active-project:active-label:label'],
      array_column($reloaded->get('field_local_contexts_labels_and_notices')->getValue(), 'value')
    );
  }

  /**
   * A node referencing the deleted project via both fields gets both
   * cleaned up, in a single save (no double revision).
   */
  public function testPurgeHandlesNodeReferencedByBothFieldsInOneSave() {
    $now = \Drupal::time()->getRequestTime();
    $this->seedSiteProject('deleted-project', 'Deleted', LocalContextsProject::STATUS_NOT_FOUND, FALSE, $now - 200, 3);
    $this->seedLabel('deleted-label', 'deleted-project', 'Deleted Label');

    $node = $this->createTestNode();
    $node->set('field_local_contexts_projects', ['deleted-project']);
    $node->set('field_local_contexts_labels_and_notices', ['deleted-project:deleted-label:label']);
    $node->save();
    $originalVid = $node->getRevisionId();

    $this->manager->purgeDeletedProject('deleted-project');
    $this->drainReferenceCleanupQueue();

    $reloaded = $this->entityTypeManager->getStorage('node')->loadUnchanged($node->id());
    $this->assertEquals([], $reloaded->get('field_local_contexts_projects')->getValue());
    $this->assertEquals([], $reloaded->get('field_local_contexts_labels_and_notices')->getValue());
    $this->assertNotEquals($originalVid, $reloaded->getRevisionId());
  }

  /**
   * Legacy (v3-migrated) synthetic TK Labels project IDs are never on the
   * real hub and will 404 forever - they must never be auto-purged, even
   * with the thresholds fully met.
   */
  public function testLegacyProjectIdNeverPurged() {
    $now = \Drupal::time()->getRequestTime();
    $this->seedSiteProject('comm_42_tk', 'Legacy TK', LocalContextsProject::STATUS_NOT_FOUND, FALSE, $now - 200, 3);

    $this->assertTrue($this->manager->isLegacyProjectId('comm_42_tk'));

    $result = $this->manager->purgeDeletedProject('comm_42_tk');
    $this->assertSame(['labels' => 0, 'notices' => 0, 'nodes_queued' => 0], $result);
    $this->assertNotFalse((new LocalContextsProject('comm_42_tk'))->isValid());
  }

  /**
   * Regression test for the removeProject() notice-deletion bug: its
   * delete conditions referenced nonexistent 'id'/'label_id' columns on
   * the notices/notice_translations tables (whose primary key is actually
   * project_id+type), silently leaving orphaned rows behind.
   */
  public function testRemoveProjectDeletesNoticesAndTranslations() {
    $this->seedSiteProject('project-with-notice', 'Has Notice');
    $this->seedNotice('attribution-incomplete', 'project-with-notice', 'A Notice');

    $db = $this->container->get('database');
    $this->assertSame(1, (int) $db->select('mukurtu_local_contexts_notices', 'n')->condition('project_id', 'project-with-notice')->countQuery()->execute()->fetchField());
    $this->assertSame(1, (int) $db->select('mukurtu_local_contexts_notice_translations', 't')->condition('project_id', 'project-with-notice')->countQuery()->execute()->fetchField());

    $this->manager->removeProject('project-with-notice', TRUE);

    $this->assertSame(0, (int) $db->select('mukurtu_local_contexts_notices', 'n')->condition('project_id', 'project-with-notice')->countQuery()->execute()->fetchField());
    $this->assertSame(0, (int) $db->select('mukurtu_local_contexts_notice_translations', 't')->condition('project_id', 'project-with-notice')->countQuery()->execute()->fetchField());
  }

  /**
   * The undismissed purge notice list can be dismissed, after which it no
   * longer appears.
   */
  public function testDismissPurgeNoticeRemovesItFromUndismissedList() {
    $now = \Drupal::time()->getRequestTime();
    $this->seedSiteProject('deleted-project', 'Deleted', LocalContextsProject::STATUS_NOT_FOUND, FALSE, $now - 200, 3);
    $this->manager->purgeDeletedProject('deleted-project');

    $notices = $this->manager->getUndismissedPurgeNotices('site', 0);
    $this->assertCount(1, $notices);

    $this->manager->dismissPurgeNotices(array_keys($notices));
    $this->assertCount(0, $this->manager->getUndismissedPurgeNotices('site', 0));
  }

}
