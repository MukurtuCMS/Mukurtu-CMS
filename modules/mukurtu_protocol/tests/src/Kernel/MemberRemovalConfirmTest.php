<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\Form\MukurtuOgMembershipRemoveMultipleForm;
use Drupal\mukurtu_protocol\Plugin\Action\MukurtuDeleteOgMembershipAction;
use Drupal\og\Entity\OgMembership;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that bulk member removal is staged and confirmed, not applied at once.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2370
 */
#[Group('mukurtu_protocol')]
#[CoversClass(MukurtuDeleteOgMembershipAction::class)]
#[CoversClass(MukurtuOgMembershipRemoveMultipleForm::class)]
class MemberRemovalConfirmTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'workflows',
    'field',
    'file',
    'filter',
    'geofield',
    'image',
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
    'views',
    'views_bulk_operations',
  ];

  /**
   * The community members are removed from.
   */
  protected Community $community;

  /**
   * A user with the community manager role.
   */
  protected User $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['og', 'filter', 'system']);
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installSchema('system', 'sequences');

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    Role::create(['id' => 'authenticated', 'label' => 'authenticated'])->save();

    $manager_role = OgRole::create([
      'name' => 'community_manager',
      'label' => 'Community Manager',
      'permissions' => ['manage members', 'update group', 'add user'],
    ]);
    $manager_role->setGroupType('community');
    $manager_role->setGroupBundle('community');
    $manager_role->save();

    $member_role = OgRole::create([
      'name' => 'community_member',
      'label' => 'Community Member',
      'permissions' => [],
    ]);
    $member_role->setGroupType('community');
    $member_role->setGroupBundle('community');
    $member_role->save();

    $protocol_member_role = OgRole::create([
      'name' => 'protocol_member',
      'label' => 'Protocol Member',
      'permissions' => [],
    ]);
    $protocol_member_role->setGroupType('protocol');
    $protocol_member_role->setGroupBundle('protocol');
    $protocol_member_role->save();

    // The first user created is uid 1, which bypasses access checks, so it is
    // spent on the community owner rather than on anybody under test.
    $owner = User::create(['name' => 'community_owner']);
    $owner->save();

    $this->community = Community::create([
      'name' => 'Test community',
      'status' => TRUE,
      'uid' => $owner->id(),
    ]);
    $this->community->save();

    $this->manager = $this->createMember('manager', ['community_manager']);
    $this->setCurrentUser($this->manager);
  }

  /**
   * Creates a user and adds them to the community.
   *
   * @param string $name
   *   The user name.
   * @param string[] $og_roles
   *   OG role names to give the membership.
   *
   * @return \Drupal\user\Entity\User
   *   The new user.
   */
  protected function createMember(string $name, array $og_roles = ['community_member']): User {
    $user = User::create(['name' => $name]);
    $user->save();
    $this->community->addMember($user, $og_roles);
    return $user;
  }

  /**
   * Returns the community membership of a user.
   */
  protected function membershipOf(User $user): ?OgMembership {
    return Og::getMembership($this->community, $user);
  }

  /**
   * Returns the delete action plugin, as the members list bulk form uses it.
   */
  protected function deleteAction(): MukurtuDeleteOgMembershipAction {
    $action = \Drupal::service('plugin.manager.action')
      ->createInstance('og_membership_delete_action');
    $this->assertInstanceOf(MukurtuDeleteOgMembershipAction::class, $action);
    return $action;
  }

  /**
   * Builds the confirm form, as a request to the confirm route would.
   *
   * @param \Drupal\Core\Form\FormState $form_state
   *   The form state to build with.
   *
   * @return array
   *   The form object and the built form array, in that order.
   */
  protected function buildConfirmForm(FormState $form_state): array {
    $form_object = MukurtuOgMembershipRemoveMultipleForm::create($this->container);
    return [$form_object, $form_object->buildForm([], $form_state)];
  }

  /**
   * Tests that the action definition sends the bulk form to a confirm step.
   */
  public function testActionDefinitionCarriesConfirmRoute(): void {
    $definition = \Drupal::service('plugin.manager.action')
      ->getDefinition('og_membership_delete_action');

    $this->assertSame(
      'mukurtu_protocol.og_membership.remove_multiple',
      $definition['confirm_form_route_name'] ?? NULL,
      'The members list bulk form redirects to the removal confirm form.'
    );
  }

  /**
   * Tests that running the action stages the selection and removes nobody.
   */
  public function testActionStagesWithoutRemoving(): void {
    $first = $this->createMember('first');
    $second = $this->createMember('second');

    $this->deleteAction()->executeMultiple([
      $this->membershipOf($first),
      $this->membershipOf($second),
    ]);

    $this->assertNotNull($this->membershipOf($first), 'Running the action alone removes nobody.');
    $this->assertNotNull($this->membershipOf($second), 'Running the action alone removes nobody.');

    $staged = \Drupal::service('tempstore.private')
      ->get(MukurtuDeleteOgMembershipAction::TEMPSTORE_COLLECTION)
      ->get($this->manager->id() . ':og_membership');

    $this->assertEqualsCanonicalizing(
      [$this->membershipOf($first)->id(), $this->membershipOf($second)->id()],
      array_keys($staged),
      'Both selected memberships are staged for confirmation.'
    );
  }

  /**
   * Tests that the staged members are listed by name on the confirm form.
   */
  public function testConfirmFormListsTheStagedMembers(): void {
    $first = $this->createMember('first');
    $second = $this->createMember('second');
    $untouched = $this->createMember('untouched');

    $this->deleteAction()->executeMultiple([
      $this->membershipOf($first),
      $this->membershipOf($second),
    ]);

    [, $form] = $this->buildConfirmForm(new FormState());

    $this->assertEqualsCanonicalizing(
      ['first', 'second'],
      $form['members']['list']['#items'],
      'The confirm form names the members that are about to be removed.'
    );
    $this->assertArrayNotHasKey('blocked', $form);
    $this->assertNotNull($this->membershipOf($untouched));
  }

  /**
   * Tests that confirming removes exactly the staged members.
   */
  public function testConfirmingRemovesTheStagedMembers(): void {
    $first = $this->createMember('first');
    $second = $this->createMember('second');
    $untouched = $this->createMember('untouched');

    $this->deleteAction()->executeMultiple([
      $this->membershipOf($first),
      $this->membershipOf($second),
    ]);

    $form_state = new FormState();
    [$form_object, $form] = $this->buildConfirmForm($form_state);
    $form_object->submitForm($form, $form_state);

    $this->assertNull($this->membershipOf($first), 'A confirmed removal takes effect.');
    $this->assertNull($this->membershipOf($second), 'A confirmed removal takes effect.');
    $this->assertNotNull($this->membershipOf($untouched), 'Members that were not selected are left alone.');
  }

  /**
   * Tests that cancelling the confirm form leaves everybody in place.
   *
   * Cancelling is a link back to the members list, so the test of it is that
   * staging on its own changes nothing and the staged selection is still
   * there to be confirmed or abandoned.
   */
  public function testStagedSelectionSurvivesUntilConfirmed(): void {
    $member = $this->createMember('member');
    $this->deleteAction()->executeMultiple([$this->membershipOf($member)]);

    // Build the form twice, as a reload of the confirmation page would.
    $this->buildConfirmForm(new FormState());
    [, $form] = $this->buildConfirmForm(new FormState());

    $this->assertSame(['member'], $form['members']['list']['#items']);
    $this->assertNotNull($this->membershipOf($member), 'Nobody is removed until the form is submitted.');
  }

  /**
   * Tests that a member who is still in a protocol is kept, with the reason.
   */
  public function testProtocolMemberIsKeptAndExplained(): void {
    $protocol = Protocol::create([
      'name' => 'Test protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $protocol->save();

    $removable = $this->createMember('removable');
    $in_protocol = $this->createMember('in_protocol');
    $protocol->addMember($in_protocol, ['protocol_member']);

    $this->deleteAction()->executeMultiple([
      $this->membershipOf($removable),
      $this->membershipOf($in_protocol),
    ]);

    $form_state = new FormState();
    [$form_object, $form] = $this->buildConfirmForm($form_state);

    $this->assertSame(['removable'], $form['members']['list']['#items'], 'Only the member who can go is listed for removal.');
    $this->assertCount(1, $form['blocked']['list']['#items'], 'The member who is still in a protocol is listed separately.');
    $this->assertStringContainsString(
      'in_protocol',
      (string) $form['blocked']['list']['#items'][0],
      'The blocked entry names the member it is about.'
    );
    $this->assertStringContainsString(
      'protocol',
      (string) $form['blocked']['list']['#items'][0],
      'The blocked entry gives the reason, which the bulk form could not.'
    );

    $form_object->submitForm($form, $form_state);

    $this->assertNull($this->membershipOf($removable));
    $this->assertNotNull($this->membershipOf($in_protocol), 'A member who is still in a protocol is not removed.');
  }

  /**
   * Tests that the action is only offered to users who may manage members.
   */
  public function testActionAccessRequiresManageMembers(): void {
    $plain_member = $this->createMember('plain');
    $target = $this->createMember('target');
    $membership = $this->membershipOf($target);

    $this->assertTrue(
      $this->deleteAction()->access($membership, $this->manager),
      'A community manager may remove members.'
    );
    $this->assertFalse(
      $this->deleteAction()->access($membership, $plain_member),
      'A member without manage members may not remove members.'
    );
  }

  /**
   * Tests that the confirm route is closed when nothing has been staged.
   */
  public function testConfirmRouteRequiresStagedSelection(): void {
    $form_object = MukurtuOgMembershipRemoveMultipleForm::create($this->container);

    $this->assertFalse(
      $form_object->access($this->manager)->isAllowed(),
      'Visiting the confirm form without a staged selection is refused.'
    );

    $member = $this->createMember('member');
    $this->deleteAction()->executeMultiple([$this->membershipOf($member)]);

    $this->assertTrue(
      MukurtuOgMembershipRemoveMultipleForm::create($this->container)->access($this->manager)->isAllowed(),
      'A manager with a staged selection reaches the confirm form.'
    );
    $this->assertFalse(
      MukurtuOgMembershipRemoveMultipleForm::create($this->container)->access($this->createMember('outsider'))->isAllowed(),
      'A user who may not manage this group\'s members is refused.'
    );
  }

}
