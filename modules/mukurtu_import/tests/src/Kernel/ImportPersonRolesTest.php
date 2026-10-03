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
 * Tests importing roles for person fields from a "Field > Role" column.
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
   * Imports rows of [nid, creators, roles] with the given mapping.
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
   * Names and roles import together, paired by position.
   */
  public function testNamesAndRolesPairByPosition(): void {
    $this->importRows(['nid', 'Creator', 'Creator > Role'], [
      [$this->node->id(), 'Alice Fletcher;Mary Jones;Eunice Kitto', 'Writer;;Singer'],
    ], [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_creator', 'source' => 'Creator'],
      ['target' => 'field_creator/role_target_id', 'source' => 'Creator > Role'],
    ]);

    $this->assertSame(['Alice Fletcher=Writer', 'Mary Jones=-', 'Eunice Kitto=Singer'], $this->creators());
    // The new role was created once, in the Role vocabulary.
    $writers = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'role', 'name' => 'Writer']);
    $this->assertCount(1, $writers);
  }

  /**
   * A file without a role column keeps the roles of people it still lists.
   */
  public function testNameOnlyImportKeepsMatchingRoles(): void {
    $this->importRows(['nid', 'Creator'], [
      [$this->node->id(), 'Mary Jones;Eunice Kitto'],
    ], [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_creator', 'source' => 'Creator'],
    ]);

    // Eunice keeps Singer, Mary is new with no role, Alice is gone.
    $this->assertSame(['Mary Jones=-', 'Eunice Kitto=Singer'], $this->creators());
  }

  /**
   * A role column on its own sets roles without touching the names.
   */
  public function testRoleOnlyImportKeepsNames(): void {
    $this->importRows(['nid', 'Creator > Role'], [
      [$this->node->id(), ';Narrator'],
    ], [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_creator/role_target_id', 'source' => 'Creator > Role'],
    ]);

    // A blank position clears Eunice's role.
    $this->assertSame(['Eunice Kitto=-', 'Alice Fletcher=Narrator'], $this->creators());
  }

  /**
   * Roles can be given as IDs or UUIDs, as an ID-based export writes them.
   */
  public function testRolesByIdAndUuid(): void {
    $writer = $this->term('role', 'Writer');
    $singer = $this->term('role', 'Singer');
    $this->importRows(['nid', 'Creator > Role'], [
      [$this->node->id(), "{$writer->uuid()};{$singer->id()}"],
    ], [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_creator/role_target_id', 'source' => 'Creator > Role'],
    ]);

    $this->assertSame(['Eunice Kitto=Writer', 'Alice Fletcher=Singer'], $this->creators());
  }

  /**
   * New items get their roles too.
   */
  public function testNewItemWithRoles(): void {
    $this->importRows(['title', 'Creator', 'Creator > Role', 'protocols', 'sharing_setting'], [
      ['New recording', 'Annie James;Eunice Kitto', 'Interviewer;Singer', $this->protocol->id(), 'any'],
    ], [
      ['target' => 'title', 'source' => 'title'],
      ['target' => 'field_creator', 'source' => 'Creator'],
      ['target' => 'field_creator/role_target_id', 'source' => 'Creator > Role'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'protocols'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'sharing_setting'],
    ]);

    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['title' => 'New recording']);
    $this->assertSame(['Annie James=Interviewer', 'Eunice Kitto=Singer'], $this->creators((int) reset($nodes)->id()));
  }

  /**
   * The mapping options offer both the names and the role column.
   */
  public function testMappingOffersNamesAndRoles(): void {
    $form = ImportFieldDescriptionListForm::create($this->container)
      ->buildForm([], new FormState(), 'node', 'protocol_aware_content');
    $options = $form['table_required']['#options'] + $form['table_optional']['#options'];

    $this->assertArrayHasKey('field_creator', $options);
    $this->assertArrayHasKey('field_creator/role_target_id', $options);
    // Control: a field whose sub-columns include its main property is still
    // offered only by sub-column.
    $this->assertArrayHasKey('field_cultural_protocols/protocols', $options);
    $this->assertArrayNotHasKey('field_cultural_protocols', $options);
  }

}
