<?php

namespace Drupal\Tests\message_ui\Functional;

use Drupal\Tests\message\Functional\MessageTestBase;

/**
 * Abstract class for Message UI tests.
 */
abstract class AbstractTestMessageUi extends MessageTestBase {

  /**
   * The user account object.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['message', 'message_ui'];

  /**
   * The user role.
   *
   * @var int
   */
  protected $rid;

  /**
   * Grant to the user a specific permission.
   *
   * @param string $operation
   *   The template of operation - create, update, delete or view.
   * @param string $template
   *   The message template.
   */
  protected function grantMessageUiPermission($operation, $template = 'foo') {
    user_role_grant_permissions($this->rid, [$operation . ' ' . $template . ' message']);
  }

  /**
   * Set a config value.
   *
   * @param string $config
   *   The config name.
   * @param string $value
   *   The config value.
   * @param string $storage
   *   The storing of the configuration. Default to message.message.
   */
  protected function configSet($config, $value, $storage = 'message_ui.settings') {
    $this->container->get('config.factory')->getEditable($storage)->set($config, $value)->save();
  }

}
