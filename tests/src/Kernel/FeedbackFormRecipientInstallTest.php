<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_install_configure_form_submit(), the fresh-install counterpart
 * to mukurtu_core_update_40123().
 *
 * The profile's own hook_install() cannot do this work, because
 * install_install_profile runs before install_configure_form and so
 * system.site:mail is still empty while mukurtu_install() executes. The
 * behaviour therefore hangs off install_configure_form's submit handler.
 *
 * @see mukurtu_install_configure_form_submit()
 * @see mukurtu_core_update_40123()
 */
#[Group('mukurtu')]
class FeedbackFormRecipientInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'contact',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // This test file lives at <profile>/tests/src/Kernel, so the profile root
    // is four levels up. Resolved from __DIR__ rather than through the profile
    // extension list, which is not reliable here: kernel tests run with no
    // active install profile.
    require_once dirname(__DIR__, 3) . '/mukurtu.profile';

    $this->installConfig(['system']);
  }

  /**
   * Creates the feedback contact form with the shipped placeholder recipient.
   */
  protected function createFeedbackForm(array $recipients = ['admin@example.com']): void {
    \Drupal::configFactory()->getEditable('contact.form.feedback')
      ->set('id', 'feedback')
      ->set('label', 'Website feedback')
      ->set('recipients', $recipients)
      ->set('reply', '')
      ->set('weight', 0)
      ->set('message', 'Your message has been sent.')
      ->set('redirect', '')
      ->save();
  }

  /**
   * Returns the feedback form's current recipients.
   */
  protected function recipients(): ?array {
    return \Drupal::config('contact.form.feedback')->get('recipients');
  }

  /**
   * The submitted site email address becomes the feedback recipient.
   */
  public function testSubmitUsesSubmittedSiteMail(): void {
    $this->createFeedbackForm();

    $form_state = new FormState();
    $form_state->setValue('site_mail', 'support@mukurtu.example');
    $form = [];
    mukurtu_install_configure_form_submit($form, $form_state);

    $this->assertSame(['support@mukurtu.example'], $this->recipients());
  }

  /**
   * Falls back to system.site:mail when the form value is absent.
   */
  public function testSubmitFallsBackToSiteConfig(): void {
    $this->createFeedbackForm();
    \Drupal::configFactory()->getEditable('system.site')
      ->set('mail', 'fallback@mukurtu.example')
      ->save();

    $form_state = new FormState();
    $form = [];
    mukurtu_install_configure_form_submit($form, $form_state);

    $this->assertSame(['fallback@mukurtu.example'], $this->recipients());
  }

  /**
   * Does nothing when no site email address is available at all.
   */
  public function testSubmitIsNoOpWithoutAnySiteMail(): void {
    $this->createFeedbackForm();
    \Drupal::configFactory()->getEditable('system.site')->set('mail', '')->save();

    $form_state = new FormState();
    $form = [];
    mukurtu_install_configure_form_submit($form, $form_state);

    $this->assertSame(['admin@example.com'], $this->recipients());
  }

  /**
   * Does nothing when the feedback form does not exist.
   */
  public function testSubmitIsNoOpWithoutFeedbackForm(): void {
    $this->assertTrue(\Drupal::config('contact.form.feedback')->isNew());

    $form_state = new FormState();
    $form_state->setValue('site_mail', 'support@mukurtu.example');
    $form = [];
    mukurtu_install_configure_form_submit($form, $form_state);

    $this->assertTrue(\Drupal::config('contact.form.feedback')->isNew());
  }

  /**
   * The form alter registers the submit handler.
   */
  public function testFormAlterAppendsSubmitHandler(): void {
    $form = ['#submit' => ['existing_handler']];
    $form_state = new FormState();
    mukurtu_form_install_configure_form_alter($form, $form_state);

    $this->assertSame(
      ['existing_handler', 'mukurtu_install_configure_form_submit'],
      $form['#submit']
    );
  }

}
