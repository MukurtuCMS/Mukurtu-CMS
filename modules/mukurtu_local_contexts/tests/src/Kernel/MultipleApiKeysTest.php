<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\og\Og;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests support for multiple Local Contexts Hub API keys per scope.
 */
#[Group('mukurtu_local_contexts')]
class MultipleApiKeysTest extends KernelTestBase {

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
    'system',
    'text',
    'user',
    'og',
    'mukurtu_protocol',
    'mukurtu_local_contexts',
  ];

  /**
   * The Local Contexts supported project manager.
   *
   * @var \Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager
   */
  protected $manager;

  /**
   * Test Community.
   *
   * @var \Drupal\mukurtu_protocol\Entity\CommunityInterface
   */
  protected $community;

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
    ]);

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    $owner = User::create(['name' => $this->randomString()]);
    $owner->save();

    $this->community = Community::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $owner->id(),
    ]);
    $this->community->save();

    $this->manager = $this->container->get('mukurtu_local_contexts.supported_project_manager');
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
   * A community can hold multiple API keys, added incrementally.
   */
  public function testGroupCanHaveMultipleApiKeys() {
    $this->assertSame([], $this->manager->getGroupApiKeys($this->community));

    $this->community->set('field_local_contexts_api_key', ['key-one']);
    $this->community->save();
    $this->assertSame(['key-one'], $this->manager->getGroupApiKeys($this->community));

    $this->community->set('field_local_contexts_api_key', ['key-one', 'key-two']);
    $this->community->save();
    $this->assertSame(['key-one', 'key-two'], $this->manager->getGroupApiKeys($this->community));
  }

  /**
   * Projects added to a group record which API key added them.
   */
  public function testAddGroupProjectRecordsApiKey() {
    $this->seedProject('project-a', 'Project A');
    $this->seedProject('project-b', 'Project B');

    $this->manager->addGroupProject($this->community, 'project-a', 'key-one');
    $this->manager->addGroupProject($this->community, 'project-b', 'key-two');

    $projects = $this->manager->getGroupSupportedProjects($this->community);
    $this->assertSame('key-one', $projects['project-a']['api_key']);
    $this->assertSame('key-two', $projects['project-b']['api_key']);
  }

  /**
   * getGroupProjectsByApiKey() finds only the projects added with that key,
   * and removing a project drops it out of that key's list. This backs the
   * "block removal of an in-use key" behavior in the management form.
   */
  public function testGetGroupProjectsByApiKey() {
    $this->seedProject('project-a', 'Project A');
    $this->seedProject('project-b', 'Project B');

    $this->manager->addGroupProject($this->community, 'project-a', 'key-one');
    $this->manager->addGroupProject($this->community, 'project-b', 'key-two');

    $this->assertSame(['project-a'], $this->manager->getGroupProjectsByApiKey($this->community, 'key-one'));
    $this->assertSame(['project-b'], $this->manager->getGroupProjectsByApiKey($this->community, 'key-two'));

    $this->manager->removeGroupProject($this->community, 'project-a');
    $this->assertSame([], $this->manager->getGroupProjectsByApiKey($this->community, 'key-one'));
  }

  /**
   * Site-wide API keys behave the same way as group keys.
   */
  public function testSiteCanHaveMultipleApiKeysAndTracksProjectsByKey() {
    $this->assertSame([], $this->manager->getSiteApiKeys());

    \Drupal::configFactory()->getEditable('mukurtu_local_contexts.settings')
      ->set('site_api_keys', ['site-key-one', 'site-key-two'])
      ->save();
    $this->assertSame(['site-key-one', 'site-key-two'], $this->manager->getSiteApiKeys());

    $this->seedProject('site-project', 'Site Project');
    $this->manager->addSiteProject('site-project', 'site-key-one');

    $this->assertSame(['site-project'], $this->manager->getSiteProjectsByApiKey('site-key-one'));
    $this->assertSame([], $this->manager->getSiteProjectsByApiKey('site-key-two'));

    $this->manager->removeSiteProject('site-project');
    $this->assertSame([], $this->manager->getSiteProjectsByApiKey('site-key-one'));
  }

}
