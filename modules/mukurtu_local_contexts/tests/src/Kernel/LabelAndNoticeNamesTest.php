<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

/**
 * Tests LocalContextsSupportedProjectManager::getLabelAndNoticeNames().
 *
 * Multiple Local Contexts projects can add the same standardized label or
 * notice (e.g. "TK Attribution"), each as its own project-scoped compound
 * key. Filters/facets rely on this lookup to merge those rows into a single
 * option/indexed value by display name.
 *
 * @group mukurtu_local_contexts
 */
class LabelAndNoticeNamesTest extends LocalContextsTestBase {

  const PROJECT_A = '4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a';
  const PROJECT_B = '5e8e8f2b-1c3c-5c2f-ad4b-2f3f4e5d6c7b';

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
   * Seed a notice directly in the DB, associated with a project.
   */
  protected function seedNotice(string $projectId, string $type, string $name = 'Notice'): void {
    $this->container->get('database')->insert('mukurtu_local_contexts_notices')
      ->fields([
        'project_id' => $projectId,
        'type' => $type,
        'name' => $name,
        'img_url' => '',
        'default_text' => '',
        'updated' => 1,
      ])
      ->execute();
  }

  /**
   * Two projects providing a same-named label resolve to the same name,
   * keyed by their own distinct compound keys.
   */
  public function testDuplicateLabelNameAcrossProjectsResolvesToSameName() {
    $this->seedSiteProject(static::PROJECT_A, 'Project A');
    $this->seedSiteProject(static::PROJECT_B, 'Project B');
    $this->seedLabel('label_1', static::PROJECT_A, 'TK Attribution');
    $this->seedLabel('label_2', static::PROJECT_B, 'TK Attribution');

    $names = $this->manager->getLabelAndNoticeNames();

    $keyA = static::PROJECT_A . ':label_1:label';
    $keyB = static::PROJECT_B . ':label_2:label';

    $this->assertArrayHasKey($keyA, $names);
    $this->assertArrayHasKey($keyB, $names);
    $this->assertSame('TK Attribution', $names[$keyA]);
    $this->assertSame('TK Attribution', $names[$keyB]);
  }

  /**
   * A notice resolves to its name via the "{project_id}:{type}:notice" key.
   */
  public function testNoticeResolvesToName() {
    $this->seedSiteProject(static::PROJECT_A, 'Project A');
    $this->seedNotice(static::PROJECT_A, 'attribution', 'TK Attribution Notice');

    $names = $this->manager->getLabelAndNoticeNames();

    $this->assertSame('TK Attribution Notice', $names[static::PROJECT_A . ':attribution:notice']);
  }

}
