<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40123(), which replaces the admin@example.com
 * placeholder on the website feedback form with the site email address.
 *
 * @see mukurtu_core_update_40123()
 */
#[Group('mukurtu_core')]
class FeedbackFormRecipientUpdateTest extends KernelTestBase {

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

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once $module_path . '/mukurtu_core.install';

    $this->installConfig(['system']);
  }

  /**
   * Creates the feedback contact form with the given recipients.
   */
  protected function createFeedbackForm(array $recipients): void {
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
   * Sets the site email address.
   */
  protected function setSiteMail(string $mail): void {
    \Drupal::configFactory()->getEditable('system.site')
      ->set('mail', $mail)
      ->save();
  }

  /**
   * Returns the feedback form's current recipients.
   */
  protected function recipients(): ?array {
    return \Drupal::config('contact.form.feedback')->get('recipients');
  }

  /**
   * The placeholder recipient is replaced with the site email address.
   */
  public function testUpdateReplacesPlaceholderRecipient(): void {
    $this->setSiteMail('admin@mukurtu.example');
    $this->createFeedbackForm(['admin@example.com']);

    mukurtu_core_update_40123();

    $this->assertSame(['admin@mukurtu.example'], $this->recipients());
  }

  /**
   * A site that configured its own recipients is left alone.
   */
  public function testUpdatePreservesCustomRecipients(): void {
    $this->setSiteMail('admin@mukurtu.example');
    $this->createFeedbackForm(['someone@community.example']);

    mukurtu_core_update_40123();

    $this->assertSame(['someone@community.example'], $this->recipients());
  }

  /**
   * A site that added addresses alongside the placeholder is left alone.
   */
  public function testUpdatePreservesPlaceholderAmongOtherRecipients(): void {
    $this->setSiteMail('admin@mukurtu.example');
    $this->createFeedbackForm(['admin@example.com', 'someone@community.example']);

    mukurtu_core_update_40123();

    $this->assertSame(
      ['admin@example.com', 'someone@community.example'],
      $this->recipients()
    );
  }

  /**
   * The update hook is a no-op when the site email address is empty.
   */
  public function testUpdateIsNoOpWithoutSiteMail(): void {
    $this->setSiteMail('');
    $this->createFeedbackForm(['admin@example.com']);

    mukurtu_core_update_40123();

    $this->assertSame(['admin@example.com'], $this->recipients());
  }

  /**
   * The update hook is a no-op when the feedback form does not exist.
   */
  public function testUpdateIsNoOpWithoutFeedbackForm(): void {
    $this->setSiteMail('admin@mukurtu.example');
    $this->assertTrue(\Drupal::config('contact.form.feedback')->isNew());

    mukurtu_core_update_40123();

    $this->assertTrue(\Drupal::config('contact.form.feedback')->isNew());
  }

  /**
   * The update hook is idempotent.
   */
  public function testUpdateIsIdempotent(): void {
    $this->setSiteMail('admin@mukurtu.example');
    $this->createFeedbackForm(['admin@example.com']);

    mukurtu_core_update_40123();
    mukurtu_core_update_40123();

    $this->assertSame(['admin@mukurtu.example'], $this->recipients());
  }

}
