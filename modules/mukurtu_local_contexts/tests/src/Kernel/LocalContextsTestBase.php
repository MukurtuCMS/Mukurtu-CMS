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
      'mukurtu_local_contexts_label_translations',
      'mukurtu_local_contexts_notices',
      'mukurtu_local_contexts_notice_translations',
      'mukurtu_local_contexts_purge_log',
    ]);
    // Note: the 'queue' table is deliberately not installed here - core's
    // DatabaseQueue creates it lazily on first use (ensureTableExists()),
    // it no longer has a hook_schema() entry to install.
    $this->installSchema('system', ['sequences']);

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
   * @param string $status
   *   The project's sync status. One of the LocalContextsProject::STATUS_*
   *   constants. Defaults to active.
   * @param bool $archived
   *   Whether the project is archived on the hub.
   * @param int|null $not_found_since
   *   Timestamp when the current unbroken run of not_found results began,
   *   or NULL if not currently in such a run.
   * @param int $not_found_count
   *   Count of consecutive not_found sync attempts.
   */
  protected function seedSiteProject(string $id, string $title = 'Project', string $status = 'active', bool $archived = FALSE, ?int $not_found_since = NULL, int $not_found_count = 0): void {
    $db = $this->container->get('database');
    $db->insert('mukurtu_local_contexts_projects')
      ->fields([
        'id' => $id,
        'provider_id' => $id,
        'title' => $title,
        'privacy' => 'public',
        'updated' => 1,
        'status' => $status,
        'status_updated' => 1,
        'archived' => (int) $archived,
        'not_found_since' => $not_found_since,
        'not_found_count' => $not_found_count,
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
    $this->container->get('database')->insert('mukurtu_local_contexts_notice_translations')
      ->fields([
        'project_id' => $projectId,
        'type' => $type,
        'locale' => 'fr',
        'language' => 'French',
        'name' => $name . ' (fr)',
        'text' => '',
        'updated' => 1,
      ])
      ->execute();
  }

}
