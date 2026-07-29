<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\mukurtu_local_contexts\LocalContextsLabel;

/**
 * Tests that a label id shared by two different projects is handled safely.
 *
 * Local Contexts label ids are not guaranteed unique across projects (the
 * labels table's own primary key is compound: ['id', 'project_id']), so any
 * code that keys on the bare label id alone will silently collide two
 * different projects' labels together.
 *
 * @group mukurtu_local_contexts
 */
class LabelIdCollisionTest extends LocalContextsTestBase {

  const PROJECT_A = '4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a';
  const PROJECT_B = '5e8e8f2b-1c3c-5c2f-ad4b-2f3f4e5d6c7b';
  const SHARED_LABEL_ID = 'shared_label';

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
    $this->installSchema('mukurtu_local_contexts', ['mukurtu_local_contexts_label_translations']);
  }

  /**
   * Seed a translation directly in the DB, associated with a label+project.
   */
  protected function seedLabelTranslation(string $labelId, string $projectId, string $locale, string $name): void {
    $this->container->get('database')->insert('mukurtu_local_contexts_label_translations')
      ->fields([
        'label_id' => $labelId,
        'project_id' => $projectId,
        'locale' => $locale,
        'language' => $locale,
        'name' => $name,
        'text' => '',
        'updated' => 1,
      ])
      ->execute();
  }

  protected function seedCollidingLabels(): void {
    $this->seedSiteProject(static::PROJECT_A, 'Project A');
    $this->seedSiteProject(static::PROJECT_B, 'Project B');
    $this->seedLabel(static::SHARED_LABEL_ID, static::PROJECT_A, 'Project A Label');
    $this->seedLabel(static::SHARED_LABEL_ID, static::PROJECT_B, 'Project B Label');
  }

  /**
   * getAllLabels()/getSiteLabels()/getUserLabels() must return both
   * projects' rows for a shared label id, not just one.
   */
  public function testGetLabelsMethodsReturnBothCollidingRows() {
    $this->seedCollidingLabels();

    foreach (['getAllLabels', 'getSiteLabels'] as $method) {
      $labels = $this->manager->{$method}();
      $projectIds = array_column($labels, 'project_id');
      $this->assertContains(static::PROJECT_A, $projectIds, "$method() dropped Project A's label.");
      $this->assertContains(static::PROJECT_B, $projectIds, "$method() dropped Project B's label.");

      $names = array_column($labels, 'name', 'project_id');
      $this->assertEquals('Project A Label', $names[static::PROJECT_A]);
      $this->assertEquals('Project B Label', $names[static::PROJECT_B]);
    }
  }

  /**
   * LocalContextsLabel::load() must only load translations belonging to
   * its own project, not a colliding label id from a different project.
   */
  public function testLabelTranslationsAreScopedByProject() {
    $this->seedCollidingLabels();
    $this->seedLabelTranslation(static::SHARED_LABEL_ID, static::PROJECT_A, 'es', 'Etiqueta A');
    $this->seedLabelTranslation(static::SHARED_LABEL_ID, static::PROJECT_B, 'es', 'Etiqueta B');

    $labelA = new LocalContextsLabel(static::PROJECT_A . ':' . static::SHARED_LABEL_ID . ':label');
    $translationNames = array_column($labelA->translations ?? [], 'name');

    $this->assertContains('Etiqueta A', $translationNames);
    $this->assertNotContains('Etiqueta B', $translationNames);
  }

  /**
   * Removing one project must not delete another project's same-ID label
   * or its translations.
   */
  public function testRemoveProjectDoesNotDeleteCollidingLabelFromOtherProject() {
    $this->seedCollidingLabels();
    $this->seedLabelTranslation(static::SHARED_LABEL_ID, static::PROJECT_A, 'es', 'Etiqueta A');
    $this->seedLabelTranslation(static::SHARED_LABEL_ID, static::PROJECT_B, 'es', 'Etiqueta B');

    $this->manager->removeProject(static::PROJECT_A, TRUE);

    $remainingLabels = $this->manager->getAllLabels();
    $projectIds = array_column($remainingLabels, 'project_id');
    $this->assertNotContains(static::PROJECT_A, $projectIds);
    $this->assertContains(static::PROJECT_B, $projectIds);

    $db = $this->container->get('database');
    $remainingTranslations = $db->select('mukurtu_local_contexts_label_translations', 't')
      ->condition('t.project_id', static::PROJECT_B)
      ->fields('t', ['name'])
      ->execute()
      ->fetchCol();
    $this->assertContains('Etiqueta B', $remainingTranslations);

    $deletedProjectTranslations = $db->select('mukurtu_local_contexts_label_translations', 't')
      ->condition('t.project_id', static::PROJECT_A)
      ->fields('t', ['name'])
      ->execute()
      ->fetchCol();
    $this->assertEmpty($deletedProjectTranslations);
  }

}
