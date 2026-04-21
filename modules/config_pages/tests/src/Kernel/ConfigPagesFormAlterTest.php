<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for config_pages_form_field_storage_config_edit_form_alter().
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesFormAlterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'config_pages',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('config_pages');
    $this->installEntitySchema('config_pages_type');
    $this->installConfig(['field', 'system']);

    // Ensure the .module file is loaded.
    \Drupal::moduleHandler()->loadInclude('config_pages', 'module');
  }

  /**
   * Tests that config_pages is removed from entity reference target options.
   */
  public function testConfigPagesRemovedFromTargetType(): void {
    $form = [
      'settings' => [
        'target_type' => [
          '#options' => [
            'Content' => [
              'node' => 'Content',
              'config_pages' => 'Config Pages',
            ],
            'Other' => [
              'user' => 'User',
            ],
          ],
        ],
      ],
    ];
    $form_state = new FormState();

    config_pages_form_field_storage_config_edit_form_alter(
      $form,
      $form_state,
      'field_storage_config_edit_form'
    );

    // config_pages should be removed from options.
    $this->assertArrayNotHasKey(
      'config_pages',
      $form['settings']['target_type']['#options']['Content']
    );

    // Other options should remain intact.
    $this->assertArrayHasKey('node', $form['settings']['target_type']['#options']['Content']);
    $this->assertArrayHasKey('user', $form['settings']['target_type']['#options']['Other']);
  }

  /**
   * Tests alter does nothing when options are empty.
   */
  public function testAlterWithEmptyOptions(): void {
    $form = [
      'settings' => [
        'target_type' => [
          '#options' => [],
        ],
      ],
    ];
    $form_state = new FormState();

    config_pages_form_field_storage_config_edit_form_alter(
      $form,
      $form_state,
      'field_storage_config_edit_form'
    );

    $this->assertEmpty($form['settings']['target_type']['#options']);
  }

  /**
   * Tests alter does nothing when options key is missing.
   */
  public function testAlterWithNoOptionsKey(): void {
    $form = [
      'settings' => [
        'target_type' => [
          '#type' => 'select',
        ],
      ],
    ];
    $form_state = new FormState();

    // Should not throw an error.
    config_pages_form_field_storage_config_edit_form_alter(
      $form,
      $form_state,
      'field_storage_config_edit_form'
    );

    $this->assertArrayNotHasKey('#options', $form['settings']['target_type']);
  }

  /**
   * Tests alter when config_pages is in a flat options array.
   */
  public function testAlterWithFlatOptions(): void {
    $form = [
      'settings' => [
        'target_type' => [
          '#options' => [
            'node' => 'Content',
            'config_pages' => 'Config Pages',
          ],
        ],
      ],
    ];
    $form_state = new FormState();

    config_pages_form_field_storage_config_edit_form_alter(
      $form,
      $form_state,
      'field_storage_config_edit_form'
    );

    // Flat options are not grouped arrays, so they should not be affected.
    $this->assertArrayHasKey('config_pages', $form['settings']['target_type']['#options']);
  }

  /**
   * Tests alter when config_pages is in multiple groups.
   */
  public function testAlterRemovesFromAllGroups(): void {
    $form = [
      'settings' => [
        'target_type' => [
          '#options' => [
            'Group A' => [
              'config_pages' => 'Config Pages',
              'node' => 'Content',
            ],
            'Group B' => [
              'config_pages' => 'Config Pages Duplicate',
              'user' => 'User',
            ],
          ],
        ],
      ],
    ];
    $form_state = new FormState();

    config_pages_form_field_storage_config_edit_form_alter(
      $form,
      $form_state,
      'field_storage_config_edit_form'
    );

    $this->assertArrayNotHasKey('config_pages', $form['settings']['target_type']['#options']['Group A']);
    $this->assertArrayNotHasKey('config_pages', $form['settings']['target_type']['#options']['Group B']);
  }

}
