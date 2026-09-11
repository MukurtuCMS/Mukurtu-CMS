<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that a user account may have no email address (#2163).
 *
 * Core requires the address unless the account already has none and the editor
 * holds 'administer users', which is why /admin/people/create lets you omit one
 * while the edit form refuses to let you remove it. Mukurtu supports community
 * members who have no email, so the two should agree.
 *
 * The form half of this lives in FormHooks::formUserFormAlter(). What is
 * asserted here is the half that actually decides whether a save succeeds:
 * without dropping the constraint, clearing #required would only move the
 * failure from the form to validation.
 *
 * @see mukurtu_core_entity_base_field_info_alter()
 * @see \Drupal\mukurtu_core\Hook\FormHooks::formUserFormAlter()
 */
#[Group('mukurtu_core')]
class OptionalUserEmailTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'text',
    'geofield',
    'leaflet',
    'file',
    'image',
    'media',
    'mukurtu_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);

    // uid 1 bypasses checks and is created implicitly; burn it so the accounts
    // under test are ordinary ones.
    User::create(['name' => 'throwaway', 'status' => 1])->save();
  }

  /**
   * The constraint core uses to require an address is gone.
   */
  public function testMailRequiredConstraintIsRemoved(): void {
    $definitions = \Drupal::service('entity_field.manager')
      ->getBaseFieldDefinitions('user');

    $this->assertArrayHasKey('mail', $definitions);
    $this->assertArrayNotHasKey(
      'UserMailRequired',
      $definitions['mail']->getConstraints(),
      'Core still requires an email address, so the form change alone would fail on save.'
    );
  }

  /**
   * An account can be saved with no address, and loads back with none.
   */
  public function testAccountWithoutEmailValidatesAndSaves(): void {
    $account = User::create(['name' => 'no_email_user', 'status' => 1]);

    $this->assertCount(0, $account->validate(), 'An account with no email failed validation.');

    $account->save();
    $this->assertNull(User::load($account->id())->getEmail());
  }

  /**
   * An address can be removed from an account that already had one.
   *
   * This is the case the issue is actually about: core permits creating
   * without an address but not clearing one afterwards.
   */
  public function testEmailCanBeRemovedFromAnExistingAccount(): void {
    $account = User::create(['name' => 'had_email', 'mail' => 'had@example.com', 'status' => 1]);
    $account->save();
    $this->assertSame('had@example.com', User::load($account->id())->getEmail());

    $account->set('mail', NULL);
    $this->assertCount(0, $account->validate(), 'Clearing an existing email failed validation.');

    $account->save();
    $this->assertNull(User::load($account->id())->getEmail());
  }

  /**
   * Two accounts still cannot share an address.
   *
   * Only the "required" constraint is dropped. Uniqueness is a different rule
   * and removing it would let duplicate accounts collide on password reset.
   */
  public function testAddressesAreStillUnique(): void {
    User::create(['name' => 'first', 'mail' => 'shared@example.com', 'status' => 1])->save();

    $second = User::create(['name' => 'second', 'mail' => 'shared@example.com', 'status' => 1]);
    $violations = $second->validate();

    $this->assertGreaterThan(0, $violations->count(), 'Two accounts were allowed to share an email address.');
  }

  /**
   * The edit form stops asking for an address.
   */
  public function testEditFormDoesNotRequireEmail(): void {
    $account = User::create(['name' => 'editable', 'mail' => 'editable@example.com', 'status' => 1]);
    $account->save();

    $form = \Drupal::service('entity.form_builder')->getForm($account, 'default');

    $this->assertFalse($form['account']['mail']['#required'], 'The edit form still requires an email address.');
  }

  /**
   * Registration still requires an address.
   *
   * This is the regression that matters most here. "user_form" is the *base*
   * form id for RegisterForm as well as UserForm, so hook_form_user_form_alter()
   * fires for /user/register too. Without the operation guard, anonymous signup
   * would silently stop asking for an address, leaving self-registered accounts
   * with no password reset and no way to be contacted.
   */
  public function testRegisterFormStillRequiresEmail(): void {
    $form = \Drupal::service('entity.form_builder')
      ->getForm(User::create([]), 'register');

    $this->assertTrue(
      $form['account']['mail']['#required'],
      'Anonymous registration no longer requires an email address.'
    );
  }

  /**
   * An address that is supplied still has to be a valid one.
   */
  public function testSuppliedAddressIsStillValidated(): void {
    $account = User::create(['name' => 'bad_email', 'mail' => 'not-an-email', 'status' => 1]);

    $this->assertGreaterThan(0, $account->validate()->count(), 'An invalid email address was accepted.');
  }

}
