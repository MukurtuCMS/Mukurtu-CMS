<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\node\Entity\Node;

/**
 * Tests that legacy (v3-migrated) TK Labels projects/labels are excluded
 * from new selections but preserved on content that already references them.
 *
 * @group mukurtu_local_contexts
 */
class LegacyProjectExclusionTest extends LocalContextsTestBase {

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
   * Tests isLegacyProjectId() classification.
   */
  public function testIsLegacyProjectId() {
    $this->assertTrue($this->manager->isLegacyProjectId('default_tk'));
    $this->assertTrue($this->manager->isLegacyProjectId('sitewide_tk'));
    $this->assertTrue($this->manager->isLegacyProjectId('comm_42_tk'));
    $this->assertTrue($this->manager->isLegacyProjectId('comm_0_tk'));

    $this->assertFalse($this->manager->isLegacyProjectId('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a'));
    $this->assertFalse($this->manager->isLegacyProjectId('default_tk_extra'));
    $this->assertFalse($this->manager->isLegacyProjectId('commtk'));
    $this->assertFalse($this->manager->isLegacyProjectId('comm__tk'));
    $this->assertFalse($this->manager->isLegacyProjectId('comm_42a_tk'));
    $this->assertFalse($this->manager->isLegacyProjectId(''));
  }

