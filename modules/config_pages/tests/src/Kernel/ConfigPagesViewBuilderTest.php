<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\Core\Render\Element;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPagesViewBuilder.
 *
 * Verifies that the #langcode property is set correctly on field render
 * arrays for config pages using a language context. Tests cover entity-level
 * rendering (buildComponents), field-level rendering (viewField), and the
 * loader service (getFieldView).
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesViewBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'link',
    'config_pages',
  ];

  /**
   * The config page type.
   *
   * @var \Drupal\config_pages\Entity\ConfigPagesType
   */
  protected ConfigPagesType $configPageType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('config_pages');
    $this->installEntitySchema('config_pages_type');
    $this->installConfig(['field', 'system', 'filter', 'text']);

    // Create a config page type.
    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_vb',
      'label' => 'Test ViewBuilder Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $this->configPageType->save();

    // Create a string field.
    FieldStorageConfig::create([
      'field_name' => 'field_string',
      'entity_type' => 'config_pages',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_string',
      'entity_type' => 'config_pages',
      'bundle' => 'test_vb',
      'label' => 'String Field',
    ])->save();

    // Create a text_long field.
    FieldStorageConfig::create([
      'field_name' => 'field_body',
      'entity_type' => 'config_pages',
      'type' => 'text_long',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_body',
      'entity_type' => 'config_pages',
      'bundle' => 'test_vb',
      'label' => 'Body Field',
    ])->save();

    // Create a link field.
    FieldStorageConfig::create([
      'field_name' => 'field_link',
      'entity_type' => 'config_pages',
      'type' => 'link',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_link',
      'entity_type' => 'config_pages',
      'bundle' => 'test_vb',
      'label' => 'Link Field',
    ])->save();

    // Set up entity view display for all fields.
    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
    $display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->create([
        'targetEntityType' => 'config_pages',
        'bundle' => 'test_vb',
        'mode' => 'default',
        'status' => TRUE,
      ]);
    $display->setComponent('field_string', ['type' => 'string'])
      ->setComponent('field_body', [
        'type' => 'text_default',
        'settings' => [],
      ])
      ->setComponent('field_link', ['type' => 'link'])
      ->save();
  }

  /**
   * Creates a config page entity with given context and field values.
   *
   * @param string $context
   *   Serialized context string.
   * @param array $field_values
   *   Field values to set.
   *
   * @return \Drupal\config_pages\Entity\ConfigPages
   *   The saved config page entity.
   */
  protected function createConfigPage(string $context, array $field_values = []): ConfigPages {
    $values = [
      'type' => 'test_vb',
      'label' => 'Test Page',
      'context' => $context,
    ] + $field_values;

    $entity = ConfigPages::create($values);
    $entity->save();
    return $entity;
  }

  /**
   * Tests entity rendering sets #langcode on all field types.
   */
  public function testEntityRenderingSetsLangcode(): void {
    $entity = $this->createConfigPage(
      serialize([['language' => 'fr']]),
      [
        'field_string' => 'Test string',
        'field_body' => [
          'value' => '<p>Body content</p>',
          'format' => 'plain_text',
        ],
        'field_link' => [
          'uri' => 'https://example.com',
          'title' => 'Example',
        ],
      ]
    );

    $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder('config_pages');
    $build = $viewBuilder->view($entity, 'default');
    // Trigger pre_render callbacks to run buildMultiple/buildComponents.
    $build = \Drupal::service('renderer')->renderInIsolation($build);

    // Re-build to inspect the render array before final rendering.
    $buildArray = $viewBuilder->view($entity, 'default');
    // Execute #pre_render callbacks manually.
    if (isset($buildArray['#pre_render'])) {
      foreach ($buildArray['#pre_render'] as $callable) {
        $buildArray = call_user_func($callable, $buildArray);
      }
    }

    // Verify #langcode is set on each field item.
    $this->assertFieldItemsLangcode($buildArray['field_string'], 'fr', 'String field');
    $this->assertFieldItemsLangcode($buildArray['field_body'], 'fr', 'Body field');
    $this->assertFieldItemsLangcode($buildArray['field_link'], 'fr', 'Link field');
  }

  /**
   * Tests viewField path sets #langcode (used by getFieldView).
   */
  public function testViewFieldSetsLangcode(): void {
    $entity = $this->createConfigPage(
      serialize([['language' => 'de']]),
      [
        'field_string' => 'Test string DE',
        'field_body' => [
          'value' => '<p>German body</p>',
          'format' => 'plain_text',
        ],
      ]
    );

    $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder('config_pages');

    // Test viewField for string field.
    $stringBuild = $viewBuilder->viewField($entity->get('field_string'));
    $this->assertFieldItemsLangcode($stringBuild, 'de', 'String via viewField');

    // Test viewField for body field.
    $bodyBuild = $viewBuilder->viewField($entity->get('field_body'));
    $this->assertFieldItemsLangcode($bodyBuild, 'de', 'Body via viewField');
  }

  /**
   * Tests getFieldView from loader service sets #langcode.
   */
  public function testLoaderServiceGetFieldViewSetsLangcode(): void {
    $entity = $this->createConfigPage(
      serialize([['language' => 'es']]),
      [
        'field_string' => 'Spanish string',
      ]
    );

    /** @var \Drupal\config_pages\ConfigPagesLoaderServiceInterface $loader */
    $loader = \Drupal::service('config_pages.loader');
    // Pass entity directly to avoid context-based lookup issues in tests.
    $build = $loader->getFieldView($entity, 'field_string');

    $this->assertFieldItemsLangcode($build, 'es', 'String via loader service');
  }

  /**
   * Tests no langcode change when entity has no language context.
   */
  public function testNoLangcodeWithoutLanguageContext(): void {
    $entity = $this->createConfigPage(
      serialize([['some_other_context' => 'value']]),
      [
        'field_string' => 'No lang context',
      ]
    );

    $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder('config_pages');
    $build = $viewBuilder->viewField($entity->get('field_string'));

    // Should not have our custom langcode set.
    foreach (Element::children($build) as $delta) {
      $this->assertNotEquals('fr', $build[$delta]['#langcode'] ?? NULL);
    }
  }

  /**
   * Tests no langcode change when entity has empty context.
   */
  public function testNoLangcodeWithEmptyContext(): void {
    $entity = $this->createConfigPage(
      serialize([]),
      [
        'field_string' => 'Empty context',
      ]
    );

    $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder('config_pages');
    $build = $viewBuilder->viewField($entity->get('field_string'));

    foreach (Element::children($build) as $delta) {
      $this->assertNotEquals('fr', $build[$delta]['#langcode'] ?? NULL);
    }
  }

  /**
   * Tests no langcode change when context is corrupted.
   */
  public function testNoLangcodeWithCorruptedContext(): void {
    $entity = $this->createConfigPage(
      serialize('not_an_array'),
      [
        'field_string' => 'Corrupted context',
      ]
    );

    $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder('config_pages');
    $build = $viewBuilder->viewField($entity->get('field_string'));

    foreach (Element::children($build) as $delta) {
      $this->assertNotEquals('fr', $build[$delta]['#langcode'] ?? NULL);
    }
  }

  /**
   * Tests language context found among multiple context items.
   */
  public function testLanguageContextAmongMultipleContexts(): void {
    $entity = $this->createConfigPage(
      serialize([
        ['theme' => 'olivero'],
        ['language' => 'uk'],
      ]),
      [
        'field_string' => 'Multi context test',
        'field_body' => [
          'value' => '<p>Ukrainian body</p>',
          'format' => 'plain_text',
        ],
      ]
    );

    $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder('config_pages');

    $stringBuild = $viewBuilder->viewField($entity->get('field_string'));
    $this->assertFieldItemsLangcode($stringBuild, 'uk', 'String with multi-context');

    $bodyBuild = $viewBuilder->viewField($entity->get('field_body'));
    $this->assertFieldItemsLangcode($bodyBuild, 'uk', 'Body with multi-context');
  }

  /**
   * Tests all field types get correct #langcode simultaneously via entity view.
   */
  public function testAllFieldTypesSimultaneously(): void {
    $entity = $this->createConfigPage(
      serialize([['language' => 'ja']]),
      [
        'field_string' => 'Japanese string',
        'field_body' => [
          'value' => '<p>Japanese body</p>',
          'format' => 'plain_text',
        ],
        'field_link' => [
          'uri' => 'https://example.jp',
          'title' => 'Japan Link',
        ],
      ]
    );

    $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder('config_pages');
    $buildArray = $viewBuilder->view($entity, 'default');

    // Execute #pre_render callbacks.
    if (isset($buildArray['#pre_render'])) {
      foreach ($buildArray['#pre_render'] as $callable) {
        $buildArray = call_user_func($callable, $buildArray);
      }
    }

    $this->assertFieldItemsLangcode($buildArray['field_string'], 'ja', 'String');
    $this->assertFieldItemsLangcode($buildArray['field_body'], 'ja', 'Body');
    $this->assertFieldItemsLangcode($buildArray['field_link'], 'ja', 'Link');
  }

  /**
   * Tests link field rendering via viewField.
   */
  public function testLinkFieldViewField(): void {
    $entity = $this->createConfigPage(
      serialize([['language' => 'pt']]),
      [
        'field_link' => [
          'uri' => 'https://example.pt',
          'title' => 'Portugal Link',
        ],
      ]
    );

    $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder('config_pages');
    $linkBuild = $viewBuilder->viewField($entity->get('field_link'));
    $this->assertFieldItemsLangcode($linkBuild, 'pt', 'Link via viewField');
  }

  /**
   * Asserts field render array children have the expected #langcode.
   *
   * @param array $build
   *   The field render array.
   * @param string $expected_langcode
   *   The expected language code.
   * @param string $field_label
   *   Label for assertion messages.
   */
  protected function assertFieldItemsLangcode(array $build, string $expected_langcode, string $field_label): void {
    $children = Element::children($build);
    $this->assertNotEmpty($children, "$field_label should have children.");

    foreach ($children as $delta) {
      $this->assertEquals(
        $expected_langcode,
        $build[$delta]['#langcode'] ?? NULL,
        "$field_label item $delta should have #langcode '$expected_langcode'."
      );
    }
  }

}
