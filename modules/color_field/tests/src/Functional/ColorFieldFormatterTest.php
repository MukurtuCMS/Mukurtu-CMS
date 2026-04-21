<?php

declare(strict_types=1);

namespace Drupal\Tests\color_field\Functional;

/**
 * Tests color field formatters.
 *
 * @group color_field
 */
class ColorFieldFormatterTest extends ColorFieldFunctionalTestBase {

  /**
   * Test color_field_formatter_text formatter.
   */
  public function testColorFieldFormatterText(): void {
    $this->form->setComponent('field_color', [
      'type' => 'color_field_widget_default',
      'settings' => [
        'placeholder_color' => '#ABC123',
        'placeholder_opacity' => '1.0',
      ],
    ])->save();

    $this->display->setComponent('field_color', [
      'type' => 'color_field_formatter_text',
      'weight' => 1,
      'label' => 'hidden',
    ])->save();

    // Display creation form.
    $this->drupalGet('node/add/article');
    $session = $this->assertSession();
    $session->fieldExists("field_color[0][color]");
    $session->fieldExists("field_color[0][opacity]");
    $session->responseContains('placeholder="#ABC123"');
    $session->responseContains('placeholder="1.0"');
    $session->responseContains('Freeform Color');
    $session->responseContains('Color field description');

    // Test basic entry of color field.
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'field_color[0][color]' => "#E70000",
      'field_color[0][opacity]' => 1,
    ];

    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains('#E70000 1</div>');

    // Ensure alternate hex format works.
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'field_color[0][color]' => "FF8C00",
      'field_color[0][opacity]' => 0.5,
    ];

    // Render without opacity value.
    $this->display->setComponent('field_color', [
      'type' => 'color_field_formatter_text',
      'weight' => 1,
      'settings' => [
        'opacity' => FALSE,
      ],
    ])->save();

    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains('#FF8C00</div>');

    // Test rgba Render mode.
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'field_color[0][color]' => "#FFEF00",
      'field_color[0][opacity]' => 0.9,
    ];
    $this->display->setComponent('field_color', [
      'type' => 'color_field_formatter_text',
      'weight' => 1,
      'settings' => [
        'format' => 'rgb',
        'opacity' => TRUE,
      ],
    ])->save();

    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains('rgba(255,239,0,0.9)');

    // Test RGB render mode.
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'field_color[0][color]' => "#00811F",
      'field_color[0][opacity]' => 0.8,
    ];
    $this->display->setComponent('field_color', [
      'type' => 'color_field_formatter_text',
      'weight' => 1,
      'settings' => [
        'format' => 'rgb',
        'opacity' => FALSE,
      ],
    ])->save();

    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains('rgb(0,129,31)');
  }

  /**
   * Test color_field_formatter_swatch formatter.
   */
  public function testColorFieldFormatterSwatch(): void {
    $this->form->setComponent('field_color', [
      'type' => 'color_field_widget_default',
      'settings' => [
        'placeholder_color' => '#ABC123',
        'placeholder_opacity' => '1.0',
      ],
    ])->save();
    $this->display->setComponent('field_color', [
      'type' => 'color_field_formatter_swatch',
      'weight' => 1,
      'label' => 'hidden',
    ])->save();

    // Test square with opacity.
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'field_color[0][color]' => "#0044FF",
      'field_color[0][opacity]' => 0.9,
    ];

    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains('background-color: rgba(0,68,255,0.9)');
    $this->assertSession()->responseContains('color_field__swatch--square');

    // Test circle without opacity.
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'field_color[0][color]' => "#760089",
      'field_color[0][opacity]' => 1,
    ];
    $this->display->setComponent('field_color', [
      'type' => 'color_field_formatter_swatch',
      'weight' => 1,
      'settings' => [
        'shape' => 'circle',
        'opacity' => FALSE,
      ],
    ])->save();

    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains('background-color: rgb(118,0,137)');
    $this->assertSession()->responseContains('color_field__swatch--circle');
  }

  /**
   * Test color_field_formatter_css formatter.
   */
  public function testColorFieldFormatterCss(): void {
    $this->form->setComponent('field_color', [
      'type' => 'color_field_widget_default',
      'settings' => [
        'placeholder_color' => '#ABC123',
        'placeholder_opacity' => '1.0',
      ],
    ])->save();
    $this->display->setComponent('field_color', [
      'type' => 'color_field_formatter_css',
      'weight' => 1,
      'label' => 'hidden',
    ])->save();

    // Test default options.
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'field_color[0][color]' => "#FFF430",
      'field_color[0][opacity]' => 0.9,
    ];

    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains('body { background-color: rgba(255,244,48,0.9) !important; }');

    // Test without opacity and not important.
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'field_color[0][color]' => "#FFFFFF",
      'field_color[0][opacity]' => 1,
    ];
    $this->display->setComponent('field_color', [
      'type' => 'color_field_formatter_css',
      'weight' => 1,
      'settings' => [
        'selector' => 'body',
        'property' => 'background-color',
        'important' => FALSE,
        'opacity' => FALSE,
      ],
      'label' => 'hidden',
    ])->save();

    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains('body { background-color: rgb(255,255,255); }');
  }

}
