<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\mukurtu_local_contexts\LocalContextsProject;

/**
 * Tests that a Project resolves to the compound keys of its labels/notices.
 *
 * Selecting an entire Local Contexts Project on content implies all of that
 * project's labels/notices, so filters/facets built on
 * field_local_contexts_labels_and_notices need to match those compound keys
 * even when the label/notice itself was never individually applied.
 *
 * @group mukurtu_local_contexts
 */
class EffectiveLabelsTest extends LocalContextsTestBase {

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
   * getLabelAndNoticeKeys() must return a compound key for every TK/BC
   * label and notice belonging to the project, in the same
   * "{project_id}:{id}:{label|notice}" format used elsewhere.
   */
  public function testGetLabelAndNoticeKeys() {
    $this->seedSiteProject('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'Real Hub Project');
    $this->seedLabel('tk_label_1', '4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'TK Label One');
    $this->seedNotice('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'attribution-incomplete', 'Attribution Incomplete');

    $project = new LocalContextsProject('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a');
    $keys = $project->getLabelAndNoticeKeys();

    $this->assertContains('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a:tk_label_1:label', $keys);
    $this->assertContains('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a:attribution-incomplete:notice', $keys);
  }

  /**
   * A project with no labels/notices resolves to an empty array rather
   * than erroring.
   */
  public function testGetLabelAndNoticeKeysEmptyProject() {
    $this->seedSiteProject('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a', 'Real Hub Project');

    $project = new LocalContextsProject('4d7d7e1a-0b2b-4b1e-9c3a-1f2e3d4c5b6a');
    $this->assertSame([], $project->getLabelAndNoticeKeys());
  }

}
