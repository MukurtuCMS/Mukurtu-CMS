<?php

declare(strict_types=1);

namespace Drupal\Tests\blazy\Traits;

use Drupal\blazy\Blazy;
use Drupal\blazy\internals\Internals;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * A Trait common for Blazy tests.
 *
 * @todo Consider using TestFileCreationTrait.
 */
trait BlazyCreationTestTrait {

  use ContentTypeCreationTrait;
  use EntityReferenceFieldCreationTrait;
  use NodeCreationTrait;

  /**
   * Testing node type.
   *
   * @var \Drupal\node\Entity\NodeType|null
   */
  protected $nodeType = NULL;

  /**
   * Setup formatter displays, default to image, and update its settings.
   *
   * @param string $bundle
   *   The bundle name.
   * @param array $data
   *   May contain formatter settings to be added to defaults.
   *
   * @return \Drupal\Core\Entity\Entity\EntityViewDisplay
   *   The formatter display instance.
   */
  protected function setUpFormatterDisplay($bundle = '', array $data = []) {
    $settings   = $data['settings'] ?? [];
    $view_mode  = empty($data['view_mode']) ? 'default' : $data['view_mode'];
    $plugin_id  = empty($data['plugin_id']) ? $this->testPluginId : $data['plugin_id'];
    $field_name = empty($data['field_name']) ? $this->testFieldName : $data['field_name'];
    $display_id = $this->entityType . '.' . $bundle . '.' . $view_mode;
    $storage    = $this->blazyManager->getStorage('entity_view_display');
    $display    = $storage->load($display_id);

    if (!$display) {
      $values = [
        'targetEntityType' => $this->entityType,
        'bundle'           => $bundle,
        'mode'             => $view_mode,
        'status'           => TRUE,
      ];

      $display = $storage->create($values);
    }

    $settings['view_mode'] = $view_mode;
    $display->setComponent($field_name, [
      'type'     => $plugin_id,
      'settings' => $settings,
      'label'    => 'hidden',
    ]);

    $display->save();

    return $display;
  }

  /**
   * Gets the field definition.
   *
   * @param string $field_name
   *   Formatted field name.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   The field definition.
   *
   * @see BaseFieldDefinition::createFromFieldStorageDefinition()
   */
  protected function getBlazyFieldDefinition($field_name = '') {
    $field_name = empty($field_name) ? $this->testFieldName : $field_name;
    $field_storage_config = $this->getBlazyFieldStorageDefinition($field_name);
    return $field_storage_config ? BaseFieldDefinition::createFromFieldStorageDefinition($field_storage_config) : FALSE;
  }

  /**
   * Gets the field storage configuration.
   *
   * @param string $field_name
   *   Formatted field name.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface|null
   *   The field storage definition.
   */
  protected function getBlazyFieldStorageDefinition($field_name = '') {
    $field_name = empty($field_name) ? $this->testFieldName : $field_name;
    $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($this->entityType);
    return $field_storage_definitions[$field_name] ?? NULL;
  }

  /**
   * Returns the field formatter instance.
   *
   * @param string $plugin_id
   *   Formatter plugin ID.
   * @param string $field_name
   *   Formatted field name.
   *
   * @return \Drupal\blazy\BlazyFormatterInterface|\Drupal\Core\Field\FormatterInterface|null
   *   The field formatter instance.
   */
  protected function getFormatterInstance($plugin_id = '', $field_name = '') {
    $plugin_id  = empty($plugin_id) ? $this->testPluginId : $plugin_id;
    $field_name = empty($field_name) ? $this->testFieldName : $field_name;
    $settings   = $this->getFormatterSettings() + $this->formatterPluginManager->getDefaultSettings($plugin_id);

    if (!$this->getBlazyFieldDefinition($field_name)) {
      return NULL;
    }

    $options = [
      'field_definition' => $this->getBlazyFieldDefinition($field_name),
      'configuration' => [
        'type' => $plugin_id,
        'settings' => $settings,
      ],
      'view_mode' => 'default',
    ];

    return $this->formatterPluginManager->getInstance($options);
  }

