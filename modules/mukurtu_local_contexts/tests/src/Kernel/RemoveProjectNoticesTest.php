<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

/**
 * Tests that removeProject() correctly deletes a project's notices.
 *
 * mukurtu_local_contexts_notices and mukurtu_local_contexts_notice_translations
 * have a compound primary key (project_id, type) and no 'id'/'label_id'
 * column, but removeProject()'s notice-deletion queries were written as if
 * they did, so calling removeProject() on a project with notices would
 * fail with a database error referencing a nonexistent column.
 *
 * @group mukurtu_local_contexts
 */
class RemoveProjectNoticesTest extends LocalContextsTestBase {

  const PROJECT_ID = '4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a';

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
    $this->installSchema('mukurtu_local_contexts', ['mukurtu_local_contexts_notice_translations']);
  }

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

  protected function seedNoticeTranslation(string $projectId, string $type, string $locale, string $name): void {
    $this->container->get('database')->insert('mukurtu_local_contexts_notice_translations')
      ->fields([
        'project_id' => $projectId,
        'type' => $type,
        'locale' => $locale,
        'language' => $locale,
        'name' => $name,
        'text' => '',
        'updated' => 1,
      ])
      ->execute();
  }

  /**
   * removeProject() must delete a project's notices and notice
   * translations without erroring on a nonexistent column.
   */
  public function testRemoveProjectDeletesNoticesAndTranslations() {
    $this->seedSiteProject(static::PROJECT_ID, 'Project With Notices');
    $this->seedNotice(static::PROJECT_ID, 'attribution-incomplete', 'Attribution Incomplete');
    $this->seedNoticeTranslation(static::PROJECT_ID, 'attribution-incomplete', 'es', 'Atribucion Incompleta');

    $this->manager->removeProject(static::PROJECT_ID, TRUE);

    $db = $this->container->get('database');
    $remainingNotices = $db->select('mukurtu_local_contexts_notices', 'n')
      ->condition('n.project_id', static::PROJECT_ID)
      ->fields('n', ['type'])
      ->execute()
      ->fetchCol();
    $this->assertEmpty($remainingNotices);

    $remainingTranslations = $db->select('mukurtu_local_contexts_notice_translations', 't')
      ->condition('t.project_id', static::PROJECT_ID)
      ->fields('t', ['name'])
      ->execute()
      ->fetchCol();
    $this->assertEmpty($remainingTranslations);
  }

}
