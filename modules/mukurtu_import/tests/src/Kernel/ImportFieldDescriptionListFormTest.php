<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_import\Form\ImportFieldDescriptionListForm;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the required/optional field split on the import format page.
 */
#[Group('mukurtu_import')]
class ImportFieldDescriptionListFormTest extends MukurtuImportTestBase {

  /**
   * The form under test.
   *
   * @var \Drupal\mukurtu_import\Form\ImportFieldDescriptionListForm
   */
  protected ImportFieldDescriptionListForm $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->form = ImportFieldDescriptionListForm::create($this->container);
  }

  /**
   * Required fields land in the Required table, optional fields don't.
   */
  public function testRequiredFieldsAreSeparatedFromOptionalFields(): void {
    $form = $this->form->buildForm([], new FormState(), 'node', 'protocol_aware_content');

    $required_options = $form['table_required']['#options'];
    $optional_options = $form['table_optional']['#options'];

    // Title and both Cultural Protocol sub-properties are required base
    // fields on every Mukurtu content node type.
    $this->assertArrayHasKey('title', $required_options);
    $this->assertArrayHasKey('field_cultural_protocols/protocols', $required_options);
    $this->assertArrayHasKey('field_cultural_protocols/sharing_setting', $required_options);

    // The entity UUID is a valid import target but is never itself
    // "required" - a blank value just means "create new content".
    $this->assertArrayHasKey('uuid', $optional_options);

    // No field should appear in both sections.
    $this->assertEmpty(array_intersect_key($required_options, $optional_options));

    // The identifier note explaining the ID/UUID/label rule is present.
    $this->assertStringContainsString('uniquely identify each row', (string) $form['identifier_note']['#markup']);
  }

  /**
   * Landing pages don't expose Cultural Protocols/Sharing Setting to import.
   *
   * Landing pages pick up a required field_cultural_protocols base field
   * from the shared MukurtuNode bundle class, but the field is hidden on
   * the landing page edit form and isn't part of its editing workflow, so
   * it should not appear as either required or optional here.
   */
  public function testLandingPageExcludesCulturalProtocols(): void {
    NodeType::create([
      'type' => 'landing_page',
      'name' => 'Landing Page',
    ])->save();

    $form = $this->form->buildForm([], new FormState(), 'node', 'landing_page');

    $required_options = $form['table_required']['#options'] ?? [];
    $optional_options = $form['table_optional']['#options'] ?? [];

    $this->assertArrayNotHasKey('field_cultural_protocols/protocols', $required_options);
    $this->assertArrayNotHasKey('field_cultural_protocols/sharing_setting', $required_options);
    $this->assertArrayNotHasKey('field_cultural_protocols/protocols', $optional_options);
    $this->assertArrayNotHasKey('field_cultural_protocols/sharing_setting', $optional_options);

    // Title is still required, confirming the bundle's other required
    // fields are unaffected.
    $this->assertArrayHasKey('title', $required_options);
  }

  /**
   * The CSV template download includes fields checked in either section.
   */
  public function testCsvTemplateIncludesBothSections(): void {
    $form = $this->form->buildForm([], new FormState(), 'node', 'protocol_aware_content');

    $form_state = new FormState();
    $form_state->setValue('entity_type_id', 'node');
    $form_state->setValue('bundle', 'protocol_aware_content');
    $form_state->setValue('table_required', ['title' => 'title']);
    $form_state->setValue('table_optional', ['uuid' => 'uuid']);

    $this->form->submitForm($form, $form_state);
    $response = $form_state->getResponse();

    $this->assertStringContainsString((string) $form['table_required']['#options']['title']['label'], $response->getContent());
    $this->assertStringContainsString((string) $form['table_optional']['#options']['uuid']['label'], $response->getContent());
  }

}