  /**
   * Build dummy content types.
   *
   * @param string $bundle
   *   The bundle name.
   * @param array $settings
   *   (Optional) configurable settings.
   */
  protected function setUpContentTypeTest($bundle = '', array $settings = []) {
    $bundle = $bundle ?: $this->bundle;
    $values = [
      'type' => $bundle,
      'name' => str_replace('_', ' ', $bundle),
    ];

    $node_type = NodeType::load($bundle);
    if (!$node_type) {
      $node_type = $this->createContentType($values);
    }

    $this->setupFilterFormat();

    $data = $settings;
    $settings['fields']['body'] = 'text_with_summary';
    $data['body_settings'] = [
      'display_summary' => TRUE,
      'allowed_formats' => ['restricted_html', 'full_html'],
    ];

    if (!empty($this->testFieldName)) {
      $settings['fields'][$this->testFieldName] = empty($this->testFieldType) ? 'image' : $this->testFieldType;
    }
    if (!empty($settings['field_name']) && !empty($settings['field_type'])) {
      $settings['fields'][$settings['field_name']] = $settings['field_type'];
    }

    if ($fields = $settings['fields'] ?? []) {
      foreach ($fields as $field_name => $field_type) {
        $data['field_name'] = $field_name;
        $data['field_type'] = $field_type;
        $this->setUpFieldConfig($bundle, $data);
      }
    }

    $this->nodeType = $node_type;
    return $node_type;
  }

  /**
   * Build dummy nodes with optional fields.
   *
   * @param string $bundle
   *   The bundle name.
   * @param array $settings
   *   (Optional) configurable settings.
   *
   * @return \Drupal\node\NodeInterface|\Drupal\node\Entity\Node|null
   *   The node instance.
   */
  protected function setUpContentWithItems($bundle = '', array $settings = []) {
    $bundle = $bundle ?: $this->bundle;
    $title = $settings['title'] ?? $this->testPluginId;
    $data = $settings['values'] ?? [];
    $count = $this->maxParagraphs / 2;
    $text = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
    $text .= $this->getRandomGenerator()->paragraphs($count);
    $text .= $this->randomParagraphs($count);

    if ($extra_text = $settings['extra_text'] ?? NULL) {
      $text .= $extra_text;
    }

    $values = $data + [
      'title'  => $title . ' : ' . $this->randomMachineName(),
      'type'   => $bundle,
      'status' => TRUE,
      // Prevents ::createNode from early setup, otherwise breaking the flow.
      'body' => [],
    ];

    $node = $this->createNode($values);

    if (!empty($this->testFieldName)) {
      $settings['fields'][$this->testFieldName] = empty($this->testFieldType) ? 'image' : $this->testFieldType;
    }
    if (!empty($settings['field_name']) && !empty($settings['field_type'])) {
      $settings['fields'][$settings['field_name']] = $settings['field_type'];
    }

    $settings['fields']['body'] = 'text_with_summary';
    if ($fields = $settings['fields'] ?? []) {
      foreach ($fields as $field_name => $field_type) {
        $multiple = $field_type == 'image' || strpos($field_name, 'mul') !== FALSE;

        if (strpos($field_name, 'empty') !== FALSE) {
          continue;
        }

        if ($field_name == $this->entityFieldName) {
          continue;
        }

        // @see \Drupal\Core\Field\FieldItemListInterface::generateSampleItems
        $max = $multiple ? $this->maxItems : 2;
        if ($node->hasField($field_name)) {
          /** @phpstan-ignore-next-line */
          if ($field = $node->get($field_name)) {
            if ($field_name == 'body') {
              $body = ['value' => $text, 'format' => 'full_html'];
              $node->set('body', $body);
            }
            else {
              $field->generateSampleItems($max);
            }
          }
        }
      }
    }

    $node->save();
    $this->testItems = $node->{$this->testFieldName};
    $this->entity = $node;

    return $node;
  }

  /**
   * Sets field values as built by FieldItemListInterface::view().
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $referenced_entities
   *   An array of entity objects that will be referenced.
   * @param string $type
   *   The formatter plugin ID.
   * @param array $settings
   *   Settings specific to the formatter. Defaults to the formatter's defaults.
   *
   * @return array
   *   A render array.
   */
  protected function buildEntityReferenceRenderArray(array $referenced_entities, $type = '', array $settings = []) {
    $type = empty($type) ? $this->entityPluginId : $type;
    /** @phpstan-ignore-next-line */
    $items = $this->referencingEntity->get($this->entityFieldName);

    // Assign the referenced entities.
    foreach ($referenced_entities as $referenced_entity) {
      $items[] = ['entity' => $referenced_entity];
    }

    // Build the renderable array for the field.
    $data['type'] = $type;
    if ($settings) {
      $data['settings'] = $settings;
    }
    return $items->view($data);
  }

