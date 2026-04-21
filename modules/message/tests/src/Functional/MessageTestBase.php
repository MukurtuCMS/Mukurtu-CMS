<?php

namespace Drupal\Tests\message\Functional;

use Drupal\message\Entity\MessageTemplate;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\message\Kernel\MessageTemplateCreateTrait;

/**
 * Holds set of tools for the message testing.
 */
abstract class MessageTestBase extends BrowserTestBase {

  use MessageTemplateCreateTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['message', 'views'];

  /**
   * The node access controller.
   *
   * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface
   */
  protected $accessController;

  /**
   * Default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'claro';

  /**
   * Load a message template easily.
   *
   * @param string $template
   *   The template of the message.
   *
   * @return \Drupal\message\Entity\MessageTemplate
   *   The message Object.
   */
  protected function loadMessageTemplate($template) {
    return MessageTemplate::load($template);
  }

  /**
   * Return a config setting.
   *
   * @param string $config
   *   The config value.
   * @param string $storage
   *   The storing of the configuration. Default to message.message.
   *
   * @return mixed
   *   The value of the config.
   */
  protected function getConfig($config, $storage = 'message.settings') {
    return \Drupal::config($storage)->get($config);
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
  protected function configSet($config, $value, $storage = 'message.settings') {
    \Drupal::configFactory()->getEditable($storage)->set($config, $value);
  }

}
