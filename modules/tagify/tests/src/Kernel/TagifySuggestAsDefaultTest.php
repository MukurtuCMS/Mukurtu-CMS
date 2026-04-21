<?php

declare(strict_types=1);

namespace Drupal\Tests\tagify\Kernel;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\node\Entity\NodeType;

/**
 * Tests tagify suggests as default settings.
 *
 * @group tagify
 */
class TagifySuggestAsDefaultTest extends TagifyKernelTestBase {

  /**
   * Tests that the default widget is correctly set for entity reference fields.
   *
   * @dataProvider providerTestDefaultWidgetBehavior
   */
  public function testDefaultWidgetBehavior(bool $set_default_widget): void {
    // Set config BEFORE creating the field.
    $this->configFactory->getEditable('tagify.settings')
      ->set('set_default_widget', $set_default_widget)
      ->save();

    $this->fieldTypeManager->clearCachedDefinitions();

    $content_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ]);
    try {
      $content_type->save();
    }
    catch (EntityStorageException) {
      $this->fail('Failed to save content type.');
    }

    $field_name = 'field_test_reference';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ]);
    try {
      $field_storage->save();
    }
    catch (EntityStorageException) {
      $this->fail('Failed to save field storage config.');
    }

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => 'Test Reference Field',
    ]);
    try {
      $field->save();
    }
    catch (EntityStorageException) {
      $this->fail('Failed to save field config.');
    }

    // Ensure the form display exists.
    $form_display = EntityFormDisplay::load('node.article.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => 'article',
        'mode' => 'default',
      ]);
      try {
        $form_display->save();
      }
      catch (EntityStorageException) {
        $this->fail('Failed to save form display.');
      }
    }

    $form_display->setComponent($field_name);
    try {
      $field_type_info = $this->fieldTypeManager->getDefinition($field_storage->getType());
    }
    catch (PluginNotFoundException) {
      $this->fail('Failed to get field type definition.');
    }

    if ($set_default_widget) {
      $this->assertEquals('tagify_entity_reference_autocomplete_widget', $field_type_info['default_widget']);
    }
    else {
      $this->assertNotEquals('tagify_entity_reference_autocomplete_widget', $field_type_info['default_widget']);
    }
  }

  /**
   * Data provider for providerTestDefaultWidgetBehavior().
   *
   * @return array
   *   The data.
   */
  public static function providerTestDefaultWidgetBehavior(): array {
    return [
      [TRUE],
      [FALSE],
    ];
  }

}
