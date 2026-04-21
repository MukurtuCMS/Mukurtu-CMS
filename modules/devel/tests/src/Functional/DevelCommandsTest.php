<?php

namespace Drupal\Tests\devel\Functional;

use Drupal\devel\Drush\Commands\DevelServicesCommand;
use Drupal\devel\Drush\Commands\DevelTokenCommand;
use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test class for the Devel drush commands.
 *
 * Note: Drush must be installed. Add it to your require-dev in composer.json.
 */
#[Group('devel')]
class DevelCommandsTest extends BrowserTestBase {

  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['devel'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests drush commands.
   */
  public function testCommands(): void {
    $this->drush(DevelTokenCommand::NAME, [], ['format' => 'json']);
    $output = $this->getOutputFromJSON();
    $tokens = array_column($output, 'token');
    $this->assertContains('account-name', $tokens);

    $this->drush(DevelServicesCommand::NAME, [], ['format' => 'json']);
    $output = $this->getOutput();
    $this->assertStringContainsString('current_user', $output);
  }

}
