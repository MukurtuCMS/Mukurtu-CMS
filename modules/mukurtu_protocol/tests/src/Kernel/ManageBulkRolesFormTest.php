<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Form\ManageCommunityBulkRolesForm;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests ManageBulkRolesFormBase validation logic.
 *
 * @group mukurtu_protocol
 */
class ManageBulkRolesFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'workflows',
    'field',
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
  ];

  /**
   * Role ID for the community manager OG role.
   */
  const MANAGER_ROLE = 'community-community-community_manager';

  /**
   * Role ID for the community member OG role.
   */
  const MEMBER_ROLE = 'community-community-community_member';

  /**
   * @var \Drupal\mukurtu_protocol\Entity\CommunityInterface
   */
  protected $community;

  /**
   * @var \Drupal\mukurtu_protocol\Form\ManageCommunityBulkRolesForm
   */
  protected $form;

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

    $role = Role::create(['id' => 'authenticated', 'label' => 'authenticated']);
    $role->save();

    // Create the standard assignable roles.
    $manager = OgRole::create([
      'name' => 'community_manager',
      'label' => 'Community Manager',
      'permissions' => ['manage members', 'update group', 'add user'],
    ]);
    $manager->setGroupType('community');
    $manager->setGroupBundle('community');
    $manager->save();

    $member = OgRole::create([
      'name' => 'community_member',
      'label' => 'Community Member',
      'permissions' => [],
    ]);
    $member->setGroupType('community');
    $member->setGroupBundle('community');
    $member->save();

    $owner = User::create(['name' => $this->randomString()]);
    $owner->save();

    $this->community = Community::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $owner->id(),
    ]);
    $this->community->save();

    $this->form = ManageCommunityBulkRolesForm::create($this->container);
  }

  /**
   * Builds minimal form state values for the given membership table rows.
   *
   * @param array $rows
   *   Keyed by membership ID; each value is an array of role_id => 0|1.
   * @param string[] $admin_role_ids
   *   IDs of roles that carry the manager permission.
   *
   * @return \Drupal\Core\Form\FormState
   */
  protected function buildFormState(array $rows, array $admin_role_ids): FormState {
    $form_state = new FormState();
    $form_state->setValue('roles_table', $rows);
    $form_state->setValue('role_ids', [self::MANAGER_ROLE, self::MEMBER_ROLE]);
    $form_state->setValue('admin_role_ids', $admin_role_ids);
    $form_state->setValue('group_entity_type', $this->community->getEntityTypeId());
    $form_state->setValue('group_id', $this->community->id());
    return $form_state;
  }

  /**
   * Creates a member of this community with the given OG roles and returns
   * the membership entity.
   */
  protected function addMember(array $og_role_names = []): \Drupal\og\Entity\OgMembership {
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $membership = $this->community->addMember($user, $og_role_names);
    $membership->save();
    return $membership;
  }

  /**
   * Returns a table row for the given membership with specified role checks.
   */
  protected function makeRow(bool $manager = FALSE, bool $member = TRUE): array {
    return [
      self::MANAGER_ROLE => (int) $manager,
      self::MEMBER_ROLE  => (int) $member,
    ];
  }

  /**
   * Tests that a row with no roles checked triggers a validation error.
   */
  public function testMemberMustHaveAtLeastOneRole(): void {
    $membership = $this->addMember(['community_member']);

    $rows = [$membership->id() => $this->makeRow(FALSE, FALSE)];
    $form_state = $this->buildFormState($rows, [self::MANAGER_ROLE]);
    $form = [];

    $this->form->validateForm($form, $form_state);

    $this->assertTrue($form_state->hasAnyErrors(), 'Validation should fail when a member has no roles selected.');
  }

  /**
   * Tests that removing the only manager triggers a validation error.
   */
  public function testLastManagerCannotBeRemoved(): void {
    $manager_membership = $this->addMember(['community_manager']);

    // Edited row strips manager role.
    $rows = [$manager_membership->id() => $this->makeRow(FALSE, TRUE)];
    $form_state = $this->buildFormState($rows, [self::MANAGER_ROLE]);
    $form = [];

    $this->form->validateForm($form, $form_state);

    $this->assertTrue($form_state->hasAnyErrors(), 'Validation should fail when removing the only manager.');
  }

  /**
   * Tests that removing a manager is allowed when another manager exists
   * outside the edited set.
   */
  public function testManagerCanBeRemovedWhenAnotherExists(): void {
    $manager_membership = $this->addMember(['community_manager']);
    // Second manager is not part of the edited set.
    $this->addMember(['community_manager']);

    $rows = [$manager_membership->id() => $this->makeRow(FALSE, TRUE)];
    $form_state = $this->buildFormState($rows, [self::MANAGER_ROLE]);
    $form = [];

    $this->form->validateForm($form, $form_state);

    $this->assertFalse($form_state->hasAnyErrors(), 'Validation should pass when another manager remains outside the edited set.');
  }

  /**
   * Tests that a valid submission (manager retained) passes validation.
   */
  public function testValidSubmissionPasses(): void {
    $membership = $this->addMember(['community_manager']);

    $rows = [$membership->id() => $this->makeRow(TRUE, TRUE)];
    $form_state = $this->buildFormState($rows, [self::MANAGER_ROLE]);
    $form = [];

    $this->form->validateForm($form, $form_state);

    $this->assertFalse($form_state->hasAnyErrors(), 'Validation should pass when a manager role is retained.');
  }

}
