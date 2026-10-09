<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40211(), which adds the language code field.
 *
 * @see mukurtu_core_update_40211()
 */
#[Group('mukurtu_core')]
class LanguageCodeFieldUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'path',
    'path_alias',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'field', 'filter']);

    // Required directly rather than via loadInclude(); see
    // CategoryAdminLinksCascadeUpdateTest::setUp() for why.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_core.install';
  }

  /**
   * A site with no saved form display gets the field and the shipped display.
   */
  public function testAddsFieldAndFormDisplay(): void {
    Vocabulary::create(['vid' => 'language', 'name' => 'Language'])->save();

    $this->assertNotNull(mukurtu_core_update_40211());

    $this->assertSame('string', FieldStorageConfig::loadByName('taxonomy_term', 'field_language_code')?->getType());
    $field = FieldConfig::loadByName('taxonomy_term', 'language', 'field_language_code');
    $this->assertSame('Language code', $field?->getLabel());
    $this->assertFalse($field->isTranslatable());

    $form = EntityFormDisplay::load('taxonomy_term.language.default');
    $this->assertSame('string_textfield', $form->getComponent('field_language_code')['type'] ?? NULL);
    $this->assertNotNull($form->getComponent('name'));
  }

  /**
   * A site's own form display keeps its arrangement and gains only the field.
   */
  public function testKeepsExistingFormDisplay(): void {
    Vocabulary::create(['vid' => 'language', 'name' => 'Language'])->save();
    EntityFormDisplay::create([
      'targetEntityType' => 'taxonomy_term',
      'bundle' => 'language',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('name', ['type' => 'string_textfield', 'weight' => 7])->save();

    mukurtu_core_update_40211();
    // Running it twice is harmless.
    mukurtu_core_update_40211();

    $form = EntityFormDisplay::load('taxonomy_term.language.default');
    $this->assertSame(7, $form->getComponent('name')['weight']);
    $this->assertSame(20, $form->getComponent('field_language_code')['settings']['size'] ?? NULL);
  }

  /**
   * A site without the language vocabulary is left alone.
   */
  public function testSkipsWithoutVocabulary(): void {
    $this->assertNull(mukurtu_core_update_40211());
    $this->assertNull(FieldStorageConfig::loadByName('taxonomy_term', 'field_language_code'));
  }

}
