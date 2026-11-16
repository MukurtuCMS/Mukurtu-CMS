<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\FileInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\mukurtu_import\Form\CustomStrategyFromFileForm;
use Drupal\mukurtu_import\Form\ImportFileSummaryForm;
use Drupal\mukurtu_import\ImportBatchExecutable;
use Drupal\mukurtu_import\ParagraphImportAccess;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests access checks on paragraph imports.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1449
 */
#[CoversClass(ParagraphImportAccess::class)]
#[Group('mukurtu_import')]
class ImportParagraphAccessTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_reference_revisions', 'paragraphs'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('paragraphs_type');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['entity_reference_revisions', 'paragraphs']);

    ParagraphsType::create(['id' => 'simple_paragraph', 'label' => 'Simple paragraph'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_paragraph_text',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_paragraph_text',
      'entity_type' => 'paragraph',
      'bundle' => 'simple_paragraph',
      'label' => 'Text',
    ])->save();

    // An item in the steward's protocol, with one paragraph.
    FieldStorageConfig::create([
      'field_name' => 'field_paragraphs',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'cardinality' => -1,
      'settings' => ['target_type' => 'paragraph'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_paragraphs',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Paragraphs',
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => ['target_bundles' => ['simple_paragraph' => 'simple_paragraph']],
      ],
    ])->save();
    $this->paragraph = Paragraph::create(['type' => 'simple_paragraph', 'field_paragraph_text' => 'Original text']);
    $node = Node::create([
      'title' => 'Item',
      'type' => 'protocol_aware_content',
      'field_paragraphs' => [$this->paragraph],
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
  }

  /**
   * The paragraph on the steward's item.
   *
   * @var \Drupal\paragraphs\Entity\Paragraph
   */
  protected Paragraph $paragraph;

  /**
   * Switches to an account with no memberships, so it can't create content.
   */
  protected function switchToNonCreator(): void {
    // The base class lets every account create its content type, which a
    // Mukurtu site doesn't.
    Role::load('authenticated')->revokePermission('create protocol_aware_content content')->save();
    $account = $this->createUser();
    $account->save();
    $this->currentUser = $account;
    $this->setCurrentUser($account);
  }

  /**
   * Imports one new paragraph as the current user.
   *
   * @return int
   *   The number of paragraphs with the imported text afterwards.
   */
  protected function importParagraph(): int {
    $file = $this->createCsvFile([['Key', 'Text'], ['row-1', 'Imported text']]);
    $this->runImport($file, [
      ['target' => 'field_paragraph_text', 'source' => 'Text'],
    ]);
    return (int) $this->container->get('entity_type.manager')->getStorage('paragraph')
      ->getQuery()->accessCheck(FALSE)->condition('field_paragraph_text', 'Imported text')->count()->execute();
  }

  /**
   * Runs a paragraph import that identifies rows by the Key column.
   */
  protected function runImport(FileInterface $file, array $mapping, ?string $identifier_column = 'Key'): int {
    $strategy = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $strategy->setTargetEntityTypeId('paragraph');
    $strategy->setTargetBundle('simple_paragraph');
    $strategy->setMapping($mapping);
    if ($identifier_column) {
      $strategy->setConfig('identifier_column', $identifier_column);
    }

    $manager = $this->container->get('plugin.manager.migration');
    $this->lastMigration = $manager->createStubMigration($strategy->toDefinition($file));
    $executable = new ImportBatchExecutable(
      $this->lastMigration,
      new MigrateMessage(),
      $this->keyValue,
      $this->container->get('datetime.time'),
      $this->container->get('string_translation'),
      $manager,
      [],
    );
    return $executable->import();
  }

  /**
   * Imports new text for the paragraph on the steward's item, by its ID.
   *
   * @return string
   *   The paragraph's text afterwards.
   */
  protected function updateParagraph(): string {
    $file = $this->createCsvFile([['ID', 'Text'], [$this->paragraph->id(), 'Changed text']]);
    $this->runImport($file, [
      ['target' => 'id', 'source' => 'ID'],
      ['target' => 'field_paragraph_text', 'source' => 'Text'],
    ], NULL);
    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $storage->resetCache();
    return $storage->load($this->paragraph->id())->get('field_paragraph_text')->value;
  }

  /**
   * Gets the entity type options on the mapping form.
   */
  protected function entityTypeOptions(): array {
    return $this->mappingForm()['entity_type_id']['#options'];
  }

  /**
   * Builds the mapping form for a new file.
   */
  protected function mappingForm(): array {
    $file = $this->createCsvFile([['Text'], ['Imported text']]);
    return CustomStrategyFromFileForm::create($this->container)->buildForm([], new FormState(), $file);
  }

  /**
   * Gets the saved templates offered for a file, by label.
   */
  protected function templateOptions(): array {
    $file = $this->createCsvFile([['Text', 'Title'], ['Imported text', 'Title']]);
    foreach (['paragraph' => 'simple_paragraph', 'node' => 'protocol_aware_content'] as $entity_type_id => $bundle) {
      $strategy = MukurtuImportStrategy::create([
        'id' => $entity_type_id . '_template',
        'label' => "$entity_type_id template",
        'uid' => $this->currentUser->id(),
        'configuration' => [],
      ]);
      $strategy->setTargetEntityTypeId($entity_type_id);
      $strategy->setTargetBundle($bundle);
      $strategy->setMapping([
        ['target' => 'field_paragraph_text', 'source' => 'Text'],
        ['target' => 'title', 'source' => 'Title'],
      ]);
      $strategy->save();
    }
    $form = ImportFileSummaryForm::create($this->container);
    $method = new \ReflectionMethod($form, 'getImportConfigOptions');
    return array_map('strval', $method->invoke($form, $file->id()));
  }

  /**
   * Tests that someone who can create content can import paragraphs.
   */
  public function testContentCreatorCanImportParagraphs(): void {
    $this->assertArrayHasKey('paragraph', $this->entityTypeOptions());
    $this->assertTrue($this->mappingForm()['bundle']['#access']);
    $this->assertEqualsCanonicalizing(['custom', 'node_template', 'paragraph_template'], array_keys($this->templateOptions()));
    $this->assertSame(1, $this->importParagraph());
    $this->assertSame([], iterator_to_array($this->lastMigration->getIdMap()->getMessages()));
  }

  /**
   * Tests that the steward can update a paragraph on their item.
   */
  public function testStewardCanUpdateTheirParagraph(): void {
    $this->assertSame('Changed text', $this->updateParagraph());
  }

  /**
   * Tests that updating a paragraph follows access to its item.
   */
  public function testParagraphUpdateFollowsItemAccess(): void {
    $this->switchToNonCreator();

    $this->assertSame('Original text', $this->updateParagraph());
    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('does not have access to update', reset($messages)->message);
  }

  /**
   * Tests that someone who can't create content can't import paragraphs.
   */
  public function testNonCreatorCannotImportParagraphs(): void {
    $this->switchToNonCreator();

    $this->assertArrayNotHasKey('paragraph', $this->entityTypeOptions(), 'Paragraphs are not offered as an import type.');
    $this->assertSame(['custom'], array_keys($this->templateOptions()), 'Templates for types the user cannot import are not offered.');
    $form = $this->mappingForm();
    $this->assertSame([], $form['entity_type_id']['#options']);
    $this->assertFalse($form['bundle']['#access'], 'No sub-type, not even base fields only, is offered without a type.');

    // The template form defaults to content, which this user can't create.
    $template_form = $this->container->get('entity.form_builder')
      ->getForm(MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]), 'add');
    $this->assertSame([], $template_form['target_entity_type_id']['#options']);
    $this->assertFalse($template_form['target_bundle']['#access']);
    // A saved paragraph template must not get around the form.
    $this->assertSame(0, $this->importParagraph());
    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('does not have create access', reset($messages)->message);
  }

}
