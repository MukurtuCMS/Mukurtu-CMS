<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Base class for mukurtu_local_contexts kernel tests.
 */
abstract class LocalContextsTestBase extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'og',
    'options',
    'system',
    'user',
    'mukurtu_local_contexts',
    'mukurtu_local_contexts_test',
  ];

  /**
   * The node type used to attach the Local Contexts fields to for testing.
   */
  const TEST_BUNDLE = 'legacy_test_content';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('og_membership');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('mukurtu_local_contexts', [
      'mukurtu_local_contexts_supported_projects',
      'mukurtu_local_contexts_projects',
      'mukurtu_local_contexts_labels',
      'mukurtu_local_contexts_notices',
      'mukurtu_local_contexts_label_translations',
      'mukurtu_local_contexts_notice_translations',
    ]);

    NodeType::create([
      'type' => static::TEST_BUNDLE,
      'name' => 'Legacy Test Content',
    ])->save();
  }

  /**
   * Seed a project (and its site-supported association) directly in the DB.
   *
   * @param string $id
   *   The project ID.
   * @param string $title
   *   The project title.
   */
  protected function seedSiteProject(string $id, string $title = 'Project'): void {
    $db = $this->container->get('database');
    $db->insert('mukurtu_local_contexts_projects')
      ->fields([
        'id' => $id,
        'provider_id' => $id,
        'title' => $title,
        'privacy' => 'public',
        'updated' => 1,
      ])
      ->execute();
    $db->insert('mukurtu_local_contexts_supported_projects')
      ->fields([
        'project_id' => $id,
        'type' => 'site',
        'group_id' => 0,
      ])
      ->execute();
  }

  /**
   * Seed a label directly in the DB, associated with a project.
   *
   * @param string $labelId
   *   The label ID.
   * @param string $projectId
   *   The project ID the label belongs to.
   * @param string $name
   *   The label name.
   * @param string $tkOrBc
   *   Whether this is a 'TK' or 'BC' label.
   */
  protected function seedLabel(string $labelId, string $projectId, string $name = 'Label', string $tkOrBc = 'TK'): void {
    $this->container->get('database')->insert('mukurtu_local_contexts_labels')
      ->fields([
        'id' => $labelId,
        'project_id' => $projectId,
        'name' => $name,
        'type' => 'Attribution',
        'display' => 'label',
        'tk_or_bc' => $tkOrBc,
        'img_url' => '',
        'community' => '',
        'default_text' => '',
        'updated' => 1,
      ])
      ->execute();
  }

  /**
   * Seed a notice directly in the DB, associated with a project.
   *
   * @param string $type
   *   The notice type.
   * @param string $projectId
   *   The project ID the notice belongs to.
   * @param string $name
   *   The notice name.
   */
  protected function seedNotice(string $type, string $projectId, string $name = 'Notice'): void {
    $this->container->get('database')->insert('mukurtu_local_contexts_notices')
      ->fields([
        'project_id' => $projectId,
        'type' => $type,
        'name' => $name,
        'display' => 'notice',
        'img_url' => '',
        'default_text' => '',
        'updated' => 1,
      ])
      ->execute();
  }

}
