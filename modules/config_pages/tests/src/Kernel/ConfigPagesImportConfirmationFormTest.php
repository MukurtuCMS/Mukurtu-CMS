<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\config_pages\Form\ConfigPagesImportConfirmationForm;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPagesImportConfirmationForm.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\Form\ConfigPagesImportConfirmationForm
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesImportConfirmationFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'config_pages',
  ];

  /**
   * The config page type.
   *
   * @var \Drupal\config_pages\Entity\ConfigPagesType
   */
  protected ConfigPagesType $configPageType;

  /**
   * The target config page entity.
   *
   * @var \Drupal\config_pages\Entity\ConfigPages
   */
  protected ConfigPages $targetPage;

  /**
   * The source config page entity to import from.
   *
   * @var \Drupal\config_pages\Entity\ConfigPages
   */
  protected ConfigPages $sourcePage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('config_pages');
    $this->installEntitySchema('config_pages_type');
    $this->installConfig(['field', 'system']);

    // Create a config page type.
    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_import_type',
      'label' => 'Test Import Type',
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

    // Create a text field.
    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'field_test_import',
      'entity_type' => 'config_pages',
      'type' => 'string',
      'cardinality' => 1,
    ]);
    $fieldStorage->save();

    $fieldConfig = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'test_import_type',
      'label' => 'Test Import Field',
    ]);
    $fieldConfig->save();

    // Create a target config page with initial value.
    $this->targetPage = ConfigPages::create([
      'type' => 'test_import_type',
      'label' => 'Target Page',
      'context' => serialize([]),
      'field_test_import' => 'original_value',
    ]);
    $this->targetPage->save();

    // Create a source config page with different value.
    $this->sourcePage = ConfigPages::create([
      'type' => 'test_import_type',
      'label' => 'Source Page',
      'context' => serialize([['ctx' => 'source']]),
      'field_test_import' => 'imported_value',
    ]);
    $this->sourcePage->save();
  }

  /**
   * Tests the form can be created via dependency injection.
   *
   * @covers ::create
   */
  public function testFormCanBeCreated(): void {
    $form = ConfigPagesImportConfirmationForm::create($this->container);

    $this->assertInstanceOf(ConfigPagesImportConfirmationForm::class, $form);
  }

  /**
   * Tests getFormId returns correct ID.
   *
   * @covers ::getFormId
   */
  public function testGetFormId(): void {
    $form = ConfigPagesImportConfirmationForm::create($this->container);

    $this->assertEquals('config_pages_import_confirmation_form', $form->getFormId());
  }

  /**
   * Tests getQuestion returns expected text.
   *
   * @covers ::getQuestion
   */
  public function testGetQuestion(): void {
    $form = ConfigPagesImportConfirmationForm::create($this->container);

    $question = (string) $form->getQuestion();

    $this->assertStringContainsString('import', strtolower($question));
  }

  /**
   * Tests getDescription returns expected text.
   *
   * @covers ::getDescription
   */
  public function testGetDescription(): void {
    $form = ConfigPagesImportConfirmationForm::create($this->container);

    $description = (string) $form->getDescription();

    $this->assertStringContainsString('overwrite', strtolower($description));
  }

  /**
   * Tests getConfirmText returns "Import".
   *
   * @covers ::getConfirmText
   */
  public function testGetConfirmText(): void {
    $form = ConfigPagesImportConfirmationForm::create($this->container);

    $this->assertEquals('Import', (string) $form->getConfirmText());
  }

  /**
   * Tests getCancelText returns "Cancel".
   *
   * @covers ::getCancelText
   */
  public function testGetCancelText(): void {
    $form = ConfigPagesImportConfirmationForm::create($this->container);

    $this->assertEquals('Cancel', (string) $form->getCancelText());
  }

  /**
   * Tests getCancelUrl returns entity canonical URL.
   *
   * @covers ::getCancelUrl
   * @covers ::buildForm
   */
  public function testGetCancelUrl(): void {
    $form = ConfigPagesImportConfirmationForm::create($this->container);
    $formState = new FormState();

    $form->buildForm([], $formState, $this->targetPage->id(), $this->sourcePage->id());

    $cancelUrl = $form->getCancelUrl();

    $this->assertEquals('entity.config_pages.canonical', $cancelUrl->getRouteName());
    $params = $cancelUrl->getRouteParameters();
    $this->assertEquals($this->targetPage->id(), $params['config_pages']);
  }

  /**
   * Tests submitForm copies field values from source to target.
   *
   * @covers ::submitForm
   */
  public function testSubmitFormCopiesValues(): void {
    // Verify initial state.
    $this->assertEquals('original_value', $this->targetPage->get('field_test_import')->value);
    $this->assertEquals('imported_value', $this->sourcePage->get('field_test_import')->value);

    $formObject = ConfigPagesImportConfirmationForm::create($this->container);
    $formState = new FormState();

    $formObject->buildForm([], $formState, $this->targetPage->id(), $this->sourcePage->id());

    $formArray = [];
    $formObject->submitForm($formArray, $formState);

    // Reload target entity and verify value was imported.
    $reloaded = ConfigPages::load($this->targetPage->id());
    $this->assertEquals('imported_value', $reloaded->get('field_test_import')->value);

    // Source entity should remain unchanged.
    $reloadedSource = ConfigPages::load($this->sourcePage->id());
    $this->assertEquals('imported_value', $reloadedSource->get('field_test_import')->value);
  }

  /**
   * Tests submitForm with non-existing entity shows error.
   *
   * @covers ::submitForm
   */
  public function testSubmitFormWithMissingEntity(): void {
    $formObject = ConfigPagesImportConfirmationForm::create($this->container);
    $formState = new FormState();

    // Use non-existing IDs.
    $formObject->buildForm([], $formState, 99999, 99998);

    $formArray = [];
    $formObject->submitForm($formArray, $formState);

    // Target entity should remain unchanged.
    $reloaded = ConfigPages::load($this->targetPage->id());
    $this->assertEquals('original_value', $reloaded->get('field_test_import')->value);
  }

  /**
   * Tests submitForm with missing source entity shows error.
   *
   * @covers ::submitForm
   */
  public function testSubmitFormWithMissingSourceEntity(): void {
    $formObject = ConfigPagesImportConfirmationForm::create($this->container);
    $formState = new FormState();

    // Valid target, non-existing source.
    $formObject->buildForm([], $formState, $this->targetPage->id(), 99999);

    $formArray = [];
    $formObject->submitForm($formArray, $formState);

    // Target entity should remain unchanged.
    $reloaded = ConfigPages::load($this->targetPage->id());
    $this->assertEquals('original_value', $reloaded->get('field_test_import')->value);
  }

  /**
   * Tests buildForm stores entity IDs correctly.
   *
   * @covers ::buildForm
   */
  public function testBuildFormStoresIds(): void {
    $formObject = ConfigPagesImportConfirmationForm::create($this->container);
    $formState = new FormState();

    $result = $formObject->buildForm([], $formState, $this->targetPage->id(), $this->sourcePage->id());

    // Form should be built successfully.
    $this->assertIsArray($result);
  }

}
