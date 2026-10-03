<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_digital_heritage\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_core\Plugin\Field\FieldWidget\EntityReferenceRoleTagifyWidget;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how the "Tagify with roles" widget pairs chips with roles.
 */
#[Group('mukurtu_digital_heritage')]
class RoleTagifyWidgetTest extends DigitalHeritageTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['tagify'];

  /**
   * The widget under test, on field_creator.
   */
  protected EntityReferenceRoleTagifyWidget $widget;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    Vocabulary::create(['vid' => 'creator', 'name' => 'Creator'])->save();
    Vocabulary::create(['vid' => 'role', 'name' => 'Role'])->save();

    $definition = $this->container->get('entity_field.manager')
      ->getFieldDefinitions('node', 'digital_heritage')['field_creator'];
    $this->widget = $this->container->get('plugin.manager.field.widget')->getInstance([
      'field_definition' => $definition,
      'form_mode' => 'default',
      'configuration' => ['type' => 'mukurtu_entity_reference_role_tagify', 'settings' => []],
    ]);
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
   * Runs the widget's massageFormValues() on submitted names and roles.
   */
  protected function massage(array $names, array $roles): array {
    return $this->widget->massageFormValues(
      ['names' => json_encode($names), 'roles' => json_encode($roles)],
      [],
      new FormState(),
    );
  }

  /**
   * Each chip gets the role in the same position, in any input format.
   */
  public function testRolesPairWithChipsByPosition(): void {
    $kitto = $this->term('creator', 'Eunice Kitto');
    $baskin = $this->term('creator', 'Eunice Baskin');
    $fletcher = $this->term('creator', 'Alice Fletcher');
    $singer = $this->term('role', 'Singer');
    $writer = $this->term('role', 'Writer');

    $items = $this->massage(
      [
        ['value' => $kitto->id(), 'label' => 'Eunice Kitto', 'entity_id' => $kitto->id()],
        ['value' => $baskin->id(), 'label' => 'Eunice Baskin', 'entity_id' => $baskin->id()],
        ['value' => $fletcher->id(), 'label' => 'Alice Fletcher', 'entity_id' => $fletcher->id()],
      ],
      // Autocomplete format, a bare existing name, and no role.
      ["Singer ({$singer->id()})", 'Writer', ''],
    );

    $this->assertCount(3, $items);
    $this->assertEquals($kitto->id(), $items[0]['target_id']);
    $this->assertEquals($singer->id(), $items[0]['role_target_id']);
    $this->assertEquals($baskin->id(), $items[1]['target_id']);
    $this->assertEquals($writer->id(), $items[1]['role_target_id']);
    $this->assertEquals($fletcher->id(), $items[2]['target_id']);
    $this->assertArrayNotHasKey('role_target_id', $items[2]);
    $this->assertArrayNotHasKey('role_entity', $items[2]);
  }

  /**
   * A new name and a new role are both created, and stay paired.
   */
  public function testNewNameAndNewRoleAreCreated(): void {
    $items = $this->massage([['value' => 'Annie James']], ['Narrator']);

    $this->assertCount(1, $items);
    $this->assertSame('Annie James', $items[0]['entity']->label());
    $this->assertTrue($items[0]['role_entity']->isNew());
    $this->assertSame('Narrator', $items[0]['role_entity']->label());
    $this->assertSame('role', $items[0]['role_entity']->bundle());

    // Saving the item creates both terms and links them.
    $item = $this->buildDigitalHeritage('Item', [$this->createCategory('Category')]);
    $item->set('field_creator', $items);
    $item->save();
    $saved = $item->get('field_creator')->first();
    $this->assertSame('Annie James', $saved->entity->label());
    $this->assertSame('Narrator', Term::load($saved->role_target_id)->label());
  }

  /**
   * A role ID from another vocabulary is not accepted as a role.
   */
  public function testRoleFromOtherVocabularyIsNotUsedById(): void {
    $kitto = $this->term('creator', 'Eunice Kitto');
    $items = $this->massage(
      [['value' => $kitto->id(), 'label' => 'Eunice Kitto', 'entity_id' => $kitto->id()]],
      ["Eunice Kitto ({$kitto->id()})"],
    );
    $this->assertArrayNotHasKey('role_target_id', $items[0]);
  }

  /**
   * Missing or malformed roles leave names unaffected.
   */
  public function testMissingRolesLeaveNamesAlone(): void {
    $kitto = $this->term('creator', 'Eunice Kitto');
    $names = [['value' => $kitto->id(), 'label' => 'Eunice Kitto', 'entity_id' => $kitto->id()]];

    $items = $this->widget->massageFormValues(['names' => json_encode($names), 'roles' => 'not json'], [], new FormState());
    $this->assertEquals($kitto->id(), $items[0]['target_id']);
    $this->assertArrayNotHasKey('role_target_id', $items[0]);

    $this->assertSame([], $this->massage([], ['Singer']));
  }

  /**
   * With several role vocabularies, an unknown role is an error, not lost.
   */
  public function testUnknownRoleIsAnErrorWhenItCannotBeCreated(): void {
    Vocabulary::create(['vid' => 'other_role', 'name' => 'Other role'])->save();
    $definition = $this->container->get('entity_field.manager')
      ->getFieldDefinitions('node', 'digital_heritage')['field_creator'];
    $definition->setSetting('role_target_bundles', ['role' => 'role', 'other_role' => 'other_role']);
    $widget = $this->container->get('plugin.manager.field.widget')->getInstance([
      'field_definition' => $definition,
      'form_mode' => 'default',
      'configuration' => ['type' => 'mukurtu_entity_reference_role_tagify', 'settings' => []],
    ]);
    $kitto = $this->term('creator', 'Eunice Kitto');
    $this->term('other_role', 'Singer');
    $names = json_encode([
      ['value' => $kitto->id(), 'label' => 'Eunice Kitto', 'entity_id' => $kitto->id()],
      ['value' => $kitto->id(), 'label' => 'Eunice Kitto', 'entity_id' => $kitto->id()],
    ]);

    // Control: a role that exists in either vocabulary is accepted.
    $form_state = new FormState();
    $items = $widget->massageFormValues(['names' => $names, 'roles' => json_encode(['Singer', ''])], [], $form_state);
    $this->assertFalse($form_state->hasAnyErrors());
    $this->assertArrayHasKey('role_target_id', $items[0]);

    $form_state = new FormState();
    $widget->massageFormValues(['names' => $names, 'roles' => json_encode(['Nonexistent', ''])], [], $form_state);
    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertStringContainsString('Nonexistent', (string) $form_state->getErrors()['field_creator']);

    // Submit handlers that rebuild the entity run after validation, when
    // setting an error would throw; the role is just left off there.
    $form_state = new FormState();
    $form_state->setValidationComplete();
    $items = $widget->massageFormValues(['names' => $names, 'roles' => json_encode(['Nonexistent', ''])], [], $form_state);
    $this->assertArrayNotHasKey('role_target_id', $items[0]);
  }

  /**
   * Two people given the same new role share one new term.
   */
  public function testSameNewRoleIsCreatedOnce(): void {
    $items = $this->massage([['value' => 'Annie James'], ['value' => 'Mary Jones']], ['Narrator', 'narrator']);
    $this->assertSame($items[0]['role_entity'], $items[1]['role_entity']);

    $item = $this->buildDigitalHeritage('Item', [$this->createCategory('Category')]);
    $item->set('field_creator', $items);
    $item->save();
    $this->assertCount(1, $this->container->get('entity_type.manager')->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'role', 'name' => 'Narrator']));
    $this->assertSame($item->get('field_creator')[0]->role_target_id, $item->get('field_creator')[1]->role_target_id);
  }

}