  /**
   * Build dummy contents with entity references.
   *
   * @param array $settings
   *   (Optional) configurable settings.
   */
  protected function setUpContentWithEntityReference(array $settings = []) {
    $target_bundle   = $this->targetBundle;
    $bundle          = $this->bundle;
    $fields          = empty($settings['fields']) ? [] : $settings['fields'];
    $image_settings  = empty($settings['image_settings']) ? [] : $settings['image_settings'];
    $entity_settings = empty($settings['entity_settings']) ? [] : $settings['entity_settings'];
    $er_field_name   = empty($settings['entity_field_name']) ? $this->entityFieldName : $settings['entity_field_name'];
    $er_plugin_id    = empty($settings['entity_plugin_id']) ? $this->entityPluginId : $settings['entity_plugin_id'];

    // Create referenced entity.
    $referenced_data['title'] = 'Referenced ' . $this->testPluginId;

    // Create dummy fields.
    $referenced_data['fields'] = array_merge($this->getDefaultFields(), $fields);

    // Create referenced entity type.
    $this->setUpContentTypeTest($target_bundle, $referenced_data);

    // Create referencing entity type.
    $referencing_data['fields'] = [
      $er_field_name => 'entity_reference',
    ];
    $this->setUpContentTypeTest($bundle, $referencing_data);

    // 1. Build the referenced entities.
    $referenced_formatter_link = [
      'field_name' => 'field_link',
      'plugin_id'  => 'link',
      'settings'   => [],
    ];
    $this->setUpFormatterDisplay($target_bundle, $referenced_formatter_link);

    $referenced_formatter_data = [
      'field_name' => $this->testFieldName,
      'plugin_id'  => $this->testPluginId,
      'settings'   => $image_settings + $this->getFormatterSettings(),
    ];
    $this->referencedDisplay = $this->setUpFormatterDisplay($target_bundle, $referenced_formatter_data);

    // Create referenced entities.
    $this->referencedEntity = $this->setUpContentWithItems($target_bundle, $referenced_data);

    // 2. Build the referencing entity.
    $referencing_formatter_settings = $this->getDefaultFields(TRUE);
    $referencing_formatter_data = [
      'field_name' => $er_field_name,
      'plugin_id'  => $er_plugin_id,
      'settings'   => empty($entity_settings) ? $referencing_formatter_settings : array_merge($referencing_formatter_settings, $entity_settings),
    ];
    $this->referencingDisplay = $this->setUpFormatterDisplay($bundle, $referencing_formatter_data);
  }

  /**
   * Create referencing entity.
   */
  protected function createReferencingEntity(array $data = []) {
    if (empty($data['values']) && $this->referencedEntity->id()) {
      $data['values'] = [
        $this->entityFieldName => [
          ['target_id' => $this->referencedEntity->id()],
        ],
      ];
    }

    return $this->setUpContentWithItems($this->bundle, $data);
  }

  /**
   * Set up dummy image.
   */
  protected function setUpRealImage() {
    /** @phpstan-ignore-next-line */
    $this->uri = $this->getImagePath();
    $item = $this->dummyItem;

    if (isset($this->testItems[0])) {
      $item = $this->testItems[0];

      if ($item instanceof ImageItem) {
        /** @phpstan-ignore-next-line */
        $this->uri = ($entity = $item->entity) && empty($item->uri) ? $entity->getFileUri() : $item->uri;
        $this->url = Blazy::transformRelative($this->uri);
      }
    }

    if (empty($this->url)) {
      $source = $this->root . '/core/misc/druplicon.png';
      $uri = 'public://test.png';
      $replace = Internals::fileExistsReplace();
      $this->fileSystem->copy($source, $uri, $replace);
      $this->url = Blazy::createUrl($uri);
    }

    $this->testItem = $this->image = $item;

    $this->data = [
      '#settings' => $this->getFormatterSettings(),
      '#item'     => $item,
    ];
  }

  /**
   * Returns path to the stored image location.
   */
  protected function getImagePath($is_dir = FALSE) {
    $path            = $this->root . '/sites/default/files/simpletest/' . $this->testPluginId;
    $item            = $this->createDummyImage();
    $this->dummyUrl  = Blazy::transformRelative($this->dummyUri);
    $this->dummyItem = $item;
    $this->dummyData = [
      '#settings' => $this->getFormatterSettings(),
      '#item' => $item,
    ];

    return $is_dir ? $path : $this->dummyUri;
  }

