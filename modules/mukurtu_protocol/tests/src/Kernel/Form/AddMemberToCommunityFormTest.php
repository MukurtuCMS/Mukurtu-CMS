<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests step 1 validation on the add-member form.
 *
 * All three of these checks used to live in the form's #submit handler.
 * FormState::setErrorByName() throws a LogicException once validation has
 * finished, so each ordinary mistake - no user chosen, an existing member
 * chosen, no role ticked - returned HTTP 500 and a white screen instead of
 * the message the code intended. A Community Manager re-adding someone who
 * was already a member hit it every time.
 *
 * @see \Drupal\mukurtu_protocol\Form\AddMemberToCommunityForm::validateStep1()
 */
class AddMemberToCommunityFormTest extends KernelTestBase {

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
  ];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The community under test.
   */
  protected Community $community;

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
    $role->save();

    $memberRole = OgRole::create(['name' => 'community_member', 'label' => 'Community Member']);
    $memberRole->setGroupType('community');
    $memberRole->setGroupBundle('community');
    $memberRole->save();

    $owner = $this->user();
    $this->community = Community::create([
      'name' => 'Test community',
      'status' => TRUE,
      'uid' => $owner->id(),
    ]);
    $this->community->save();
  }

  /**
   * Creates a saved user.
   */
  private function user(): User {
    $user = User::create(['name' => $this->randomMachineName()]);
    $user->save();
    return $user;
  }

  /**
   * Runs step 1 validation and returns the error messages.
   *
   * @return string[]
   *   Error messages keyed by form element name.
   */
  private function validate(?int $uid, array $selections): array {
    $form_object = $this->container->get('class_resolver')
      ->getInstanceFromDefinition('Drupal\mukurtu_protocol\Form\AddMemberToCommunityForm');

    $form_state = new FormState();
    $form_state->set('community_id', $this->community->id());
    $form_state->setValues([
      'user' => $uid,
      'memberships' => $selections,
    ]);

    $form = [];
    $form_object->validateStep1($form, $form_state);

    return array_map('strval', $form_state->getErrors());
  }

  /**
   * A valid selection raises nothing.
   */
  public function testValidSelectionPasses(): void {
    $user = $this->user();
    $errors = $this->validate((int) $user->id(), [
      $this->community->id() => ['community-community-community_member' => 1],
    ]);

    $this->assertSame([], $errors);
  }

  /**
   * Re-adding an existing member is a validation error, not a crash.
   *
   * This is the path that broke CI: the seeding enrols the accessibility
   * scan accounts, and on any later run they are already members.
   */
  public function testExistingMemberIsRejectedWithAMessage(): void {
    $user = $this->user();
    $this->community->addMember($user, ['community_member']);

    $errors = $this->validate((int) $user->id(), [
      $this->community->id() => ['community-community-community_member' => 1],
    ]);

    $this->assertNotEmpty($errors, 'An existing member produces an error rather than an exception.');
    $this->assertStringContainsString('already a member', implode(' ', $errors));
  }

  /**
   * Choosing no user is a validation error.
   */
  public function testMissingUserIsRejected(): void {
    $errors = $this->validate(NULL, []);

    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('Please select a user', implode(' ', $errors));
  }

  /**
   * Choosing no role is a validation error.
   */
  public function testMissingRoleIsRejected(): void {
    $user = $this->user();
    $errors = $this->validate((int) $user->id(), []);

    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('at least one community role', implode(' ', $errors));
  }

}
