<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_digital_heritage\Kernel;

use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_core\Hook\TagifyWidgetHooks;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Tagify help line that mentions the reorder arrows (#2710).
 */
#[CoversClass(TagifyWidgetHooks::class)]
#[Group('mukurtu_digital_heritage')]
class TagifyReorderHelpTextTest extends DigitalHeritageTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['tagify'];

  /**
   * The help line that mentions the arrow buttons.
   */
  protected const REORDER_MESSAGE = 'To change the order of @entity_types, drag them into place, or use the arrow buttons on each one.';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['keywords' => 'Keywords', 'creator' => 'Creator', 'role' => 'Role'] as $vid => $name) {
      Vocabulary::create(['vid' => $vid, 'name' => $name])->save();
    }
  }

  /**
   * Builds a field's widget and returns its help line.
   */
  protected function description(string $field_name, string $widget_type): mixed {
    $node = Node::create(['type' => 'digital_heritage', 'title' => 'Item']);
    $items = $node->get($field_name);
    $widget = $this->container->get('plugin.manager.field.widget')->getInstance([
      'field_definition' => $items->getFieldDefinition(),
      'form_mode' => 'default',
      'configuration' => ['type' => $widget_type, 'settings' => []],
    ]);
    $form = ['#parents' => []];
    $element = $widget->form($items, $form, new FormState());
    // "Tagify with roles" wraps the Tagify element as its "names" child.
    return $element['widget']['names']['#description'] ?? $element['widget']['#description'] ?? NULL;
  }

  /**
   * Asserts that a help line, or one of its items, is the given string.
   */
  protected function assertHelpLine(string $expected, mixed $description): TranslatableMarkup {
    if (($description['#theme'] ?? NULL) === 'item_list') {
      $description = end($description['#items']);
    }
    $this->assertInstanceOf(TranslatableMarkup::class, $description);
    $this->assertSame($expected, $description->getUntranslatedString());
    return $description;
  }

  /**
   * A multi-value Tagify field mentions the arrows, naming its item type.
   */
  public function testTagifyFieldMentionsArrows(): void {
    $line = $this->assertHelpLine(static::REORDER_MESSAGE, $this->description('field_keywords', 'tagify_entity_reference_autocomplete_widget'));
    $this->assertEquals('taxonomy terms', $line->getArguments()['@entity_types']);
  }

  /**
   * Other help lines are left alone, including the field's own help.
   *
   * A single-value field has no drag line, and its help is markup rather
   * than a list, which crashed the Person form.
   */
  public function testOtherHelpIsLeftAlone(): void {
    $hooks = new TagifyWidgetHooks();
    $hooks->setStringTranslation($this->container->get('string_translation'));
    $help = FieldFilteredMarkup::create('Choose a relationship.');
    $element = ['#description' => $help];
    $hooks->tagifyWidgetFormAlter($element, new FormState(), []);
    $this->assertSame($help, $element['#description']);

    $element = ['#type' => 'entity_autocomplete_tagify'];
    $hooks->tagifyWidgetFormAlter($element, new FormState(), []);
    $this->assertArrayNotHasKey('#description', $element);
  }

  /**
   * The "Tagify with roles" widget keeps its own line while rows are shown.
   */
  public function testRoleWidgetWithRolesKeepsItsLine(): void {
    $this->config('mukurtu_core.person_roles')->set('enabled_fields', ['field_creator'])->save();
    $this->assertHelpLine('Drag or use the arrow buttons to reorder.', $this->description('field_creator', 'mukurtu_entity_reference_role_tagify'));
  }

  /**
   * With roles off, the "Tagify with roles" widget shows chips and arrows.
   */
  public function testRoleWidgetWithoutRolesMentionsArrows(): void {
    $this->config('mukurtu_core.person_roles')->set('enabled_fields', [])->save();
    $this->assertHelpLine(static::REORDER_MESSAGE, $this->description('field_creator', 'mukurtu_entity_reference_role_tagify'));
  }

}