  /**
   * Returns the created image file.
   */
  protected function createDummyImage($name = '', $source = '') {
    $path   = $this->root . '/sites/default/files/simpletest/' . $this->testPluginId;
    $name   = empty($name) ? $this->testPluginId . '.png' : $name;
    $source = empty($source) ? $this->root . '/core/misc/druplicon.png' : $source;
    $uri    = $path . '/' . $name;

    if (!is_file($uri)) {
      $this->prepareTestDirectory();
      $replace = Internals::fileExistsReplace();
      $this->fileSystem->saveData($source, $uri, $replace);
    }

    $uri = 'public://simpletest/' . $this->testPluginId . '/' . $name;
    $this->dummyUri = $uri;
    $item = File::create([
      'uri' => $uri,
      'uid' => 1,
      'status' => FileInterface::STATUS_PERMANENT,
      'filename' => $name,
    ]);

    $item->save();

    return $item;
  }

  /**
   * Prepares test directory to store screenshots, or images.
   */
  protected function prepareTestDirectory() {
    $this->testDirPath = $this->root . '/sites/default/files/simpletest/' . $this->testPluginId;
    $this->fileSystem->prepareDirectory($this->testDirPath, FileSystemInterface::CREATE_DIRECTORY);
  }

  /**
   * Setup a new image field.
   *
   * @param string $bundle
   *   The bundle name.
   * @param array $data
   *   (Optional) A list of field data.
   */
  protected function setUpFieldConfig($bundle = '', array $data = []): void {
    $bundle     = $bundle ?: $this->bundle;
    $default    = empty($this->testFieldType) ? 'image' : $this->testFieldType;
    $field_type = $data['field_type'] ?? $default;
    $field_name = $data['field_name'] ?? $this->testFieldName;
    $config     = $data[$field_name . '_settings'] ?? [];
    $multiple   = strpos($field_name, 'mul') !== FALSE;
    $node_type  = $this->nodeType ?? NodeType::load($bundle);

    if (!$node_type) {
      // This only creates the bundle, nothing else.
      $node_type = $this->createContentType([
        'type' => $bundle,
        'name' => str_replace('_', ' ', $bundle),
      ]);
    }

    $this->nodeType = $node_type;

    $this->ensureFieldCreatedOnce($data, $bundle);
  }

  /**
   * Ensures field being created once.
   */
  protected function ensureFieldCreatedOnce(array $data, string $bundle = 'bundle_test'): void {
    $default    = $this->testFieldType ?: 'image';
    $field_type = $data['field_type'] ?? $default;
    $field_name = $data['field_name'] ?? $this->testFieldName;
    $config     = $data[$field_name . '_settings'] ?? [];
    $multiple   = strpos($field_name, 'mul') !== FALSE;
    $label      = $data['label'] ?? str_replace('_', ' ', $field_name);
    $config     = $data[$field_name . '_settings'] ?? [];
    $storage    = FieldStorageConfig::loadByName($this->entityType, $field_name);

    if (in_array($field_type, ['file', 'image'])) {
      $config['file_directory'] = $this->testPluginId;
      $config['file_extensions'] = 'png gif jpg jpeg';

      if ($field_type == 'file') {
        $config['file_extensions'] .= ' txt';
      }

      if ($field_type == 'image') {
        $config['title_field'] = 1;
        $config['title_field_required'] = 1;
      }

      $multiple = TRUE;
    }

    if ($field_type == 'entity_reference' && !empty($this->targetBundles)) {
      $config['handler'] = 'default';
      $config['handler_settings']['target_bundles'] = $this->targetBundles;
      $config['handler_settings']['sort']['field'] = '_none';
      $bundle = $this->bundle;
    }

    $storage_settings = $data[$field_name . '_storage_settings'] ?? [];
    if ($field_type == 'entity_reference') {
      $storage_settings['target_type'] = $this->targetType ?? $this->entityType;
      $bundle = $this->bundle;
      $multiple = FALSE;
    }

    if ($field_name == 'field_image') {
      $multiple = FALSE;
    }

    if (!$storage) {
      // Create new configurable storage.
      $storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $this->entityType,
        'type' => $field_type,
        'cardinality' => $multiple ? -1 : 1,
        'settings' => $storage_settings,
      ]);
      $storage->save();

      // Only now is it safe to pass field_storage.
      FieldConfig::create([
        'field_storage' => $storage,
        'field_name' => $field_name,
        'entity_type' => $this->entityType,
        'bundle' => $bundle,
        'label' => $label,
        'settings' => $config,
      ])->save();

