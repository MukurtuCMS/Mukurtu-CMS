<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Shared base class for Mukurtu kernel tests.
 *
 * Installs the minimum set of schemas and creates the standard
 * user / community / protocol fixture that every Mukurtu kernel test needs.
 *
 * Subclasses that require additional modules must re-declare $modules and
 * include this class's $modules list plus their own additions, since PHP
 * static property overriding means a child's $modules completely replaces
 * the parent's. Alternatively, call $this->enableModules([...]) in setUp()
 * after parent::setUp().
 */
abstract class MukurtuKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Core modules required by all Mukurtu kernel tests.
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
    'mukurtu_local_contexts',
    'mukurtu_protocol',
  ];

  /**
   * The current test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $currentUser;

  /**
   * A community for the content.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Community
   */
  protected Community $community;

  /**
   * A protocol for the content.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Protocol
   */
  protected Protocol $protocol;

  /**
   * {@inheritdoc}
   *
   * Register a synthetic file.repository stub so Symfony's DI compiler does
   * not reject the container when the blazy module is loaded. The blazy.file
   * service has a hard dependency on file.repository, a Drupal 9 service
   * removed in Drupal 10. The concrete mock is set in setUp() to satisfy
   * BlazyFile::__construct(FileRepository)'s type check at runtime.
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    if (!$container->has('file.repository')) {
      $container->register('file.repository')->setSynthetic(TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Satisfy the runtime type check on BlazyFile::__construct(FileRepository).
    // Kernel tests do not exercise any Blazy file operations, so this mock is
    // never actually called.
    $this->container->set(
      'file.repository',
      $this->createMock(\Drupal\file\FileRepository::class)
    );

    $this->installSchema('system', 'sequences');
    $this->installSchema('file', 'file_usage');
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
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('taxonomy_vocabulary');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');

    $this->installConfig(['filter', 'og', 'system']);

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    // Authenticated role.
    $role = Role::create(['id' => 'authenticated', 'label' => 'authenticated']);
    $role->grantPermission('access content');
    $role->save();

    // Protocol steward OG role. Subclasses may override
    // getProtocolStewardPermissions() to add content-type-specific permissions.
    $protocolStewardRole = OgRole::create([
      'name' => 'protocol_steward',
      'label' => 'Protocol Steward',
      'permissions' => $this->getProtocolStewardPermissions(),
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
  }

  /**
   * Returns the permissions to assign to the protocol_steward OG role.
   *
   * Subclasses may override to add content-type-specific CRUD permissions.
   *
   * @return string[]
   *   An array of OG permission machine names.
   */
  protected function getProtocolStewardPermissions(): array {
    return [
      'add user',
      'apply protocol',
      'administer permissions',
      'approve and deny subscription',
      'manage members',
      'update group',
    ];
  }

}
