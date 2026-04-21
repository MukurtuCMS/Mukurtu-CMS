<?php

namespace Drupal\Tests\message\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests admin menus for the message module.
 *
 * @group message_subscribe
 */
class MenuTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['message'];

  /**
   * Default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'claro';

  /**
   * Test that the menu links are working properly.
   */
  public function testMenuLinks() {
    $admin = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($admin);

    // Link should appear on main config page.
    $this->drupalGet(Url::fromRoute('system.admin_config'));
    $this->assertSession()->linkExists('Message');

    // Link should be on the message-specific overview page.
    $this->drupalGet(Url::fromRoute('message.main_settings'));
    $this->assertSession()->linkExists('Message');

    $this->clickLink('Message');
    $this->assertSession()->statusCodeEquals(200);
  }

}
