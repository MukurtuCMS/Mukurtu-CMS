<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_local_contexts\Controller\ProjectDirectoryController;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\og\Og;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests access to a group's Local Contexts projects directory.
 *
 * The directory used to be readable by anyone as long as the group
 * existed. That disclosed a strict protocol's name to anonymous visitors
 * through the page title ("%group's Local Contexts Projects"), along with
 * its projects and labels, even though those visitors cannot view the
 * protocol itself. For a system built around cultural protocols, a strict
 * protocol's existence should not be public.
 *
 * @see \Drupal\mukurtu_local_contexts\Controller\ProjectDirectoryController::groupDirectoryAccess()
 */
class ProjectDirectoryAccessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'workflows',
    'field',
    'geofield',
    'leaflet',
    'node',
    'node_access_test',
    'media',
    'og',
    'options',
    'system',
    'text',
    'taxonomy',
    'user',
    'mukurtu_core',
    'mukurtu_protocol',
    'path_alias',
    'mukurtu_local_contexts',
  ];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['og']);
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installSchema('system', 'sequences');

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    $role = Role::create(['id' => 'authenticated', 'label' => 'authenticated']);
    $role->grantPermission('access content');
    $role->grantPermission('view published community entities');
    $role->save();

    // Mirror the permissions a real Mukurtu site grants anonymous, rather
    // than a bare role: the install gives it 'view published community
    // entities', which is what makes a public community visible to a
    // visitor at all. Without it this test would "pass" by denying
    // everything, and would not notice the directory being locked down too
    // far.
    $anonymous = Role::create(['id' => 'anonymous', 'label' => 'anonymous']);
    $anonymous->grantPermission('access content');
    $anonymous->grantPermission('view published community entities');
    $anonymous->save();
  }

  /**
   * Creates a user.
   */
  private function user(): User {
    $user = User::create(['name' => $this->randomMachineName()]);
    $user->save();
    return $user;
  }

  /**
   * Runs the directory access check for a group and account.
   */
  private function directoryAccess($group, $account): bool {
    $controller = ProjectDirectoryController::create($this->container);
    return $controller->groupDirectoryAccess($account, $group)->isAllowed();
  }

  /**
   * A strict protocol's directory is hidden from people who cannot see it.
   *
   * This is the disclosure the change fixes: before it, this assertion
   * would have been TRUE for an anonymous visitor.
   */
  public function testStrictProtocolDirectoryIsNotPublic(): void {
    $owner = $this->user();
    $community = Community::create(['name' => 'Test community', 'status' => TRUE, 'uid' => $owner->id()]);
    $community->save();

    $protocol = Protocol::create([
      'name' => 'Strict protocol',
      'status' => TRUE,
      'uid' => $owner->id(),
      'field_communities' => [$community->id()],
      'field_access_mode' => 'strict',
    ]);
    $protocol->save();

    $anonymous = User::getAnonymousUser();
    $this->assertFalse(
      $protocol->access('view', $anonymous),
      'Precondition: anonymous cannot view a strict protocol.',
    );
    $this->assertFalse(
      $this->directoryAccess($protocol, $anonymous),
      'So anonymous cannot reach its Local Contexts directory either.',
    );

    // A member of the protocol can view it, so can see the directory.
    $member = $this->user();
    $protocol->addMember($member);
    $this->assertTrue($protocol->access('view', $member), 'Precondition: a member can view it.');
    $this->assertTrue($this->directoryAccess($protocol, $member));
  }

  /**
   * An open protocol's directory stays public, because the protocol is.
   *
   * The rule is parity with the group, not "lock everything down": an open
   * protocol is deliberately visible, so its projects should be too.
   */
  public function testOpenProtocolDirectoryStaysPublic(): void {
    $owner = $this->user();
    $community = Community::create(['name' => 'Test community', 'status' => TRUE, 'uid' => $owner->id()]);
    $community->save();

    $protocol = Protocol::create([
      'name' => 'Open protocol',
      'status' => TRUE,
      'uid' => $owner->id(),
      'field_communities' => [$community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();

    $anonymous = User::getAnonymousUser();
    $this->assertTrue($protocol->access('view', $anonymous), 'Precondition: an open protocol is viewable.');
    $this->assertTrue($this->directoryAccess($protocol, $anonymous));
  }

  /**
   * The same rule applies to communities, which share the access method.
   */
  public function testCommunityDirectoryFollowsCommunityAccess(): void {
    $owner = $this->user();

    $private = Community::create(['name' => 'Members only', 'status' => TRUE, 'uid' => $owner->id()]);
    $private->setSharingSetting('community-only');
    $private->save();

    $public = Community::create(['name' => 'Open to all', 'status' => TRUE, 'uid' => $owner->id()]);
    $public->setSharingSetting('public');
    $public->save();

    $anonymous = User::getAnonymousUser();
    $this->assertFalse($this->directoryAccess($private, $anonymous), 'A community-only community hides its directory.');
    $this->assertTrue($this->directoryAccess($public, $anonymous), 'A public one does not.');

    $member = $this->user();
    $private->addMember($member);
    $this->assertTrue($this->directoryAccess($private, $member), 'Its members still reach it.');
  }

  /**
   * A missing group is forbidden rather than fataling.
   */
  public function testMissingGroupIsForbidden(): void {
    $this->assertFalse($this->directoryAccess(NULL, $this->user()));
  }

}
