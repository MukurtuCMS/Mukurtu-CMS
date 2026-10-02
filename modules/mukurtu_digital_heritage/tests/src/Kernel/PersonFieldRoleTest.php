<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_digital_heritage\Kernel;

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\mukurtu_core\Traits\EntityReferenceRoleUpdateTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests roles on Creator, Contributor and People, and their update path.
 */
#[Group('mukurtu_digital_heritage')]
class PersonFieldRoleTest extends DigitalHeritageTestBase {

  use EntityReferenceRoleUpdateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['flat_taxonomy', 'tagify'];

  /**
   * The person fields, keyed by name, valued by the vocabulary they use.
   */
  const FIELDS = [
    'field_creator' => 'creator',
    'field_contributor' => 'contributor',
    'field_people' => 'people',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // taxonomy_index is only maintained when this setting is on.
    $this->installConfig(['taxonomy']);
    foreach (self::FIELDS as $vid) {
      Vocabulary::create(['vid' => $vid, 'name' => $vid])->save();
    }
  }

  /**
   * Creates and saves a term.
   */
  protected function term(string $vid, string $name): Term {
    $term = Term::create(['vid' => $vid, 'name' => $name]);
    $term->save();
    return $term;
  }

  /**
   * Saves a digital heritage item with the given field values.
   */
  protected function saveItem(array $values): Node {
    $item = $this->buildDigitalHeritage('Item', [$this->createCategory('Category')]);
    foreach ($values as $field_name => $value) {
      $item->set($field_name, $value);
    }
    $item->save();
    return $item;
  }

  /**
   * Each person field stores a role per value, and the role is optional.
   */
  public function testRolesPersistOnEveryPersonField(): void {
    Vocabulary::create(['vid' => 'role', 'name' => 'Role'])->save();
    $role = $this->term('role', 'Singer');

    $values = [];
    $names = [];
    foreach (self::FIELDS as $field_name => $vid) {
      $names[$field_name] = [$this->term($vid, "$vid one"), $this->term($vid, "$vid two")];
      $values[$field_name] = [
        ['target_id' => $names[$field_name][0]->id(), 'role_target_id' => $role->id()],
        ['target_id' => $names[$field_name][1]->id()],
      ];
    }
    $item = $this->saveItem($values);

    $loaded = $this->reloadEntity('node', $item->id());
    foreach (self::FIELDS as $field_name => $vid) {
      $stored = $loaded->get($field_name)->getValue();
      $this->assertEquals($names[$field_name][0]->id(), $stored[0]['target_id'], $field_name);
      $this->assertEquals($role->id(), $stored[0]['role_target_id'], $field_name);
      $this->assertEquals($names[$field_name][1]->id(), $stored[1]['target_id'], $field_name);
      $this->assertNull($stored[1]['role_target_id'], $field_name);
    }
  }

  /**
   * A new, unsaved role term is saved along with the item.
   */
  public function testNewRoleTermIsSavedWithItem(): void {
    Vocabulary::create(['vid' => 'role', 'name' => 'Role'])->save();
    $creator = $this->term('creator', 'Eunice Kitto');
    $role = Term::create(['vid' => 'role', 'name' => 'Narrator']);

    $item = $this->saveItem([
      'field_creator' => [['target_id' => $creator->id(), 'role_entity' => $role]],
    ]);

    $this->assertFalse($role->isNew());
    $this->assertEquals($role->id(), $item->get('field_creator')->role_target_id);
  }

  /**
   * Term pages keep working: names are indexed and roles are not.
   */
  public function testTaxonomyIndexHoldsNamesNotRoles(): void {
    Vocabulary::create(['vid' => 'role', 'name' => 'Role'])->save();
    $role = $this->term('role', 'Singer');
    $values = [];
    $names = [];
    foreach (self::FIELDS as $field_name => $vid) {
      $names[$field_name] = $this->term($vid, "$vid name");
      $values[$field_name] = [['target_id' => $names[$field_name]->id(), 'role_target_id' => $role->id()]];
    }
    $item = $this->saveItem($values);

    $indexed = $this->container->get('database')->select('taxonomy_index', 'ti')
      ->fields('ti', ['tid'])
      ->condition('nid', $item->id())
      ->execute()
      ->fetchCol();
    // Control: a plain term reference on the same item is indexed too.
    $this->assertContains((string) $item->get('field_category')->target_id, $indexed);
    foreach ($names as $field_name => $name) {
      $this->assertContains((string) $name->id(), $indexed, $field_name);
    }
    $this->assertNotContains((string) $role->id(), $indexed);
  }

