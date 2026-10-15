<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_import\Form\ImportFieldDescriptionListForm;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests importing person field roles written as "Name>Role".
 */
#[Group('mukurtu_import')]
class ImportPersonRolesTest extends MukurtuImportTestBase {

  /**
   * An existing item with Eunice Kitto (Singer) and Alice Fletcher (no role).
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    Vocabulary::create(['vid' => 'creator', 'name' => 'Creator'])->save();
    Vocabulary::create(['vid' => 'role', 'name' => 'Role'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_creator',
      'entity_type' => 'node',
      'type' => 'mukurtu_entity_reference_role',
      'cardinality' => -1,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_creator',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Creator',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => ['creator' => 'creator'],
          'auto_create' => TRUE,
        ],
        'role_target_bundles' => ['role' => 'role'],
      ],
    ])->save();

    $this->node = Node::create([
      'title' => 'Recording',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_creator' => [
        [
          'target_id' => $this->term('creator', 'Eunice Kitto')->id(),
          'role_target_id' => $this->term('role', 'Singer')->id(),
        ],
        ['target_id' => $this->term('creator', 'Alice Fletcher')->id()],
      ],
    ]);
    $this->node->setSharingSetting('any');
    $this->node->setProtocols([$this->protocol]);
    $this->node->save();
  }

  /**
   * Finds or creates a term.
   */
  protected function term(string $vid, string $name): TermInterface {
    $existing = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $name]);
    if ($existing) {
      return reset($existing);
    }
    $term = Term::create(['vid' => $vid, 'name' => $name]);
    $term->save();
    return $term;
  }

  /**
   * Imports the given rows with the given mapping.
   */
  protected function importRows(array $header, array $rows, array $mapping): void {
    $file = $this->createCsvFile(array_merge([$header], $rows));
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $this->importCsvFile($file, $mapping));
  }

  /**
   * Returns the node's creators as "Name=Role" strings, in order.
   */
  protected function creators(?int $nid = NULL): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache();
    $node = $storage->load($nid ?? $this->node->id());
    $out = [];
    foreach ($node->get('field_creator') as $item) {
      $role = $item->role_target_id ? Term::load($item->role_target_id)->getName() : '-';
      $out[] = $item->entity->getName() . '=' . $role;
    }
    return $out;
  }

  /**
   * Imports the given Creator cell onto the existing item.
   */
  protected function importCreators(string $cell): void {
    $this->importRows(['nid', 'Creator'], [[$this->node->id(), $cell]], [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_creator', 'source' => 'Creator'],
    ]);
  }

  /**
   * Entries written as Name>Role set roles; new roles are created once.
   */
  public function testInlineRolesAreSet(): void {
    $this->importCreators('Alice Fletcher>Writer;Mary Jones;Eunice Kitto>Singer');

    // Mary is new and has no role to keep; Eunice's role moves with the
    // name from first place to last.
    $this->assertSame(['Alice Fletcher=Writer', 'Mary Jones=-', 'Eunice Kitto=Singer'], $this->creators());
    $writers = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'role', 'name' => 'Writer']);
    $this->assertCount(1, $writers);
  }

  /**
   * A name on its own keeps that person's current role.
   */
  public function testNameOnlyEntriesKeepRoles(): void {
    $this->importCreators('Mary Jones;Eunice Kitto');

    // Eunice keeps Singer in the new position; Mary doesn't inherit it.
    $this->assertSame(['Mary Jones=-', 'Eunice Kitto=Singer'], $this->creators());
  }

  /**
   * An entry written as Name> removes that person's role.
   */
  public function testEmptyRoleRemovesIt(): void {
    $this->importCreators('Eunice Kitto>;Alice Fletcher>Narrator');
    $this->assertSame(['Eunice Kitto=-', 'Alice Fletcher=Narrator'], $this->creators());
  }

  /**
   * People given by ID or UUID get their roles too.
   */
  public function testRolesWithPeopleByIdAndUuid(): void {
    $kitto = $this->term('creator', 'Eunice Kitto');
    $fletcher = $this->term('creator', 'Alice Fletcher');
    $this->importCreators("{$fletcher->uuid()}>Writer;{$kitto->id()}>Narrator");
    $this->assertSame(['Alice Fletcher=Writer', 'Eunice Kitto=Narrator'], $this->creators());
  }

  /**
   * A name may contain ">"; the role is after the last one.
   */
  public function testNameContainingSeparator(): void {
    $this->importCreators('Smith > Jones Family>Editor;Eunice Kitto');
    $this->assertSame(['Smith > Jones Family=Editor', 'Eunice Kitto=Singer'], $this->creators());
  }

  /**
   * New items get their roles too.
   */
  public function testNewItemWithRoles(): void {
    $this->importRows(['title', 'Creator', 'protocols', 'sharing_setting'], [
      ['New recording', 'Annie James>Interviewer;Eunice Kitto>Singer', $this->protocol->id(), 'any'],
    ], [
      ['target' => 'title', 'source' => 'title'],
      ['target' => 'field_creator', 'source' => 'Creator'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'protocols'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'sharing_setting'],
    ]);

    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['title' => 'New recording']);
    $this->assertSame(['Annie James=Interviewer', 'Eunice Kitto=Singer'], $this->creators((int) reset($nodes)->id()));
  }

  /**
   * The mapping offers the field as one column, described with the format.
   */
  public function testMappingOffersOneColumn(): void {
    $form = ImportFieldDescriptionListForm::create($this->container)
      ->buildForm([], new FormState(), 'node', 'protocol_aware_content');
    $options = $form['table_required']['#options'] + $form['table_optional']['#options'];

    $this->assertArrayHasKey('field_creator', $options);
    $this->assertEmpty(array_filter(array_keys($options), fn ($key) => str_starts_with((string) $key, 'field_creator/')));

    $plugin = $this->container->get('plugin.manager.mukurtu_import_field_process')
      ->getInstance(['field_definition' => $this->node->getFieldDefinition('field_creator')]);
    $this->assertStringContainsString('Eunice Kitto>Singer', (string) $plugin->getFormatDescription($this->node->getFieldDefinition('field_creator')));
  }

}
