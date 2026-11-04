<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use Drupal\mukurtu_core\Hook\ParagraphsWidgetHooks;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Paragraphs widget's Collapse / Expand button changes.
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
   * A form state whose triggering element is the named button.
   */
  private function triggeredBy(?string $name = NULL): FormState {
    $form_state = new FormState();
    if ($name) {
      $form_state->setTriggeringElement(['#name' => $name]);
    }
    return $form_state;
  }

  /**
   * A button as Paragraphs builds it.
   */
  private function button(string $label, string $name, bool $with_title = FALSE): array {
    $button = [
      '#type' => 'submit',
      '#value' => $label,
      '#name' => $name,
      '#attributes' => ['class' => ['paragraphs-icon-button'], 'title' => $label],
    ];
    if ($with_title) {
      $button['#title'] = $label;
    }
    return $button;
  }

  /**
   * An item element with one button and, optionally, summary text.
   */
  private function item(string $key, array $button, array $summary_content = ['An apple a day.']): array {
    return [
      'top' => [
        'summary' => [
          '#type' => 'container',
          'fields_info' => ['#summary' => ['content' => $summary_content, 'behaviors' => []]],
        ],
        'actions' => ['actions' => [$key => $button]],
      ],
    ];
  }

  /**
   * A collapsed item's Edit button reads Expand.
   */
  public function testItemEditButtonReadsExpand(): void {
    $element = $this->item('edit_button', $this->button('Edit', 'field_x_0_edit'));

    $this->hooks()->itemButtons($element, $this->triggeredBy(), []);

    $button = $element['top']['actions']['actions']['edit_button'];
    $this->assertSame('Expand', $button['#value']);
    $this->assertSame('Expand', $button['#attributes']['title']);
    $this->assertSame('field_x_0_edit', $button['#name'], 'The button name drives Paragraphs\' submit handling and must not change.');
    $this->assertSame(['paragraphs-icon-button'], $button['#attributes']['class']);
    $this->assertContains('mukurtu_core/paragraphs_widget_focus', $element['#attached']['library']);
  }

  /**
   * An open item's Collapse button keeps its label.
   */
  public function testCollapseButtonKeepsLabel(): void {
    $element = $this->item('collapse_button', $this->button('Collapse', 'field_x_0_collapse'));

    $this->hooks()->itemButtons($element, $this->triggeredBy(), []);

    $this->assertSame('Collapse', $element['top']['actions']['actions']['collapse_button']['#value']);
  }

  /**
   * Item buttons are described by the item's summary.
   */
  public function testItemButtonDescribedBySummary(): void {
    foreach (['edit_button' => 'field_x_0_edit', 'collapse_button' => 'field_x_0_collapse'] as $key => $name) {
      $element = $this->item($key, $this->button('Label', $name));

      $this->hooks()->itemButtons($element, $this->triggeredBy(), []);

      $this->assertSame('field-x-0-summary', $element['top']['summary']['#id'], $key);
      $this->assertSame('field-x-0-summary', $element['top']['actions']['actions'][$key]['#attributes']['aria-describedby'], $key);
    }
  }

  /**
   * An item with no summary text gets no description.
   */
  public function testItemWithoutSummaryHasNoDescription(): void {
    $element = $this->item('edit_button', $this->button('Edit', 'field_x_0_edit'), []);

    $this->hooks()->itemButtons($element, $this->triggeredBy(), []);

    $this->assertArrayNotHasKey('aria-describedby', $element['top']['actions']['actions']['edit_button']['#attributes']);
    $this->assertArrayNotHasKey('#id', $element['top']['summary']);
  }

  /**
   * After Collapse is clicked, the item's new Expand button takes focus.
   */
  public function testItemFocusAfterItsPairIsClicked(): void {
    $element = $this->item('edit_button', $this->button('Edit', 'field_x_0_edit'));

    $this->hooks()->itemButtons($element, $this->triggeredBy('field_x_0_collapse'), []);

    $attributes = $element['top']['actions']['actions']['edit_button']['#attributes'];
    $this->assertArrayHasKey(ParagraphsWidgetHooks::FOCUS_ATTRIBUTE, $attributes);
    $this->assertSame('true', $attributes['data-disable-refocus']);
  }

  /**
   * Another item's button doesn't take focus.
   */
  public function testItemNoFocusWhenAnotherItemIsClicked(): void {
    $element = $this->item('edit_button', $this->button('Edit', 'field_x_1_edit'));

    $this->hooks()->itemButtons($element, $this->triggeredBy('field_x_0_collapse'), []);

    $attributes = $element['top']['actions']['actions']['edit_button']['#attributes'];
    $this->assertArrayNotHasKey(ParagraphsWidgetHooks::FOCUS_ATTRIBUTE, $attributes);
    $this->assertSame('true', $attributes['data-disable-refocus']);
  }

  /**
   * Edit all reads Expand all and takes focus after Collapse all.
   */
  public function testEditAllReadsExpandAllWhenCollapsed(): void {
    $form = [
      'widget' => [
        'header_actions' => [
          'actions' => ['edit_all' => $this->button('Edit all', 'field_x_edit_all', TRUE)],
          'dropdown_actions' => ['collapse_all' => $this->button('Collapse all', 'field_x_collapse_all')],
        ],
      ],
    ];

    $this->hooks()->headerButtons($form, $this->triggeredBy('field_x_collapse_all'), []);

    $edit_all = $form['widget']['header_actions']['actions']['edit_all'];
    $this->assertSame('Expand all', $edit_all['#value']);
    $this->assertSame('Expand all', $edit_all['#attributes']['title']);
    $this->assertSame('Expand all', $edit_all['#title']);
    $this->assertArrayHasKey(ParagraphsWidgetHooks::FOCUS_ATTRIBUTE, $edit_all['#attributes']);

    $collapse_all = $form['widget']['header_actions']['dropdown_actions']['collapse_all'];
    $this->assertSame('Collapse all', $collapse_all['#value']);
    $this->assertArrayNotHasKey(ParagraphsWidgetHooks::FOCUS_ATTRIBUTE, $collapse_all['#attributes'], 'A button hidden in the dropdown never takes focus.');
    $this->assertSame('true', $collapse_all['#attributes']['data-disable-refocus'], 'Drupal must not refocus a parent after a dropdown button is clicked either.');
  }

  /**
   * Edit all reads Expand all in the dropdown while items are open.
   */
  public function testEditAllReadsExpandAllInDropdown(): void {
    $edit_all = $this->button('Edit all', 'field_x_edit_all');
    unset($edit_all['#attributes']['title']);
    $form = [
      'widget' => [
        'header_actions' => [
          'actions' => ['collapse_all' => $this->button('Collapse all', 'field_x_collapse_all')],
          'dropdown_actions' => ['edit_all' => $edit_all],
        ],
      ],
    ];

    $this->hooks()->headerButtons($form, $this->triggeredBy('field_x_edit_all'), []);

    $edit_all = $form['widget']['header_actions']['dropdown_actions']['edit_all'];
    $this->assertSame('Expand all', $edit_all['#value']);
    $this->assertArrayNotHasKey('title', $edit_all['#attributes'], 'No tooltip is added where Paragraphs had none.');
    $this->assertArrayNotHasKey('#title', $edit_all);
    $this->assertArrayHasKey(ParagraphsWidgetHooks::FOCUS_ATTRIBUTE, $form['widget']['header_actions']['actions']['collapse_all']['#attributes']);
  }

  /**
   * A widget with a single item has no header actions, and nothing is added.
   */
  public function testNoHeaderActions(): void {
    $form = ['widget' => ['#type' => 'container']];
    $before = $form;

    $this->hooks()->headerButtons($form, $this->triggeredBy(), []);

    $this->assertSame($before, $form);
  }

}
