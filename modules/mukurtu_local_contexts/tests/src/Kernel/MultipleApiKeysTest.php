<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\og\Og;
use Drupal\user\Entity\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
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
      'mukurtu_local_contexts_notice_translations',
      'mukurtu_local_contexts_api_key_labels',
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

  /**
   * Projects with no recorded API key (added before per-project key
   * tracking existed, or legacy migrated projects) aren't attributed to
   * any specific key, so they're surfaced separately from
   * getGroupProjectsByApiKey(). This backs the "block removal of an
   * in-use key" check for keys that can't see these projects directly.
   */
  public function testGetGroupProjectsWithoutApiKey() {
    $this->seedProject('project-a', 'Project A');
    $this->seedProject('legacy-project', 'Legacy Project');

    $this->manager->addGroupProject($this->community, 'project-a', 'key-one');
    $this->manager->addGroupProject($this->community, 'legacy-project', NULL);

    $this->assertSame(['project-a'], $this->manager->getGroupProjectsByApiKey($this->community, 'key-one'));
    $this->assertSame(['legacy-project'], $this->manager->getGroupProjectsWithoutApiKey($this->community));
  }

  /**
   * Site-wide projects with no recorded API key are surfaced the same way
   * as group projects.
   */
  public function testGetSiteProjectsWithoutApiKey() {
    $this->seedProject('site-project', 'Site Project');
    $this->seedProject('legacy-site-project', 'Legacy Site Project');

    $this->manager->addSiteProject('site-project', 'site-key-one');
    $this->manager->addSiteProject('legacy-site-project', NULL);

    $this->assertSame(['site-project'], $this->manager->getSiteProjectsByApiKey('site-key-one'));
    $this->assertSame(['legacy-site-project'], $this->manager->getSiteProjectsWithoutApiKey());
  }

  /**
   * Removing a project that has hub notices attached doesn't throw a
   * database error. The notices/notice_translations tables use compound
   * (project_id, type[, locale]) keys, not an "id"/"label_id" column.
   */
  public function testRemoveProjectWithNoticesDoesNotThrow() {
    $this->seedProject('project-with-notice', 'Project With Notice');
    $this->manager->addGroupProject($this->community, 'project-with-notice', 'key-one');

    $this->container->get('database')->insert('mukurtu_local_contexts_notices')
      ->fields([
        'project_id' => 'project-with-notice',
        'type' => 'attribution',
        'display' => 'notice',
        'name' => 'Attribution Notice',
        'img_url' => '',
        'default_text' => 'Notice text.',
        'updated' => 1,
      ])
      ->execute();

    $this->manager->removeGroupProject($this->community, 'project-with-notice');

    $this->assertSame([], $this->manager->getGroupSupportedProjects($this->community));
  }

  /**
   * fetchFromHub() returns FALSE and leaves no local project row behind
   * when the Hub API call fails (invalid/expired key, rate limit,
   * transient error), rather than creating a supported-project tracking
   * row with nothing for it to join against.
   */
  public function testFetchFromHubFailureLeavesNoOrphanedProjectRow() {
    $mock = new MockHandler([
      new Response(401, [], json_encode(['detail' => 'Invalid token.'])),
    ]);
    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $this->container->set('http_client', $client);

    $project = new LocalContextsProject('missing-project');
    $this->assertFalse($project->fetchFromHub('bad-key'));

    $stored = $this->container->get('database')->select('mukurtu_local_contexts_projects', 'p')
      ->condition('id', 'missing-project')
      ->fields('p', ['id'])
      ->execute()
      ->fetchField();
    $this->assertFalse($stored);
  }

  /**
   * Admins can set, read, and remove a display label for a group's API key,
   * scoped separately from other groups' and the site's labels.
   */
  public function testGroupApiKeyLabelLifecycle() {
    $this->assertSame([], $this->manager->getGroupApiKeyLabels($this->community));

    $this->manager->setGroupApiKeyLabel($this->community, 'key-one', 'WSU Archive');
    $this->assertSame(['key-one' => 'WSU Archive'], $this->manager->getGroupApiKeyLabels($this->community));

    // Setting a label for the same key again updates it in place.
    $this->manager->setGroupApiKeyLabel($this->community, 'key-one', 'Updated Name');
    $this->assertSame(['key-one' => 'Updated Name'], $this->manager->getGroupApiKeyLabels($this->community));

    // An empty label removes it rather than storing a blank string.
    $this->manager->setGroupApiKeyLabel($this->community, 'key-one', '  ');
    $this->assertSame([], $this->manager->getGroupApiKeyLabels($this->community));

    $this->manager->setGroupApiKeyLabel($this->community, 'key-one', 'WSU Archive');
    $this->manager->removeGroupApiKeyLabel($this->community, 'key-one');
    $this->assertSame([], $this->manager->getGroupApiKeyLabels($this->community));
  }

  /**
   * Site-wide API key labels behave the same way as group labels, and are
   * tracked independently of any group's labels.
   */
  public function testSiteApiKeyLabelLifecycle() {
    $this->assertSame([], $this->manager->getSiteApiKeyLabels());

    $this->manager->setSiteApiKeyLabel('site-key-one', 'Mukurtu testing');
    $this->assertSame(['site-key-one' => 'Mukurtu testing'], $this->manager->getSiteApiKeyLabels());

    $this->manager->setGroupApiKeyLabel($this->community, 'site-key-one', 'Community label for same key value');
    $this->assertSame(['site-key-one' => 'Mukurtu testing'], $this->manager->getSiteApiKeyLabels());

    $this->manager->removeSiteApiKeyLabel('site-key-one');
    $this->assertSame([], $this->manager->getSiteApiKeyLabels());
  }

}
