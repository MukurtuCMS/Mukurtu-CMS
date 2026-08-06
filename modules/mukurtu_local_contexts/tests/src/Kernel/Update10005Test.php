<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\og\Og;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_local_contexts_update_10005().
 */
#[Group('mukurtu_local_contexts')]
class Update10005Test extends KernelTestBase {

  /**
   * Test Community.
   *
   * @var \Drupal\mukurtu_protocol\Entity\CommunityInterface
   */
  protected $community;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'media',
    'taxonomy',
    'content_moderation',
    'workflows',
    'options',
    'path_alias',
    'system',
    'text',
    'user',
    'og',
    'mukurtu_protocol',
    'mukurtu_local_contexts',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['og', 'mukurtu_local_contexts']);
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('mukurtu_local_contexts', [
      'mukurtu_local_contexts_supported_projects',
      'mukurtu_local_contexts_projects',
      'mukurtu_local_contexts_labels',
      'mukurtu_local_contexts_notices',
      'mukurtu_local_contexts_notice_translations',
      'mukurtu_local_contexts_api_key_labels',
    ]);

    Og::addGroup('community', 'community');

    $owner = User::create(['name' => $this->randomString()]);
    $owner->save();
    $this->community = Community::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $owner->id(),
    ]);
    $this->community->save();
  }

  /**
   * Seed a project directly in the DB so it can be referenced by ID.
   */
  protected function seedProject(string $id, string $title = 'Project'): void {
    $this->container->get('database')->merge('mukurtu_local_contexts_projects')
      ->key('id', $id)
      ->fields([
        'provider_id' => $id,
        'title' => $title,
        'privacy' => 'public',
        'updated' => 1,
      ])
      ->execute();
  }

  /**
   * Tests the hook backfills from the current storage locations.
   *
   * Simulates a site whose schema version was stuck at 10001 under the old
   * hook numbering: update_10002() already migrated the site-wide key into
   * the 'site_api_keys' list and the group key into the group entity's own
   * field storage, before update_10001() (renamed here to update_10005())
   * ever got a chance to run and add the api_key column.
   */
  public function testUpdateHookBackfillsFromCurrentStorage(): void {
    require_once __DIR__ . '/../../../mukurtu_local_contexts.install';

    $db = $this->container->get('database');
    $schema = $db->schema();
    $schema->dropField('mukurtu_local_contexts_supported_projects', 'api_key');

    $this->seedProject('site-project', 'Site Project');
    $db->insert('mukurtu_local_contexts_supported_projects')
      ->fields(['project_id' => 'site-project', 'type' => 'site', 'group_id' => 0])
      ->execute();
    \Drupal::configFactory()->getEditable('mukurtu_local_contexts.settings')
      ->set('site_api_keys', ['site-key-one'])
      ->save();

    $this->seedProject('group-project', 'Group Project');
    $db->insert('mukurtu_local_contexts_supported_projects')
      ->fields(['project_id' => 'group-project', 'type' => 'community', 'group_id' => $this->community->id()])
      ->execute();
    $this->community->set('field_local_contexts_api_key', ['group-key-one']);
    $this->community->save();

    mukurtu_local_contexts_update_10005();

    $this->assertTrue($schema->fieldExists('mukurtu_local_contexts_supported_projects', 'api_key'));

    $site_api_key = $db->select('mukurtu_local_contexts_supported_projects', 'sp')
      ->fields('sp', ['api_key'])
      ->condition('type', 'site')
      ->condition('group_id', 0)
      ->execute()
      ->fetchField();
    $this->assertEquals('site-key-one', $site_api_key);

    $group_api_key = $db->select('mukurtu_local_contexts_supported_projects', 'sp')
      ->fields('sp', ['api_key'])
      ->condition('type', 'community')
      ->condition('group_id', $this->community->id())
      ->execute()
      ->fetchField();
    $this->assertEquals('group-key-one', $group_api_key);
  }

}
