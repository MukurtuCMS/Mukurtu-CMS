<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_digital_heritage\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_core\Form\PersonRoleSettingsForm;
use Drupal\mukurtu_core\Plugin\Field\FieldType\EntityReferenceRoleItem;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the manager setting that turns roles on or off per person field.
 */
#[Group('mukurtu_digital_heritage')]
class PersonRoleSettingsTest extends DigitalHeritageTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['flat_taxonomy', 'tagify'];

  /**
   * An item with one creator who has the role "Singer".
   */
  protected NodeInterface $item;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // The formatter links names, which looks up path aliases.
    $this->installEntitySchema('path_alias');
    $this->config(EntityReferenceRoleItem::SETTINGS)->set('enabled_fields', [])->save();
    Vocabulary::create(['vid' => 'creator', 'name' => 'Creator'])->save();
    Vocabulary::create(['vid' => 'role', 'name' => 'Role'])->save();
    $creator = Term::create(['vid' => 'creator', 'name' => 'Eunice Kitto']);
    $creator->save();
    $role = Term::create(['vid' => 'role', 'name' => 'Singer']);
    $role->save();
    $this->item = $this->buildDigitalHeritage('Item', [$this->createCategory('Category')]);
    $this->item->set('field_creator', [['target_id' => $creator->id(), 'role_target_id' => $role->id()]]);
    $this->item->save();
  }

  /**
   * Turns roles on for the given fields.
   */
  protected function enableRoles(array $field_names): void {
    $this->config(EntityReferenceRoleItem::SETTINGS)->set('enabled_fields', $field_names)->save();
  }

  /**
   * Renders field_creator with the role label formatter.
   */
  protected function renderCreator(): string {
    $build = $this->item->get('field_creator')->view(['type' => 'mukurtu_entity_reference_role_label']);
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

  /**
   * Builds field_creator's form element with the given widget.
   */
  protected function buildWidget(string $type): array {
    $widget = $this->container->get('plugin.manager.field.widget')->getInstance([
      'field_definition' => $this->item->getFieldDefinition('field_creator'),
      'form_mode' => 'default',
      'configuration' => ['type' => $type, 'settings' => []],
    ]);
    $form = ['#parents' => []];
    return $widget->form($this->item->get('field_creator'), $form, new FormState());
  }

  /**
   * Shipped person fields start with roles off; other role fields stay on.
   */
  public function testRolesStartOffForShippedFields(): void {
    foreach (EntityReferenceRoleItem::MANAGED_FIELDS as $field_name) {
      $this->assertFalse(EntityReferenceRoleItem::rolesEnabled($field_name), $field_name);
    }
    $this->assertTrue(EntityReferenceRoleItem::rolesEnabled('field_site_added_people'));

    $this->enableRoles(['field_creator']);
    $this->assertTrue(EntityReferenceRoleItem::rolesEnabled('field_creator'));
    $this->assertFalse(EntityReferenceRoleItem::rolesEnabled('field_people'));
  }

  /**
   * The formatter shows the role only when roles are on for the field.
   */
  public function testFormatterFollowsSetting(): void {
    $off = $this->renderCreator();
    $this->assertStringContainsString('Eunice Kitto', $off);
    $this->assertStringNotContainsString('Singer', $off);

    $this->enableRoles(['field_creator']);
    $this->assertStringContainsString('(Singer)', $this->renderCreator());

    // Every name carries the setting's cache tag, so changing it refreshes
    // cached pages either way.
    $build = $this->item->get('field_creator')->view(['type' => 'mukurtu_entity_reference_role_label']);
    $this->assertContains('config:' . EntityReferenceRoleItem::SETTINGS, $build[0]['#cache']['tags']);
  }

  /**
   * With roles off, the Tagify widget is names-only but still sends roles.
   */
  public function testTagifyWidgetWithRolesOff(): void {
    $element = $this->buildWidget('mukurtu_entity_reference_role_tagify')['widget'];
    $this->assertContains('mukurtu-role-tagify--names-only', $element['#attributes']['class']);
    // The existing role is still in the hidden field the script submits.
    $this->assertSame(['Singer'], json_decode($element['roles']['#default_value'], TRUE));
    $this->assertContains('config:' . EntityReferenceRoleItem::SETTINGS, $element['#cache']['tags']);

    $this->enableRoles(['field_creator']);
    $element = $this->buildWidget('mukurtu_entity_reference_role_tagify')['widget'];
    $this->assertNotContains('mukurtu-role-tagify--names-only', $element['#attributes']['class']);
  }

  /**
   * With roles off, the row widget hides the role box but keeps the role.
   */
  public function testRowWidgetWithRolesOff(): void {
    $role_id = $this->item->get('field_creator')->role_target_id;
    $row = $this->buildWidget('mukurtu_entity_reference_role_autocomplete')['widget'][0];
    $this->assertSame('value', $row['role_target_id']['#type']);
    $this->assertEquals($role_id, $row['role_target_id']['#value']);

    $this->enableRoles(['field_creator']);
    $row = $this->buildWidget('mukurtu_entity_reference_role_autocomplete')['widget'][0];
    $this->assertSame('entity_autocomplete', $row['role_target_id']['#type']);
  }

  /**
   * The settings form saves only the checked fields.
   */
  public function testSettingsFormSavesCheckedFields(): void {
    $form_state = (new FormState())->setValues([
      // As a browser submits it: unchecked boxes are left out.
      'enabled_fields' => ['field_creator' => 'field_creator', 'field_people' => 'field_people'],
    ]);
    $this->container->get('form_builder')->submitForm(PersonRoleSettingsForm::class, $form_state);

    $this->assertSame([], $form_state->getErrors());
    $this->assertSame(['field_creator', 'field_people'], $this->config(EntityReferenceRoleItem::SETTINGS)->get('enabled_fields'));
  }

}
