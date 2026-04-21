<?php

namespace Drupal\Tests\token\Functional;

use Drupal\Tests\menu_ui\Functional\MenuUiNodeTest;

/**
 * Tests Menu UI and Content Moderation integration.
 *
 * @group token
 */
class TokenMenuUiNodeTest extends MenuUiNodeTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['token'];

}
