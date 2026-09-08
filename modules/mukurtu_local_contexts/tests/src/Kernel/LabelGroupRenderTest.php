<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\node\Entity\Node;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the LC project and label/notice formatters both render through
 * the shared 'local_contexts_label_group' theme, now that the separate
 * 'local_contexts_project' theme has been consolidated away.
 */
#[Group('mukurtu_local_contexts')]
class LabelGroupRenderTest extends LocalContextsTestBase {

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
   * LocalContextsProjectFormatter must build a single label-group render
   * array containing one themed item per label/notice, with the group-level
   * link pointing at the project's hub page.
   */
  public function testProjectFormatterRendersLabelGroup() {
    $this->seedSiteProject('project-a', 'Project A');
    $this->seedLabel('tk-1', 'project-a', 'TK Label', 'TK');
    $this->seedLabel('bc-1', 'project-a', 'BC Label', 'BC');
    $this->seedNotice('attribution-incomplete', 'project-a', 'Notice');

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_projects', ['project-a']);
    $entity->save();

    $build = $entity->get('field_local_contexts_projects')->view(['type' => 'local_contexts_project']);

    $this->assertEquals('local_contexts_label_group', $build[0]['#theme']);
    $this->assertEquals('Project A', $build[0]['#project_title']);
    $this->assertEquals(LocalContextsProject::buildUrl('project-a'), $build[0]['#project_url']);
    $this->assertCount(3, $build[0]['#items']);

    $themes = array_column($build[0]['#items'], '#theme');
    sort($themes);
    $this->assertEquals(['local_contexts_label', 'local_contexts_label', 'local_contexts_notice'], $themes);

    foreach ($build[0]['#items'] as $item) {
      $this->assertEquals('Project A', $item['#project_title']);
      $this->assertEquals(LocalContextsProject::buildUrl('project-a'), $item['#project_url']);
    }
  }

  /**
   * LocalContextsLabelFormatter must group individually-selected labels and
   * notices by project into one label-group render array per project, also
   * setting the group-level project link.
   */
  public function testLabelFormatterGroupsByProject() {
    $this->seedSiteProject('project-b', 'Project B');
    $this->seedLabel('tk-2', 'project-b', 'TK Label 2', 'TK');
    $this->seedNotice('attribution-incomplete', 'project-b', 'Notice 2');

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_labels_and_notices', [
      'project-b:tk-2:label',
      'project-b:attribution-incomplete:notice',
    ]);
    $entity->save();

    $build = $entity->get('field_local_contexts_labels_and_notices')->view(['type' => 'local_contexts_label_and_notice']);

    $this->assertEquals('local_contexts_label_group', $build[0]['#theme']);
    $this->assertEquals('Project B', $build[0]['#project_title']);
    $this->assertEquals(LocalContextsProject::buildUrl('project-b'), $build[0]['#project_url']);
    $this->assertCount(2, $build[0]['#items']);
  }

}
