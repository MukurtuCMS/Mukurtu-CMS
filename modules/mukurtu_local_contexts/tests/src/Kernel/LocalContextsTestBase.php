<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\Tests\mukurtu_core\Kernel\MukurtuKernelTestBase;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;

/**
 * Base class for Mukurtu Local Contexts kernel tests.
 */
abstract class LocalContextsTestBase extends MukurtuKernelTestBase {

  /**
   * The LocalContextsSupportedProjectManager service under test.
   *
   * @var \Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager
   */
  protected LocalContextsSupportedProjectManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->manager = $this->container->get('mukurtu_local_contexts.supported_project_manager');
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

}