      if ($field_name == 'body') {
        $this->setupBodyField();
      }
      return;
    }

    // Storage exists and is configurable, attach field config only if missing.
    if (!FieldConfig::loadByName($this->entityType, $bundle, $field_name)) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $this->entityType,
        'bundle' => $bundle,
        'label' => $label,
        'settings' => $config,
      ])->save();

      if ($field_name == 'body') {
        $this->setupBodyField();
      }
    }
  }

  /**
   * Setups body field displays.
   */
  protected function setupBodyField() {
    $type = $this->nodeType;

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = $this->entityDisplayRepository;
    if (!$display_repository) {
      $display_repository = $this->blazyManager->service('entity_display.repository');
    }

    // Assign widget settings for the default form mode.
    $display_repository->getFormDisplay('node', $type->id())
      ->setComponent('body', [
        'type' => 'text_textarea_with_summary',
      ])
      ->save();

    // Assign display settings for the 'default' and 'teaser' view modes.
    $display_repository->getViewDisplay('node', $type->id())
      ->setComponent('body', [
        'label' => 'hidden',
        'type' => 'text_default',
      ])
      ->save();

    // The teaser view mode is created by the Standard profile and therefore
    // might not exist.
    $view_modes = $display_repository->getViewModes('node');
    if (isset($view_modes['teaser'])) {
      $display_repository->getViewDisplay('node', $type->id(), 'teaser')
        ->setComponent('body', [
          'label' => 'hidden',
          'type' => 'text_summary_or_trimmed',
        ])
        ->save();
    }
  }

  /**
   * Add test fields.
   */
  protected function addTestField(
    array $data,
    string $bundle = '',
  ): void {
    $bundle = $bundle ?: $this->bundle;
    $field_name = $data['field_name'] ?? $this->testFieldName;
    $field_type = $data['field_type'] ?? $this->testFieldType;
    $entity_type = $data['entity_type'] ?? $this->entityType;
    $label = $data['label'] ?? str_replace('_', ' ', $field_name);
    $settings = $data['settings'] ?? [];
    $node_type = $this->nodeType ?? NodeType::load($bundle);

    if (!$node_type) {
      // This only creates the bundle, nothing else.
      $node_type = $this->createContentType([
        'type' => $bundle,
        'name' => str_replace('_', ' ', $bundle),
      ]);
    }

    $this->nodeType = $node_type;

    $field_storage = FieldStorageConfig::loadByName(
      $entity_type,
      $field_name
    );
    if (!$field_storage) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'type' => $field_type,
      ])->save();
    }

    $field = FieldConfig::loadByName($entity_type, $bundle, $field_name);
    if (!$field) {
      // Attach body field to the bundle.
      FieldConfig::create([
        'field_name' => $field_name,
        'field_storage' => $field_storage,
        'bundle' => $bundle,
        'label' => $label,
        'settings' => $settings,
      ])->save();
    }
  }

  /**
   * Prepares filter formats.
   */
  protected function setupFilterFormat(): void {
    $full_html = $this->blazyManager->load('full_html', 'filter_format');
    $restricted_html = $this->blazyManager->load('restricted_html', 'filter_format');

    if (!$restricted_html) {
      $restricted_html = FilterFormat::create([
        'format'  => 'restricted_html',
        'name'    => 'Basic HML',
        'weight'  => 2,
        'filters' => [],
      ]);

      $restricted_html->save();
    }

    $this->filterFormatRestricted = $restricted_html;

    if (!$full_html) {
      $full_html = FilterFormat::create([
        'format'  => 'full_html',
        'name'    => 'Full HML',
        'weight'  => 3,
      ]);

      $full_html->save();
    }

    $this->filterFormatFull = $full_html;
  }

  /**
   * Generate random paragraphs.
   *
   * @todo remove once core ::getRandomGenerator()->paragraphs() works again.
   */
  protected function randomParagraphs(
    int $count = 100,
    int $per_paragraph = 5,
    int $max_sentence_chars = 120,
  ): string {
    $words = ['a', 'be', 'to', 'of', 'in', 'it', 'is', 'you', 'that', 'on',
      'for', 'with', 'as', 'are', 'there', 'here', 'oh', 'no', 'yes', 'god',
    ];
    $output = [];

    for ($p = 0; $p < $count; $p++) {
      $sentences = [];

      for ($s = 0; $s < $per_paragraph; $s++) {
        $sentence = '';
        while (strlen($sentence) < $max_sentence_chars) {
          $sentence .= $words[array_rand($words)] . ' ';
        }
        $sentences[] = ucfirst(rtrim(substr($sentence, 0, $max_sentence_chars))) . '.';
      }

      $output[] = implode(' ', $sentences);
    }

    return implode("\n\n", $output);
  }

}
