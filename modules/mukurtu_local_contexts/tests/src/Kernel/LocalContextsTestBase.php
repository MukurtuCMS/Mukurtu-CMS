<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Base class for Mukurtu Local Contexts kernel tests.
 */
abstract class LocalContextsTestBase extends KernelTestBase {

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
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
    'mukurtu_core',
    'mukurtu_protocol',
    'mukurtu_local_contexts',
  ];

  /**
   * The current test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $currentUser;

  /**
   * A community for group-scoped project tests.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Community
   */
  protected Community $community;

  /**
   * A protocol for group-scoped project tests.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Protocol
   */
  protected Protocol $protocol;

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

    $this->installSchema('system', 'sequences');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installSchema('mukurtu_local_contexts', [
      'mukurtu_local_contexts_supported_projects',
      'mukurtu_local_contexts_projects',
      'mukurtu_local_contexts_labels',
      'mukurtu_local_contexts_label_translations',
      'mukurtu_local_contexts_notices',
      'mukurtu_local_contexts_notice_translations',
    ]);

    $this->installEntitySchema('user');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');

    $this->installConfig(['filter', 'og', 'system']);

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    // Authenticated role.
    $role = Role::create(['id' => 'authenticated', 'label' => 'authenticated']);
    $role->grantPermission('access content');
    $role->save();

    // Protocol steward OG role.
    $protocolStewardRole = OgRole::create([
      'name' => 'protocol_steward',
      'label' => 'Protocol Steward',
      'permissions' => [
        'add user',
        'manage members',
        'update group',
      ],
    ]);
    $protocolStewardRole->setGroupType('protocol');
    $protocolStewardRole->setGroupBundle('protocol');
    $protocolStewardRole->save();

    $this->container = \Drupal::getContainer();

    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->currentUser = $user;
    $this->container->get('current_user')->setAccount($user);

    $community = Community::create(['name' => 'Test Community']);
    $community->save();
    $community->addMember($user);
    $this->community = $community;

    $protocol = Protocol::create([
      'name' => 'Test Protocol',
      'field_communities' => [$community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $protocol->addMember($user, ['protocol_steward']);
    $this->protocol = $protocol;

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
