<?php

declare(strict_types=1);

namespace Drupal\Tests\color_field\Functional;

/**
 * Tests color field widgets.
 *
 * @group color_field
 */
class ColorFieldWidgetTest extends ColorFieldFunctionalTestBase {

  /**
   * Test color_field_widget_html5.
   */
  public function testColorFieldWidgetHtml5(): void {
    $this->form->setComponent('field_color', [
      'type' => 'color_field_widget_html5',
    ])->save();

    $this->display->setComponent('field_color', [
      'type' => 'color_field_formatter_text',
      'weight' => 1,
    ])->save();

    $session = $this->assertSession();

    // Confirm field label and description are rendered.
    $this->drupalGet('node/add/article');
    $session->fieldExists("field_color[0][color]");
    $session->fieldExists("field_color[0][opacity]");
    $session->responseContains('Freeform Color');
    $session->responseContains('Color field description');

    // Test basic entry of color field.
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'field_color[0][color]' => "#E70000",
      'field_color[0][opacity]' => 1,
    ];

    $this->submitForm($edit, 'Save');
    $session->responseContains('#E70000 1</div>');
  }

}
