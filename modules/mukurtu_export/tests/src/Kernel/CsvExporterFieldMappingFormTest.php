<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\mukurtu_export\Entity\CsvExporter;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests that CSV exporter field mappings are edited one section at a time.
 *
 * The add/edit form used to post a row of inputs for every field of every
 * bundle, which exceeded PHP's max_input_vars and broke saving. The mappings
 * now live on per-section forms.
 */
#[Group('mukurtu_export')]
class CsvExporterFieldMappingFormTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mukurtu_export',
    'mukurtu_multipage_items',
  ];

  /**
   * Submit button labels, keyed by form operation.
   */
  protected const BUTTONS = [
    'edit' => 'Update',
    'field_mapping' => 'Save field mapping',
  ];

  /**
   * The CSV exporter under test.
   */
  protected CsvExporter $exporter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('multipage_item');

    // A second node bundle, in a custom section of its own.
    if (!NodeType::load('collection')) {
      NodeType::create(['type' => 'collection', 'name' => 'Collection'])->save();
    }

    $this->exporter = CsvExporter::create([
      'id' => 'test_csv_exporter',
      'label' => 'Test CSV Exporter',
      'uid' => $this->currentUser->id(),
      'entity_fields_export_list' => [
        'node__protocol_aware_content' => ['nid' => 'ID', 'title' => 'Title'],
        'node__collection' => ['title' => 'Collection title'],
        'user__user' => ['name' => 'Username'],
      ],
    ]);
    $this->exporter->save();
  }

  /**
   * Every bundle appears in exactly one section.
   */
  public function testSectionsCoverEveryBundleOnce(): void {
    $sections = $this->container->get('mukurtu_export.csv_mapping_sections')->getSections($this->exporter);
    $all_bundle_info = $this->container->get('entity_type.bundle.info')->getAllBundleInfo();

    $expected = [];
    foreach ($this->exporter->getSupportedEntityTypes() as $type) {
      foreach (array_keys($all_bundle_info[$type] ?? []) as $bundle) {
        $expected[] = "{$type}__{$bundle}";
      }
    }

    $actual = [];
    foreach ($sections as $section) {
      foreach ($section['items'] as $item) {
        $actual[] = "{$item['type']}__{$item['bundle']}";
      }
    }

    sort($expected);
    sort($actual);
    $this->assertNotEmpty($expected);
    $this->assertSame($expected, $actual);
    $this->assertArrayHasKey('collection', $sections);
    $this->assertArrayHasKey('node__protocol_aware_content', $sections);

    // Only the custom groups combine bundles, so no other section grows as a
    // site adds bundles (vocabularies, media types, ...).
    $custom_groups = ['digital_heritage', 'dictionary_word', 'person', 'place', 'collection', 'word_list'];
    foreach ($sections as $key => $section) {
      if (!in_array($key, $custom_groups, TRUE)) {
        $this->assertCount(1, $section['items'], "Section $key has one bundle.");
      }
    }
  }

  /**
   * The edit form has no per-field inputs; the section forms have them all.
   */
  public function testEditFormHasNoFieldMappingInputs(): void {
    $edit_form = $this->buildEntityForm('edit');
    $this->assertSame([], $this->findMappingRows($edit_form));
    $this->assertArrayHasKey('field_mapping', $edit_form);
    $this->assertArrayHasKey('node__protocol_aware_content', $edit_form['field_mapping']['sections']);

    // Negative control: the rows do exist, on the section forms.
    $node_form = $this->buildEntityForm('field_mapping', 'node__protocol_aware_content');
    $this->assertContains('title', $this->findMappingRows($node_form));
    $this->assertArrayHasKey('node__protocol_aware_content', $node_form['mapping']);
    $this->assertArrayNotHasKey('user__user', $node_form['mapping']);
  }

  /**
   * Saving a section only replaces that section's bundles, in weight order.
   */
  public function testSectionSaveOnlyChangesItsBundles(): void {
    $form_state = $this->submitEntityForm('field_mapping', [
      'mapping' => [
        'node__protocol_aware_content' => [
          'fields' => [
            'nid' => ['export' => 1, 'csv_header_label' => 'Node ID', 'weight' => 10],
            'title' => ['export' => 1, 'csv_header_label' => 'Name', 'weight' => -10],
            'uuid' => ['export' => 1, 'csv_header_label' => 'UUID', 'weight' => 0],
          ],
        ],
      ],
    ], 'node__protocol_aware_content');
    $this->assertSame([], $form_state->getErrors());

    $map = $this->reload()->get('entity_fields_export_list');
    $this->assertSame(['title' => 'Name', 'uuid' => 'UUID', 'nid' => 'Node ID'], $map['node__protocol_aware_content']);
    $this->assertSame(['title' => 'Collection title'], $map['node__collection']);
    $this->assertSame(['name' => 'Username'], $map['user__user']);
  }

  /**
   * Unchecking a field removes it from the section's mapping.
   */
  public function testUncheckedFieldIsNotExported(): void {
    $this->submitEntityForm('field_mapping', [
      'mapping' => [
        'node__protocol_aware_content' => [
          'fields' => [
            'nid' => ['export' => 0],
          ],
        ],
      ],
    ], 'node__protocol_aware_content');

    $map = $this->reload()->get('entity_fields_export_list');
    $this->assertSame(['title' => 'Title'], $map['node__protocol_aware_content']);
  }

  /**
   * Saving the general settings doesn't touch the field mapping.
   */
  public function testEditFormSaveKeepsFieldMapping(): void {
    $before = $this->exporter->get('entity_fields_export_list');

    $form_state = $this->submitEntityForm('edit', [
      'label' => 'Renamed',
      'default_format' => 'plain_text',
    ]);
    $this->assertSame([], $form_state->getErrors());

    $reloaded = $this->reload();
    $this->assertSame('Renamed', $reloaded->label());
    $this->assertSame($before, $reloaded->get('entity_fields_export_list'));
  }

  /**
   * A new setting saved from the add form starts with the default mapping.
   */
  public function testAddFormSeedsDefaultFieldMapping(): void {
    $new = CsvExporter::create(['uid' => $this->currentUser->id()]);
    $expected_title = NULL;
    foreach ($new->getMappedFields('node', 'protocol_aware_content') as $field) {
      if ($field['field_name'] === 'title') {
        $expected_title = $field;
      }
    }
    $this->assertTrue($expected_title['export']);

    $form_object = $this->container->get('entity_type.manager')->getFormObject('csv_exporter', 'add');
    $form_object->setEntity($new);
    $form_state = (new FormState())->setValues([
      'label' => 'New setting',
      'default_format' => 'plain_text',
      'op' => 'Save',
    ]);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);
    $this->assertSame([], $form_state->getErrors());

    $saved = CsvExporter::load($new->id());
    $map = $saved->get('entity_fields_export_list');
    $this->assertSame((string) $expected_title['csv_header_label'], $map['node__protocol_aware_content']['title']);
    $this->assertArrayHasKey('user__user', $map);
  }

  /**
   * An unknown section is a 404.
   */
  public function testUnknownSectionIsNotFound(): void {
    $this->expectException(NotFoundHttpException::class);
    $this->buildEntityForm('field_mapping', 'not_a_section');
  }

  /**
   * The field mapping route uses the same access as the edit form.
   */
  public function testFieldMappingRouteAccess(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('entity.csv_exporter.field_mapping_form');
    $this->assertSame('csv_exporter.update', $route->getRequirement('_entity_access'));
    $this->assertSame('csv_exporter.field_mapping', $route->getDefault('_entity_form'));
  }

  /**
   * Builds a CSV exporter entity form.
   */
  protected function buildEntityForm(string $operation, ?string $section = NULL): array {
    $form_object = $this->container->get('entity_type.manager')->getFormObject('csv_exporter', $operation);
    $form_object->setEntity($this->exporter);
    $form_state = new FormState();
    if ($section !== NULL) {
      $form_state->addBuildInfo('args', [$section]);
    }
    return $this->container->get('form_builder')->buildForm($form_object, $form_state);
  }

  /**
   * Submits a CSV exporter entity form.
   */
  protected function submitEntityForm(string $operation, array $values, ?string $section = NULL): FormState {
    $form_object = $this->container->get('entity_type.manager')->getFormObject('csv_exporter', $operation);
    $form_object->setEntity($this->exporter);
    // Programmed submissions only run the button's submit handlers when the
    // button is named in the values.
    $values['op'] = self::BUTTONS[$operation];
    $form_state = (new FormState())->setValues($values);
    if ($section !== NULL) {
      $form_state->addBuildInfo('args', [$section]);
    }
    $this->container->get('form_builder')->submitForm($form_object, $form_state);
    return $form_state;
  }

  /**
   * Returns the field names of every field mapping row in a built form.
   */
  protected function findMappingRows(array $element): array {
    $found = [];
    foreach (Element::children($element) as $key) {
      $child = $element[$key];
      if (is_array($child) && isset($child['csv_header_label']['#type'])) {
        $found[] = (string) $key;
      }
      elseif (is_array($child)) {
        $found = array_merge($found, $this->findMappingRows($child));
      }
    }
    return $found;
  }

  /**
   * Reloads the CSV exporter from storage.
   */
  protected function reload(): CsvExporter {
    return $this->container->get('entity_type.manager')->getStorage('csv_exporter')->loadUnchanged($this->exporter->id());
  }

}
