<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use Drupal\mukurtu_core\Hook\ParagraphsWidgetHooks;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Paragraphs widget's Expand / Expand all labels.
 *
 * @see \Drupal\mukurtu_core\Hook\ParagraphsWidgetHooks
 */
#[Group('mukurtu_core')]
class ParagraphsWidgetHooksTest extends UnitTestCase {

  /**
   * Builds the hooks with a pass-through translation stub.
   */
  private function hooks(): ParagraphsWidgetHooks {
    $hooks = new ParagraphsWidgetHooks();
    $hooks->setStringTranslation($this->getStringTranslationStub());
    return $hooks;
  }

  /**
   * A button as Paragraphs builds it.
   */
  private function button(string $label, bool $with_title = FALSE): array {
    $button = [
      '#type' => 'submit',
      '#value' => $label,
      '#name' => 'field_x_0_edit',
      '#attributes' => ['class' => ['paragraphs-icon-button'], 'title' => $label],
    ];
    if ($with_title) {
      $button['#title'] = $label;
    }
    return $button;
  }

  /**
   * A collapsed item's Edit button reads Expand.
   */
  public function testItemEditButtonReadsExpand(): void {
    $element = ['top' => ['actions' => ['actions' => ['edit_button' => $this->button('Edit')]]]];

    $this->hooks()->itemButtons($element, new FormState(), []);

    $button = $element['top']['actions']['actions']['edit_button'];
    $this->assertSame('Expand', $button['#value']);
    $this->assertSame('Expand', $button['#attributes']['title']);
    $this->assertSame('field_x_0_edit', $button['#name'], 'The button name drives Paragraphs\' submit handling and must not change.');
    $this->assertSame(['paragraphs-icon-button'], $button['#attributes']['class']);
  }

  /**
   * An open item's Collapse button is left alone.
   */
  public function testOpenItemIsUntouched(): void {
    $element = ['top' => ['actions' => ['actions' => ['collapse_button' => $this->button('Collapse')]]]];
    $before = $element;

    $this->hooks()->itemButtons($element, new FormState(), []);

    $this->assertSame($before, $element);
  }

  /**
   * Edit all reads Expand all, as a main action while items are collapsed.
   */
  public function testEditAllReadsExpandAllWhenCollapsed(): void {
    $form = ['widget' => ['header_actions' => [
      'actions' => ['edit_all' => $this->button('Edit all', TRUE)],
      'dropdown_actions' => ['collapse_all' => $this->button('Collapse all')],
    ]]];

    $this->hooks()->headerButtons($form, new FormState(), []);

    $edit_all = $form['widget']['header_actions']['actions']['edit_all'];
    $this->assertSame('Expand all', $edit_all['#value']);
    $this->assertSame('Expand all', $edit_all['#attributes']['title']);
    $this->assertSame('Expand all', $edit_all['#title']);
    $this->assertSame('Collapse all', $form['widget']['header_actions']['dropdown_actions']['collapse_all']['#value']);
  }

  /**
   * Edit all reads Expand all in the dropdown while items are open.
   */
  public function testEditAllReadsExpandAllInDropdown(): void {
    $edit_all = $this->button('Edit all');
    unset($edit_all['#attributes']['title']);
    $form = ['widget' => ['header_actions' => [
      'actions' => ['collapse_all' => $this->button('Collapse all')],
      'dropdown_actions' => ['edit_all' => $edit_all],
    ]]];

    $this->hooks()->headerButtons($form, new FormState(), []);

    $edit_all = $form['widget']['header_actions']['dropdown_actions']['edit_all'];
    $this->assertSame('Expand all', $edit_all['#value']);
    $this->assertArrayNotHasKey('title', $edit_all['#attributes'], 'No tooltip is added where Paragraphs had none.');
    $this->assertArrayNotHasKey('#title', $edit_all);
  }

  /**
   * A widget with a single item has no header actions, and nothing is added.
   */
  public function testNoHeaderActions(): void {
    $form = ['widget' => ['#type' => 'container']];
    $before = $form;

    $this->hooks()->headerButtons($form, new FormState(), []);

    $this->assertSame($before, $form);
  }

}