  /**
   * Regression test: getSiteSupportedProjects(TRUE) must exclude all three
   * legacy ID shapes, not just default_tk/sitewide_tk.
   */
  public function testGetSiteSupportedProjectsExcludeLegacyCoversAllPatterns() {
    $this->seedSiteProject('default_tk', 'Default Legacy');
    $this->seedSiteProject('sitewide_tk', 'Sitewide Legacy');
    $this->seedSiteProject('comm_5_tk', 'Community 5 Legacy');
    $this->seedSiteProject('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'Real Hub Project');

    $projects = $this->manager->getSiteSupportedProjects(TRUE);

    $this->assertArrayNotHasKey('default_tk', $projects);
    $this->assertArrayNotHasKey('sitewide_tk', $projects);
    $this->assertArrayNotHasKey('comm_5_tk', $projects);
    $this->assertArrayHasKey('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', $projects);
  }

  /**
   * A new entity should not be able to select a legacy project, but should
   * be able to select a real one.
   */
  public function testNewEntityCannotSelectLegacyProject() {
    $this->seedSiteProject('default_tk', 'Default Legacy');
    $this->seedSiteProject('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'Real Hub Project');

    $entity = $this->createTestNode();
    $item = $entity->get('field_local_contexts_projects')->appendItem();
    $options = $item->getSettableOptions();

    $this->assertArrayNotHasKey('default_tk', $options);
    $this->assertArrayHasKey('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', $options);
  }

  /**
   * A new entity should not be able to select a legacy label, but should be
   * able to select a real one.
   */
  public function testNewEntityCannotSelectLegacyLabel() {
    $this->seedSiteProject('default_tk', 'Default Legacy');
    $this->seedLabel('default_tk_label', 'default_tk', 'Legacy Label');
    $this->seedSiteProject('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'Real Hub Project');
    $this->seedLabel('real_label', '4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'Real Label');

    $entity = $this->createTestNode();
    $item = $entity->get('field_local_contexts_labels_and_notices')->appendItem();
    $options = $item->getSettableOptions();

    // Options are keyed by project title, containing project_id:label_id:display values.
    $allValues = array_merge(...array_values($options));
    $this->assertArrayNotHasKey('default_tk:default_tk_label:label', $allValues);
    $this->assertArrayHasKey('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a:real_label:label', $allValues);
  }

  /**
   * Core regression test: an entity that already references a legacy
   * project must keep that reference (and pass validation) after an
   * unrelated resave, rather than silently losing it.
   */
  public function testResavingEntityPreservesExistingLegacyProject() {
    $this->seedSiteProject('default_tk', 'Default Legacy');

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_projects', ['default_tk']);
    $entity->save();

    $violations = $entity->validate();
    $this->assertCount(0, $violations, 'Entity with an existing legacy project reference should have no AllowedValues violations.');

    /** @var \Drupal\node\NodeInterface $reloaded */
    $reloaded = $this->entityTypeManager->getStorage('node')->loadUnchanged($entity->id());
    $reloaded->set('title', $this->randomString());
    $reloaded->save();

    $reloaded = $this->entityTypeManager->getStorage('node')->loadUnchanged($entity->id());
    $this->assertEquals(['default_tk'], array_column($reloaded->get('field_local_contexts_projects')->getValue(), 'value'));
  }

  /**
   * Core regression test: same as above, for the label/notice field.
   */
  public function testResavingEntityPreservesExistingLegacyLabel() {
    $this->seedSiteProject('default_tk', 'Default Legacy');
    $this->seedLabel('default_tk_label', 'default_tk', 'Legacy Label');
    $value = 'default_tk:default_tk_label:label';

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_labels_and_notices', [$value]);
    $entity->save();

    $violations = $entity->validate();
    $this->assertCount(0, $violations, 'Entity with an existing legacy label reference should have no AllowedValues violations.');

    /** @var \Drupal\node\NodeInterface $reloaded */
    $reloaded = $this->entityTypeManager->getStorage('node')->loadUnchanged($entity->id());
    $reloaded->set('title', $this->randomString());
    $reloaded->save();

    $reloaded = $this->entityTypeManager->getStorage('node')->loadUnchanged($entity->id());
    $this->assertEquals([$value], array_column($reloaded->get('field_local_contexts_labels_and_notices')->getValue(), 'value'));
  }

  /**
   * A user must still be able to explicitly remove an already-set legacy
   * project reference (the fix only blocks adding new ones).
   */
  public function testUserCanRemovePreExistingLegacyProject() {
    $this->seedSiteProject('default_tk', 'Default Legacy');

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_projects', ['default_tk']);
    $entity->save();

    $reloaded = $this->entityTypeManager->getStorage('node')->loadUnchanged($entity->id());
    $reloaded->set('field_local_contexts_projects', []);
    $reloaded->save();

    $reloaded = $this->entityTypeManager->getStorage('node')->loadUnchanged($entity->id());
    $this->assertEquals([], $reloaded->get('field_local_contexts_projects')->getValue());
  }

  /**
   * getReferencedLegacyProjectIds() must stay empty until content actually
   * references a legacy project, then return exactly that ID.
   */
  public function testGetReferencedLegacyProjectIdsFromProjectField() {
    $this->seedSiteProject('default_tk', 'Default Legacy');
    $this->seedSiteProject('sitewide_tk', 'Sitewide Legacy');

    $this->assertSame([], $this->manager->getReferencedLegacyProjectIds());

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_projects', ['default_tk']);
    $entity->save();

    $this->assertEquals(['default_tk'], $this->manager->getReferencedLegacyProjectIds());
  }

  /**
   * Same as above, but the reference comes from the compound label/notice
   * field rather than the project field directly.
   */
  public function testGetReferencedLegacyProjectIdsFromLabelField() {
    $this->seedSiteProject('sitewide_tk', 'Sitewide Legacy');
    $this->seedLabel('sitewide_tk_label', 'sitewide_tk', 'Legacy Label');

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_labels_and_notices', ['sitewide_tk:sitewide_tk_label:label']);
    $entity->save();

    $this->assertEquals(['sitewide_tk'], $this->manager->getReferencedLegacyProjectIds());
  }

  /**
   * A referenced real (non-legacy) Hub project must never be reported back
   * as a "referenced legacy" ID.
   */
  public function testGetReferencedLegacyProjectIdsExcludesNonLegacyProjects() {
    $this->seedSiteProject('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'Real Hub Project');

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_projects', ['4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a']);
    $entity->save();

    $this->assertSame([], $this->manager->getReferencedLegacyProjectIds());
  }

}
