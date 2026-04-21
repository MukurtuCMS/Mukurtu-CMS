<?php

declare(strict_types=1);

namespace Drupal\Tests\color_field\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Provide basic setup for all color field functional tests.
 *
 * @group color_field
 */
abstract class ColorFieldFunctionalTestBase extends BrowserTestBase {

  /**
   * The Entity View Display for the article node type.
   *
   * @var \Drupal\Core\Entity\Entity\EntityViewDisplay
   */
  protected EntityViewDisplay $display;

  /**
   * The Entity Form Display for the article node type.
   *
   * @var \Drupal\Core\Entity\Entity\EntityFormDisplay
   */
  protected EntityFormDisplay $form;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'node',
    'color_field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article']);
    $user = $this->drupalCreateUser([
      'create article content', 'edit own article content',
    ]);
    $this->drupalLogin($user);
    $entityTypeManager = $this->container->get('entity_type.manager');
    FieldStorageConfig::create([
      'field_name' => 'field_color',
      'entity_type' => 'node',
      'type' => 'color_field_type',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_color',
      'label' => 'Freeform Color',
      'description' => 'Color field description',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();
    $this->form = $entityTypeManager->getStorage('entity_form_display')
      ->load('node.article.default');
    $this->display = $entityTypeManager->getStorage('entity_view_display')
      ->load('node.article.default');
  }

}
