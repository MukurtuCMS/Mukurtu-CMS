<?php

declare(strict_types=1);

namespace Drupal\Tests\genpass\Kernel;

use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\genpass\GenpassInterface;

/**
 * Tests the validation constraints on config when called from code/API.
 *
 * @group genpass
 */
class ConfigValidation extends KernelTestBase {

  use SchemaCheckTrait;

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = TRUE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'genpass',
    'system',
    'user',
  ];

  /**
   * Defines the configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Manages config schema type plugins.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfig;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Add config for genpass.
    $this->installConfig([
      'genpass',
      'user',
    ]);

    // Required services.
    $this->configFactory = $this->container->get('config.factory');
    $this->typedConfig = $this->container->get('config.typed');
  }

  /**
   * Test config updates with validation.
   *
   * @param array $config
   *   Array of config name and keys/value pairs to set and then validate.
   * @param array $expects
   *   Array of expected validation results.
   *
   * @dataProvider configUpdateDataProvider
   */
  public function testConfigUpdate(array $config, array $expects) {
    // Try updating all of the provided configuration settings.
    foreach ($config as $config_name => $settings) {
      $config = $this->configFactory->getEditable($config_name);

      foreach ($settings as $name => $value) {
        $config->set($name, $value);
      }

      // Save the config to cast all values to correct types.
      $config->save();

      // Check for violations.
      $violations = $this->checkConfigSchema(
        $this->typedConfig,
        $config_name,
        $config->get(),
        TRUE
      );

      // Debugging helper to provide the actual error message to help fix it.
      if (is_array($violations) && (is_bool($expects[$config_name]) || $expects[$config_name] != $violations)) {
        dump($violations);
      }

      // (2nd param) does not match expected type "1st param".
      $this->assertEquals($expects[$config_name], $violations);
    }
  }

  /**
   * Data provider for estGenpassConfigs.
   *
   * @return \Generator
   *   An array of configuration options to set, and expected page text output.
   */
  public static function configUpdateDataProvider(): \Generator {
    // Tests GenpassModeConstraint where verify_mail is also checked.
    yield [
      [
        'user.settings' => [
          'verify_mail' => 1,
        ],
        'genpass.settings' => [
          'genpass_mode' => GenpassInterface::PASSWORD_REQUIRED,
        ],
      ],
      [
        'user.settings' => TRUE,
        'genpass.settings' => [
          '[genpass_mode] User password entry option <em class="placeholder">Users must enter a password on registration</em> is not available when email verification is enabled.',
        ],
      ],
    ];
    // Tests GenpassModeConstraint where verify_mail is NOT checked.
    yield [
      [
        'genpass.settings' => [
          'genpass_mode' => GenpassInterface::PASSWORD_REQUIRED,
        ],
      ],
      [
        'genpass.settings' => [
          '[genpass_mode] User password entry option <em class="placeholder">Users must enter a password on registration</em> is not available when email verification is enabled.',
        ],
      ],
    ];
    // Tests GenpassModeConstraint where verify_mail is also checked.
    yield [
      [
        'user.settings' => [
          'verify_mail' => 1,
        ],
        'genpass.settings' => [
          'genpass_mode' => GenpassInterface::PASSWORD_OPTIONAL,
        ],
      ],
      [
        'user.settings' => TRUE,
        'genpass.settings' => [
          '[genpass_mode] User password entry option <em class="placeholder">Users may enter a password on registration</em> is not available when email verification is enabled.',
        ],
      ],
    ];
    // Tests GenpassModeConstraint where verify_mail is disabled.
    yield [
      [
        'user.settings' => [
          'verify_mail' => 0,
        ],
        'genpass.settings' => [
          'genpass_mode' => GenpassInterface::PASSWORD_REQUIRED,
        ],
      ],
      [
        'user.settings' => TRUE,
        'genpass.settings' => TRUE,
      ],
    ];
    // Tests genpass_mode is out of range.
    yield [
      [
        'genpass.settings' => [
          'genpass_mode' => 3,
        ],
      ],
      [
        'genpass.settings' => [
          '[genpass_mode] This value should be between <em class="placeholder">0</em> and <em class="placeholder">2</em>.',
        ],
      ],
    ];
    // Password length out of range.
    yield [
      [
        'genpass.settings' => [
          'genpass_length' => 3,
        ],
      ],
      [
        'genpass.settings' => [
          '[genpass_length] This value should be between <em class="placeholder">5</em> and <em class="placeholder">32</em>.',
        ],
      ],
    ];
    // Admin mode out of range.
    yield [
      [
        'genpass.settings' => [
          'genpass_admin_mode' => 3,
        ],
      ],
      [
        'genpass.settings' => [
          '[genpass_admin_mode] This value should be between <em class="placeholder">1</em> and <em class="placeholder">2</em>.',
        ],
      ],
    ];
    // Display out of range.
    yield [
      [
        'genpass.settings' => [
          'genpass_display' => 8675309,
        ],
      ],
      [
        'genpass.settings' => [
          '[genpass_display] This value should be between <em class="placeholder">0</em> and <em class="placeholder">3</em>.',
        ],
      ],
    ];
  }

}
