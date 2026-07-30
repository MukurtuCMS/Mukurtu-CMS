<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\node\Entity\Node;

/**
 * Tests LocalContextsSupportedProjectManager::getReferencedProjectIds() and
 * getReferencedLabelAndNoticeKeys(), which drive both the LC Project/Label
 * exposed filter dropdowns (only offer options actually used by content)
 * and the Project filter/facet's label-inheritance matching.
 *
 * @group mukurtu_local_contexts
 */
class ReferencedProjectsAndLabelsTest extends LocalContextsTestBase {

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
   * A synced project with no content referencing it must not be reported
   * as referenced.
   */
  public function testUnreferencedProjectExcluded() {
    $this->seedSiteProject('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'Unused Project');

    $this->assertSame([], $this->manager->getReferencedProjectIds());
  }

  /**
   * A project applied directly to content must be reported as referenced.
   */
  public function testReferencedProjectFromProjectField() {
    $this->seedSiteProject('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'Applied Project');

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_projects', ['4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a']);
    $entity->save();

    $this->assertEquals(['4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a'], $this->manager->getReferencedProjectIds());
  }

  /**
   * A project must also be reported as referenced when only one of its
   * individual labels/notices is applied, not the project itself.
   */
  public function testReferencedProjectFromLabelField() {
    $this->seedSiteProject('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'Label-only Project');
    $this->seedLabel('real_label', '4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'Real Label');

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_labels_and_notices', ['4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a:real_label:label']);
    $entity->save();

    $this->assertEquals(['4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a'], $this->manager->getReferencedProjectIds());
  }

  /**
   * getReferencedLabelAndNoticeKeys() must stay empty until content
   * actually references a label/notice, then return exactly that key.
   */
  public function testGetReferencedLabelAndNoticeKeysEmptyUntilReferenced() {
    $this->seedSiteProject('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'Project');
    $this->seedLabel('real_label', '4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'Real Label');

    $this->assertSame([], $this->manager->getReferencedLabelAndNoticeKeys());

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_labels_and_notices', ['4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a:real_label:label']);
    $entity->save();

    $this->assertEquals(['4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a:real_label:label'], $this->manager->getReferencedLabelAndNoticeKeys());
  }

  /**
   * A label belonging to an unreferenced project must not appear in
   * getReferencedLabelAndNoticeKeys() just because a *different* project
   * happens to be referenced.
   */
  public function testGetReferencedLabelAndNoticeKeysExcludesUnrelatedProject() {
    $this->seedSiteProject('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'Referenced Project');
    $this->seedSiteProject('9e8d7c6b-5a4b-3c2d-1e0f-abcdef123456', 'Unreferenced Project');
    $this->seedLabel('other_label', '9e8d7c6b-5a4b-3c2d-1e0f-abcdef123456', 'Other Label');

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_projects', ['4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a']);
    $entity->save();

    $this->assertSame([], $this->manager->getReferencedLabelAndNoticeKeys());
  }

}
