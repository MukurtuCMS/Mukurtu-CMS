<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_digital_heritage\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the role sub-field on field_creator and its update path.
 */
#[Group('mukurtu_digital_heritage')]
class CreatorRoleTest extends DigitalHeritageTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['flat_taxonomy'];

  /**
   * {@inheritdoc}
   *
   * The update installs the shipped Role vocabulary, which carries
   * flat_taxonomy's third-party setting like every Mukurtu vocabulary.
   * flat_taxonomy keeps its schema file at the module root rather than in
   * config/schema/, so Drupal never discovers it and strict checking fails.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // taxonomy_index is only maintained when this setting is on.
    $this->installConfig(['taxonomy']);
    Vocabulary::create(['vid' => 'creator', 'name' => 'Creator'])->save();
  }

  /**
   * Creates the role vocabulary, as a fresh install would.
   */
  protected function createRoleVocabulary(): void {
    Vocabulary::create(['vid' => 'role', 'name' => 'Role'])->save();
  }

  /**
   * Saves a digital heritage item with the given creator values.
   */
  protected function saveItemWithCreators(array $creators): Node {
    $item = $this->buildDigitalHeritage('Item', [$this->createCategory('Category')]);
    $item->set('field_creator', $creators);
    $item->save();
    return $item;
  }

  /**
   * The creator and role survive a save and reload.
   */
  public function testCreatorAndRolePersist(): void {
    $this->createRoleVocabulary();
    $creator = Term::create(['vid' => 'creator', 'name' => 'Eunice Kitto']);
    $creator->save();
    $role = Term::create(['vid' => 'role', 'name' => 'Singer']);
    $role->save();
    $other = Term::create(['vid' => 'creator', 'name' => 'No Role']);
    $other->save();

    $item = $this->saveItemWithCreators([
      ['target_id' => $creator->id(), 'role_target_id' => $role->id()],
      ['target_id' => $other->id()],
    ]);

    $this->container->get('entity_type.manager')->getStorage('node')->resetCache();
    $values = Node::load($item->id())->get('field_creator')->getValue();
    $this->assertEquals($creator->id(), $values[0]['target_id']);
    $this->assertEquals($role->id(), $values[0]['role_target_id']);
    $this->assertEquals($other->id(), $values[1]['target_id']);
    $this->assertNull($values[1]['role_target_id']);
  }

  /**
   * A new, unsaved role term is saved along with the item.
   */
  public function testNewRoleTermIsSavedWithItem(): void {
    $this->createRoleVocabulary();
    $creator = Term::create(['vid' => 'creator', 'name' => 'Eunice Kitto']);
    $creator->save();
    $role = Term::create(['vid' => 'role', 'name' => 'Narrator']);

    $item = $this->saveItemWithCreators([
      ['target_id' => $creator->id(), 'role_entity' => $role],
    ]);

    $this->assertFalse($role->isNew());
    $this->assertEquals($role->id(), $item->get('field_creator')->role_target_id);
  }

  /**
   * Term pages keep working: the creator is indexed and the role is not.
   */
  public function testTaxonomyIndexHoldsCreatorNotRole(): void {
    $this->createRoleVocabulary();
    $creator = Term::create(['vid' => 'creator', 'name' => 'Eunice Kitto']);
    $creator->save();
    $role = Term::create(['vid' => 'role', 'name' => 'Singer']);
    $role->save();

    $item = $this->saveItemWithCreators([
      ['target_id' => $creator->id(), 'role_target_id' => $role->id()],
    ]);

    $indexed = $this->container->get('database')->select('taxonomy_index', 'ti')
      ->fields('ti', ['tid'])
      ->condition('nid', $item->id())
      ->execute()
      ->fetchCol();
    // Control: a plain term reference on the same item is indexed too.
    $category = $item->get('field_category')->target_id;
    $this->assertContains((string) $category, $indexed);
    $this->assertContains((string) $creator->id(), $indexed);
    $this->assertNotContains((string) $role->id(), $indexed);
  }

  /**
   * The update converts a site's existing entity_reference field_creator.
   */
  public function testUpdateConvertsExistingCreatorStorage(): void {
    $creator = Term::create(['vid' => 'creator', 'name' => 'Eunice Kitto']);
    $creator->save();
    $item = $this->saveItemWithCreators([['target_id' => $creator->id()]]);

    $this->rollBackToEntityReferenceStorage();
    $this->assertNull(Vocabulary::load('role'));
    // Negative control: the rollback really leaves a pending change, so the
    // assertion after the update can't pass vacuously.
    $change_list = $this->container->get('entity.definition_update_manager')->getChangeList();
    $this->assertArrayHasKey('field_creator', $change_list['node']['field_storage_definitions'] ?? []);

    $this->container->get('module_handler')->loadInclude('mukurtu_digital_heritage', 'install');
    mukurtu_digital_heritage_update_40501();

    // The vocabulary is installed and nothing is left pending.
    $this->assertNotNull(Vocabulary::load('role'));
    $change_list = $this->container->get('entity.definition_update_manager')->getChangeList();
    $this->assertArrayNotHasKey('field_creator', $change_list['node']['field_storage_definitions'] ?? []);

    // Existing data is intact and a role can now be stored.
    $this->container->get('entity_type.manager')->getStorage('node')->resetCache();
    $loaded = Node::load($item->id());
    $this->assertEquals($creator->id(), $loaded->get('field_creator')->target_id);
    $this->assertNull($loaded->get('field_creator')->role_target_id);

    $role = Term::create(['vid' => 'role', 'name' => 'Singer']);
    $role->save();
    $loaded->get('field_creator')->role_target_id = $role->id();
    $loaded->save();
    $this->container->get('entity_type.manager')->getStorage('node')->resetCache();
    $this->assertEquals($role->id(), Node::load($item->id())->get('field_creator')->role_target_id);

    // Running it again is harmless.
    mukurtu_digital_heritage_update_40501();
  }

  /**
   * Puts field_creator back into the state an existing site has before 4.0.5.
   */
  protected function rollBackToEntityReferenceStorage(): void {
    $last_installed = $this->container->get('entity.last_installed_schema.repository');
    $current = $last_installed->getLastInstalledFieldStorageDefinitions('node')['field_creator'];
    $this->assertSame('mukurtu_entity_reference_role', $current->getType());

    $settings = $current->getSettings();
    unset($settings['role_target_bundles']);
    $old = BaseFieldDefinition::create('entity_reference')
      ->setName('field_creator')
      ->setTargetEntityTypeId('node')
      ->setProvider($current->getProvider())
      ->setSettings($settings)
      ->setCardinality($current->getCardinality())
      ->setRevisionable($current->isRevisionable())
      ->setTranslatable($current->isTranslatable());

    $schema = $this->container->get('database')->schema();
    $key_value = $this->container->get('keyvalue')->get('entity.storage_schema.sql');
    $schema_data = $key_value->get('node.field_schema_data.field_creator');
    foreach (['node__field_creator', 'node_revision__field_creator'] as $table) {
      $schema->dropIndex($table, 'field_creator_role_target_id');
      $schema->dropField($table, 'field_creator_role_target_id');
      unset($schema_data[$table]['fields']['field_creator_role_target_id'], $schema_data[$table]['indexes']['field_creator_role_target_id']);
    }
    $key_value->set('node.field_schema_data.field_creator', $schema_data);
    $last_installed->setLastInstalledFieldStorageDefinition($old);
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

}
