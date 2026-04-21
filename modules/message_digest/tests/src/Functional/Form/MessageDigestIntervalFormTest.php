<?php

namespace Drupal\Tests\message_digest\Functional\Form;

use Drupal\message_digest\Entity\MessageDigestInterval;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the message digest interval UI and forms.
 *
 * @group message_digest
 */
class MessageDigestIntervalFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'message_digest', 'system'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Skip UID 1.
    $this->createUser();

    $this->adminUser = $this->createUser([
      'access administration pages',
      'administer message digest',
      'administer message templates',
    ]);

    $this->placeBlock('local_actions_block');
  }

  /**
   * Tests CRUD operations.
   */
  public function testCrud() {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('/admin/config/message');
    $this->clickLink('Message digest intervals');
    $this->clickLink('Add digest interval');

    $edit = [
      'id' => 'bi_weekly',
      'label' => 'Every 2 weeks',
      'interval' => '2 weeks',
      'description' => 'Send digests every 2 weeks',
    ];
    $this->submitForm($edit, 'Save');

    /** @var \Drupal\message_digest\Entity\MessageDigestIntervalInterface $config */
    $config = MessageDigestInterval::load('bi_weekly');
    $this->assertEquals('2 weeks', $config->getInterval());
    $this->assertEquals('Every 2 weeks', $config->label());
    $this->assertEquals('Send digests every 2 weeks', $config->getDescription());

    $this->assertSession()->pageTextContains('Interval Every 2 weeks has been added.');
    $this->assertSession()->addressEquals($config->toUrl('collection')->setAbsolute(TRUE)->toString());
    $this->assertSession()->pageTextContains('Every 2 weeks');

    // Edit the interval.
    $this->drupalGet($config->toUrl('edit-form'));
    $edit = [
      'label' => 'Every 14 days',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->addressEquals($config->toUrl('collection')->setAbsolute(TRUE)->toString());
    $this->assertSession()->pageTextContains('Interval Every 14 days has been updated.');

    // Try an invalid interval.
    $this->drupalGet($config->toUrl('edit-form'));
    $edit = [
      'interval' => '42 bananas',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('The interval 42 bananas is invalid');

    // Delete the interval.
    $this->drupalGet($config->toUrl('delete-form'));
    $this->assertSession()->pageTextContains('Delete Every 14 days interval? This action cannot be undone.');
    $this->submitForm([], 'Delete interval');
    $this->assertSession()->pageTextContains('The Every 14 days message digest interval has been deleted.');
    $this->assertSession()->addressEquals($config->toUrl('collection')->setAbsolute(TRUE)->toString());
    \Drupal::entityTypeManager()->getStorage('message_digest_interval')->resetCache();
    $this->assertNull(MessageDigestInterval::load('bi_weekly'), 'The interval was not deleted.');
  }

}