  /**
   * The update converts a site's existing node person fields.
   */
  public function testUpdateConvertsNodePersonFields(): void {
    $values = [];
    $names = [];
    foreach (self::FIELDS as $field_name => $vid) {
      $names[$field_name] = $this->term($vid, "$vid name");
      $values[$field_name] = [['target_id' => $names[$field_name]->id()]];
    }
    $item = $this->saveItem($values);

    foreach (array_keys(self::FIELDS) as $field_name) {
      $this->rollBackToEntityReference('node', $field_name);
      // Negative control: the rollback really leaves a pending change, so
      // the assertion after the update can't pass vacuously.
      $this->assertStoragePending(TRUE, 'node', $field_name);
    }
    $this->assertNull(Vocabulary::load('role'));

    $this->runRoleUpdate();

    $this->assertNotNull(Vocabulary::load('role'));
    $role = $this->term('role', 'Singer');
    $loaded = $this->reloadEntity('node', $item->id());
    foreach (array_keys(self::FIELDS) as $field_name) {
      $this->assertStoragePending(FALSE, 'node', $field_name);
      $this->assertEquals($names[$field_name]->id(), $loaded->get($field_name)->target_id, $field_name);
      $this->assertNull($loaded->get($field_name)->role_target_id, $field_name);
      $loaded->get($field_name)->role_target_id = $role->id();
    }
    $loaded->save();

    $reloaded = $this->reloadEntity('node', $item->id());
    foreach (array_keys(self::FIELDS) as $field_name) {
      $this->assertEquals($role->id(), $reloaded->get($field_name)->role_target_id, $field_name);
    }

    // Running it again is harmless.
    $this->runRoleUpdate();
  }

  /**
   * The update moves displays onto the role widget and formatter.
   *
   * Form displays all move to a role widget; view displays move only where
   * they use the plain label formatter.
   */
  public function testUpdateSwitchesDisplays(): void {
    $config_factory = $this->container->get('config.factory');
    $tagify_settings = [
      'match_operator' => 'STARTS_WITH',
      'match_limit' => 0,
      'placeholder' => '',
      'suggestions_dropdown' => 1,
    ];
    $this->writeDisplay('core.entity_form_display.node.digital_heritage.default', [
      // Tagify becomes Tagify with roles and keeps its settings.
      'field_people' => [
        'type' => 'tagify_entity_reference_autocomplete_widget',
        'weight' => 3,
        'settings' => $tagify_settings,
      ],
      // Any other widget moves too, with the new widget's defaults.
      'field_contributor' => [
        'type' => 'entity_reference_autocomplete',
        'weight' => 4,
        'settings' => ['size' => 40],
      ],
      // A role widget a site already chose is left alone.
      'field_creator' => [
        'type' => 'mukurtu_entity_reference_role_autocomplete',
        'weight' => 5,
        'settings' => ['size' => 30],
      ],
    ]);
    $this->writeDisplay('core.entity_view_display.node.digital_heritage.full', [
      'field_people' => ['type' => 'entity_reference_label', 'settings' => ['link' => TRUE]],
      'field_contributor' => ['type' => 'entity_reference_entity_id', 'settings' => []],
    ]);

    $this->runRoleUpdate();

    $form = $config_factory->get('core.entity_form_display.node.digital_heritage.default');
    $this->assertSame('mukurtu_entity_reference_role_tagify', $form->get('content.field_people.type'));
    $this->assertSame($tagify_settings, $form->get('content.field_people.settings'));
    $this->assertSame(3, $form->get('content.field_people.weight'));
    $this->assertSame('mukurtu_entity_reference_role_tagify', $form->get('content.field_contributor.type'));
    $this->assertSame([], $form->get('content.field_contributor.settings'));
    $this->assertSame('mukurtu_entity_reference_role_autocomplete', $form->get('content.field_creator.type'));
    $this->assertSame(['size' => 30], $form->get('content.field_creator.settings'));

    $view = $config_factory->get('core.entity_view_display.node.digital_heritage.full');
    $this->assertSame('mukurtu_entity_reference_role_label', $view->get('content.field_people.type'));
    $this->assertSame(['link' => TRUE], $view->get('content.field_people.settings'));
    $this->assertSame('entity_reference_entity_id', $view->get('content.field_contributor.type'));
  }

  /**
   * Writes raw display config, as an existing site would have it.
   */
  protected function writeDisplay(string $name, array $content): void {
    [, , $entity_type_id, $bundle, $mode] = explode('.', $name);
    foreach ($content as &$component) {
      $component += ['region' => 'content', 'third_party_settings' => []];
    }
    $this->container->get('config.factory')->getEditable($name)->setData([
      'id' => "$entity_type_id.$bundle.$mode",
      'targetEntityType' => $entity_type_id,
      'bundle' => $bundle,
      'mode' => $mode,
      'content' => $content,
    ])->save();
  }

}
