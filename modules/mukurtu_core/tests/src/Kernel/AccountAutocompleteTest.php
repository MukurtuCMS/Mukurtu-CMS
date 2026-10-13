<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the autocomplete tokens on the account form (#2372).
 *
 * Core's AccountForm sets autocomplete="off" on the account fields whenever
 * the form is not a registration, which fails WCAG 1.3.5 Identify Input
 * Purpose when someone edits their own account. mukurtu_core restores the
 * proper tokens for self-editing only. Both halves are asserted here: the
 * restore, and that an administrator editing someone else still gets "off".
 *
 * @see \Drupal\mukurtu_core\Hook\FormHooks::formUserFormAlter()
 */
#[Group('mukurtu_core')]
class AccountAutocompleteTest extends KernelTestBase {

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

    // User 1 bypasses checks and is created implicitly; burn it so the accounts
    // under test are ordinary ones.
    User::create(['name' => 'throwaway', 'status' => 1])->save();
  }

  /**
   * Builds the edit form for $account as $editor.
   */
  protected function buildEditForm(UserInterface $account, UserInterface $editor): array {
    \Drupal::currentUser()->setAccount($editor);
    return \Drupal::service('entity.form_builder')->getForm($account, 'default');
  }

  /**
   * Editing your own account restores the tokens core turns off.
   */
  public function testSelfEditRestoresTokens(): void {
    $account = User::create(['name' => 'self', 'mail' => 'self@example.com', 'status' => 1]);
    $account->save();

    $form = $this->buildEditForm($account, $account);

    $this->assertSame('username', $form['account']['name']['#attributes']['autocomplete']);
    $this->assertSame('email', $form['account']['mail']['#attributes']['autocomplete']);
    $this->assertArrayHasKey('current_pass', $form['account'], 'Core no longer offers the current password field when self-editing.');
    $this->assertSame('current-password', $form['account']['current_pass']['#attributes']['autocomplete']);
  }

  /**
   * Editing someone else's account keeps core's "off".
   *
   * If the self-edit check ever widened to every edit, an administrator would
   * be offered their own saved details on someone else's account.
   */
  public function testEditingAnotherAccountKeepsCoreOff(): void {
    $account = User::create(['name' => 'edited', 'mail' => 'edited@example.com', 'status' => 1]);
    $account->save();
    $editor = User::create(['name' => 'editor', 'mail' => 'editor@example.com', 'status' => 1]);
    $editor->save();

    $form = $this->buildEditForm($account, $editor);

    $this->assertSame('off', $form['account']['name']['#attributes']['autocomplete']);
    $this->assertSame('off', $form['account']['mail']['#attributes']['autocomplete']);
    $this->assertArrayNotHasKey('current_pass', $form['account']);
  }

  /**
   * The register form is left alone.
   *
   * "user_form" is the base form id for RegisterForm too, so the alter fires
   * there. A new account has no id, so it must never be treated as the
   * signed-in user's own, whoever is signed in.
   */
  public function testRegisterFormIsUntouched(): void {
    $form = \Drupal::service('entity.form_builder')
      ->getForm(User::create([]), 'register');

    $this->assertNotSame('username', $form['account']['name']['#attributes']['autocomplete'] ?? NULL);
    $this->assertNotSame('email', $form['account']['mail']['#attributes']['autocomplete'] ?? NULL);
  }

}
