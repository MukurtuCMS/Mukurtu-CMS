<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\Tests\mukurtu_core\Kernel\MukurtuKernelTestBase;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Drupal\node\Entity\NodeType;

/**
 * Base class for Mukurtu Local Contexts kernel tests.
 */
abstract class LocalContextsTestBase extends MukurtuKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'filter',
    'image',
    'media',
    'node',
    'og',
    'options',
    'path',
    'path_alias',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
    'mukurtu_core',
    'mukurtu_local_contexts',
    'mukurtu_protocol',
    'mukurtu_local_contexts_test',
  ];

  /**
   * The node type used to attach the Local Contexts fields to for testing.
   */
  const TEST_BUNDLE = 'legacy_test_content';

  /**
   * The LocalContextsSupportedProjectManager service under test.
   *
   * @var \Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager
   */
  protected LocalContextsSupportedProjectManager $manager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);

    NodeType::create([
      'type' => static::TEST_BUNDLE,
      'name' => 'Legacy Test Content',
    ])->save();

    $this->manager = $this->container->get('mukurtu_local_contexts.supported_project_manager');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Insert a minimal project record so foreign key joins work in queries.
   *
   * LocalContextsSupportedProjectManager joins supported_projects → projects.
   * Tests that call getSiteSupportedProjects() / getAllProjects() etc. require
   * a matching row in mukurtu_local_contexts_projects.
   *
   * @param string $project_id
   *   The project UUID to insert.
   * @param string $title
   *   The project title.
   */
  protected function insertProjectRecord(string $project_id, string $title = 'Test Project'): void {
    \Drupal::database()->insert('mukurtu_local_contexts_projects')
      ->fields([
        'id' => $project_id,
        'provider_id' => NULL,
        'title' => $title,
        'privacy' => 'public',
        'updated' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();
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
   */
  protected function seedLabel(string $labelId, string $projectId, string $name = 'Label'): void {
    $this->container->get('database')->insert('mukurtu_local_contexts_labels')
      ->fields([
        'id' => $labelId,
        'project_id' => $projectId,
        'name' => $name,
        'type' => 'Attribution',
        'display' => 'label',
        'tk_or_bc' => 'TK',
        'img_url' => '',
        'community' => '',
        'default_text' => '',
        'updated' => 1,
      ])
      ->execute();
  }

}
