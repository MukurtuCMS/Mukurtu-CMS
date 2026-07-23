<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\node\Entity\Node;
use Drupal\mukurtu_local_contexts\LocalContextsProject;

/**
 * Tests that unavailable (401/403/404) projects/labels are excluded from new
 * selections but preserved on content that already references them, while
 * archived projects remain fully selectable.
 *
 * @group mukurtu_local_contexts
 */
class ProjectAvailabilityExclusionTest extends LocalContextsTestBase {

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
   * A new entity should not be able to select an unavailable project, but
   * should be able to select an active one.
   */
  public function testNewEntityCannotSelectUnavailableProject() {
    $this->seedSiteProject('unauthorized-project', 'Unauthorized Project', LocalContextsProject::STATUS_UNAUTHORIZED);
    $this->seedSiteProject('forbidden-project', 'Forbidden Project', LocalContextsProject::STATUS_FORBIDDEN);
    $this->seedSiteProject('not-found-project', 'Not Found Project', LocalContextsProject::STATUS_NOT_FOUND);
    $this->seedSiteProject('active-project', 'Active Project', LocalContextsProject::STATUS_ACTIVE);

    $entity = $this->createTestNode();
    $item = $entity->get('field_local_contexts_projects')->appendItem();
    $options = $item->getSettableOptions();

    $this->assertArrayNotHasKey('unauthorized-project', $options);
    $this->assertArrayNotHasKey('forbidden-project', $options);
    $this->assertArrayNotHasKey('not-found-project', $options);
    $this->assertArrayHasKey('active-project', $options);
  }

  /**
   * A generic sync error should NOT block new selection - only the three
   * named statuses (unauthorized/forbidden/not_found) do.
   */
  public function testNewEntityCanSelectGenericErrorProject() {
    $this->seedSiteProject('error-project', 'Error Project', LocalContextsProject::STATUS_ERROR);

    $entity = $this->createTestNode();
    $item = $entity->get('field_local_contexts_projects')->appendItem();
    $options = $item->getSettableOptions();

    $this->assertArrayHasKey('error-project', $options);
  }

  /**
   * An archived project must remain selectable - archived is distinct from
   * unavailable.
   */
  public function testArchivedProjectRemainsSelectable() {
    $this->seedSiteProject('archived-project', 'Archived Project', LocalContextsProject::STATUS_ACTIVE, TRUE);

    $entity = $this->createTestNode();
    $item = $entity->get('field_local_contexts_projects')->appendItem();
    $options = $item->getSettableOptions();

    $this->assertArrayHasKey('archived-project', $options);

    $project = new LocalContextsProject('archived-project');
    $this->assertTrue($project->isArchived());
    $this->assertFalse($project->isNotAvailable());
  }

  /**
   * A new entity should not be able to select a label from an unavailable
   * project, but should be able to select one from an active project.
   */
  public function testNewEntityCannotSelectUnavailableLabel() {
    $this->seedSiteProject('unauthorized-project', 'Unauthorized Project', LocalContextsProject::STATUS_UNAUTHORIZED);
    $this->seedLabel('unauthorized_label', 'unauthorized-project', 'Unauthorized Label');
    $this->seedSiteProject('active-project', 'Active Project', LocalContextsProject::STATUS_ACTIVE);
    $this->seedLabel('active_label', 'active-project', 'Active Label');

    $entity = $this->createTestNode();
    $item = $entity->get('field_local_contexts_labels_and_notices')->appendItem();
    $options = $item->getSettableOptions();

    // Options are keyed by project title, containing project_id:label_id:display values.
    $allValues = array_merge(...array_values($options));
    $this->assertArrayNotHasKey('unauthorized-project:unauthorized_label:label', $allValues);
    $this->assertArrayHasKey('active-project:active_label:label', $allValues);
  }

  /**
   * An entity that already references an unavailable project must keep that
   * reference (and pass validation) after an unrelated resave, rather than
   * silently losing it, even though the project can no longer be newly
   * selected.
   */
  public function testResavingEntityPreservesExistingUnavailableProject() {
    $this->seedSiteProject('unauthorized-project', 'Unauthorized Project');

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_projects', ['unauthorized-project']);
    $entity->save();

    // Now the project's status flips to unauthorized (e.g. after the next
    // cron sync fails).
    $this->container->get('database')->update('mukurtu_local_contexts_projects')
      ->condition('id', 'unauthorized-project')
      ->fields(['status' => LocalContextsProject::STATUS_UNAUTHORIZED])
      ->execute();

    $reloaded = $this->entityTypeManager->getStorage('node')->loadUnchanged($entity->id());
    $violations = $reloaded->validate();
    $this->assertCount(0, $violations, 'Entity with an existing unavailable project reference should have no AllowedValues violations.');

    $reloaded->set('title', $this->randomString());
    $reloaded->save();

    $reloaded = $this->entityTypeManager->getStorage('node')->loadUnchanged($entity->id());
    $this->assertEquals(['unauthorized-project'], array_column($reloaded->get('field_local_contexts_projects')->getValue(), 'value'));
  }

  /**
   * A user must still be able to explicitly remove an already-set
   * unavailable project reference (the fix only blocks adding new ones).
   */
  public function testUserCanRemovePreExistingUnavailableProject() {
    $this->seedSiteProject('unauthorized-project', 'Unauthorized Project', LocalContextsProject::STATUS_UNAUTHORIZED);

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_projects', ['unauthorized-project']);
    $entity->save();

    $reloaded = $this->entityTypeManager->getStorage('node')->loadUnchanged($entity->id());
    $reloaded->set('field_local_contexts_projects', []);
    $reloaded->save();

    $reloaded = $this->entityTypeManager->getStorage('node')->loadUnchanged($entity->id());
    $this->assertEquals([], $reloaded->get('field_local_contexts_projects')->getValue());
  }

  /**
   * Unit-level checks of LocalContextsProject's status getters given seeded
   * rows, with no HTTP involved.
   */
  public function testProjectStatusGetters() {
    $this->seedSiteProject('unauthorized-project', 'Unauthorized Project', LocalContextsProject::STATUS_UNAUTHORIZED);
    $project = new LocalContextsProject('unauthorized-project');
    $this->assertEquals(LocalContextsProject::STATUS_UNAUTHORIZED, $project->getStatus());
    $this->assertTrue($project->isNotAvailable());
    $this->assertFalse($project->isArchived());

    $this->seedSiteProject('active-project', 'Active Project');
    $project = new LocalContextsProject('active-project');
    $this->assertEquals(LocalContextsProject::STATUS_ACTIVE, $project->getStatus());
    $this->assertFalse($project->isNotAvailable());
  }

}
