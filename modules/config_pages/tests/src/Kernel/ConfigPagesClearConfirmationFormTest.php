<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\config_pages\Form\ConfigPagesClearConfirmationForm;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPagesClearConfirmationForm.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\Form\ConfigPagesClearConfirmationForm
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesClearConfirmationFormTest extends KernelTestBase {

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
   * The config page entity.
   *
   * @var \Drupal\config_pages\Entity\ConfigPages
   */
  protected ConfigPages $configPage;

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
      'id' => 'test_clear_type',
      'label' => 'Test Clear Type',
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
      'field_name' => 'field_test_clear',
      'entity_type' => 'config_pages',
      'type' => 'string',
      'cardinality' => 1,
    ]);
    $fieldStorage->save();

    $fieldConfig = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'test_clear_type',
      'label' => 'Test Clear Field',
    ]);
    $fieldConfig->save();

    // Create a config page entity with a field value.
    $this->configPage = ConfigPages::create([
      'type' => 'test_clear_type',
      'label' => 'Test Clear Type',
      'context' => serialize([]),
      'field_test_clear' => 'test_value',
    ]);
    $this->configPage->save();
  }

  /**
   * Tests the form can be created via dependency injection.
   */
  public function testFormCanBeCreated(): void {
    $form = ConfigPagesClearConfirmationForm::create($this->container);

    $this->assertInstanceOf(ConfigPagesClearConfirmationForm::class, $form);
  }

  /**
   * Tests getFormId returns correct ID.
   *
   * @covers ::getFormId
   */
  public function testGetFormId(): void {
    $form = ConfigPagesClearConfirmationForm::create($this->container);

    $this->assertEquals('config_pages_clear_confirmation_form', $form->getFormId());
  }

  /**
   * Tests getQuestion returns label instead of ID.
   *
   * @covers ::getQuestion
   * @covers ::buildForm
   */
  public function testGetQuestionShowsLabel(): void {
    $form = ConfigPagesClearConfirmationForm::create($this->container);
    $formState = new FormState();

    $form->buildForm([], $formState, $this->configPage->id());

    $question = (string) $form->getQuestion();

    $this->assertStringContainsString('clear', $question);
    $this->assertStringContainsString('Test Clear Type', $question);
    $this->assertStringNotContainsString($this->configPage->id(), $question);
  }

  /**
   * Tests getDescription returns improved text.
   *
   * @covers ::getDescription
   */
  public function testGetDescription(): void {
    $form = ConfigPagesClearConfirmationForm::create($this->container);

    $description = (string) $form->getDescription();

    $this->assertStringContainsString('reset all field values', $description);
  }

  /**
   * Tests getConfirmText returns "Clear".
   *
   * @covers ::getConfirmText
   */
  public function testGetConfirmText(): void {
    $form = ConfigPagesClearConfirmationForm::create($this->container);

    $confirmText = (string) $form->getConfirmText();

    $this->assertEquals('Clear', $confirmText);
  }

  /**
   * Tests getCancelText returns "Cancel".
   *
   * @covers ::getCancelText
   */
  public function testGetCancelText(): void {
    $form = ConfigPagesClearConfirmationForm::create($this->container);

    $cancelText = (string) $form->getCancelText();

    $this->assertEquals('Cancel', $cancelText);
  }

  /**
   * Tests getEntity returns the loaded entity.
   *
   * @covers ::getEntity
   * @covers ::buildForm
   */
  public function testGetEntity(): void {
    $form = ConfigPagesClearConfirmationForm::create($this->container);
    $formState = new FormState();

    // Before buildForm, entity should be null.
    $this->assertNull($form->getEntity());

    $form->buildForm([], $formState, $this->configPage->id());

    // After buildForm, entity should be loaded.
    $entity = $form->getEntity();
    $this->assertNotNull($entity);
    $this->assertEquals($this->configPage->id(), $entity->id());
  }

  /**
   * Tests getCancelUrl returns entity URL.
   *
   * @covers ::getCancelUrl
   */
  public function testGetCancelUrl(): void {
    $form = ConfigPagesClearConfirmationForm::create($this->container);
    $formState = new FormState();

    $form->buildForm([], $formState, $this->configPage->id());

    $cancelUrl = $form->getCancelUrl();

    $this->assertStringContainsString('config_pages', $cancelUrl->getRouteName());
  }

  /**
   * Tests getCancelUrl with no entity falls back to collection.
   *
   * @covers ::getCancelUrl
   */
  public function testGetCancelUrlWithoutEntity(): void {
    $form = ConfigPagesClearConfirmationForm::create($this->container);

    $cancelUrl = $form->getCancelUrl();

    $this->assertEquals('entity.config_pages_type.collection', $cancelUrl->getRouteName());
  }

  /**
   * Tests submitForm clears field values.
   *
   * @covers ::submitForm
   */
  public function testSubmitFormClearsFieldValues(): void {
    // Verify field has value before clear.
    $this->assertEquals('test_value', $this->configPage->get('field_test_clear')->value);

    $formObject = ConfigPagesClearConfirmationForm::create($this->container);
    $formState = new FormState();

    $formObject->buildForm([], $formState, $this->configPage->id());

    $formArray = [];
    $formObject->submitForm($formArray, $formState);

    // Reload the entity.
    $reloaded = ConfigPages::load($this->configPage->id());

    // Field value should be cleared (empty).
    $this->assertEmpty($reloaded->get('field_test_clear')->value);
  }

  /**
   * Tests submitForm with null entity does nothing.
   *
   * @covers ::submitForm
   */
  public function testSubmitFormWithNullEntityDoesNothing(): void {
    $formObject = ConfigPagesClearConfirmationForm::create($this->container);
    $formState = new FormState();

    // Don't call buildForm, so entity is null.
    $formArray = [];
    $formObject->submitForm($formArray, $formState);

    // Original entity should be unchanged.
    $reloaded = ConfigPages::load($this->configPage->id());
    $this->assertEquals('test_value', $reloaded->get('field_test_clear')->value);
  }

}
