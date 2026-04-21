<?php

declare(strict_types=1);

namespace Drupal\Tests\color_field\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests for form grouping elements.
 *
 * @group form
 */
class ColorFieldWidgetJavascriptTests extends WebDriverTestBase {

  /**
   * The Entity View Display for the article node type.
   *
   * @var \Drupal\Core\Entity\Entity\EntityViewDisplay
   */
  protected EntityViewDisplay $display;

  /**
   * The Entity Form Display for the article node type.
   *
   * @var \Drupal\Core\Entity\Entity\EntityFormDisplay
   */
  protected EntityFormDisplay $form;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'node',
    'color_field',
  ];

  /**
   * Test color_field_widget_box.
   */
  public function testColorFieldWidgetBox(): void {
    $this->form
      ->setComponent('field_color_repeat', [
        'type' => 'color_field_widget_box',
        'settings' => [
          'default_colors' => '#ff0000,#FFFFFF',
        ],
      ])
      ->setComponent('field_color', [
        'type' => 'color_field_widget_box',
        'settings' => [
          'default_colors' => '#007749,#000000,#FFFFFF,#FFB81C,#E03C31,#001489,#ffafc8,#74d7ee',
        ],
      ])
      ->save();

    $session = $this->getSession();
    $web_assert = $this->assertSession();
    $this->drupalGet('node/add/article');

    $page = $session->getPage();

    // Wait for elements to be generated.
    $web_assert->waitForElementVisible('css', '#color-field-field-color-repeat button');

    $boxes = $page->findAll('css', '#color-field-field-color-repeat button');
    $this->assertEquals(3, count($boxes));

    // Confirm that help text/label are present.
    $web_assert->responseContains('Freeform Color');
    $web_assert->responseContains('Color field description');

    // Confirm that two fields aren't sharing settings.
    $boxes = $page->findAll('css', '#color-field-field-color button');
    $this->assertEquals(8, count($boxes));

    /** @var \Behat\Mink\Element\NodeElement $box */
    $box = $boxes[0];
    $this->assertEquals('#007749', $box->getAttribute('color'));

    // Only one of the fields has a default, so it is the only one that should
    // have a box that is selected. This also confirms that even if the storage
    // setting isn't uppercase hash prefixed hex that it still correctly selects
    // the right color in the color box widget.
    $active = $page->findAll('css', 'button.color_field_widget_box__square.active');
    $this->assertEquals(1, count($active));

    // Confirm that clicking the swatch sets the field value.
    $box->click();
    $field = $page->findField('field_color[0][color]');
    $this->assertEquals('#007749', $field->getValue());

    // Test that the edit of a saved color field also show the selected color.
    // This one tests for the default in uppercase.
    $node1 = $this->drupalCreateNode([
      'type' => 'article',
      'field_color_repeat' => [
        ['color' => 'ffffff'],
      ],
    ]);
    $this->drupalGet('/node/' . $node1->id() . '/edit');
    // Wait for elements to be generated.
    $web_assert->waitForElementVisible('css', '#color-field-field-color-repeat button');
    $active = $page->findAll('css', '#color-field-field-color-repeat button.color_field_widget_box__square.active');
    $this->assertEquals(1, count($active));

    // Test that the edit of a saved color field also show the selected color.
    // This one tests for the default in lowercase.
    $node2 = $this->drupalCreateNode([
      'type' => 'article',
      'field_color_repeat' => [
        ['color' => 'ff0000'],
      ],
    ]);
    $this->drupalGet('/node/' . $node2->id() . '/edit');
    // Wait for elements to be generated.
    $web_assert->waitForElementVisible('css', '#color-field-field-color-repeat button');
    $active = $page->findAll('css', '#color-field-field-color-repeat button.color_field_widget_box__square.active');
    $this->assertEquals(1, count($active));
  }

  /**
   * Test color_field_widget_spectrum widget.
   *
   * Unfortunately since the spectrum library uses clickable divs instead of
   * buttons, we can't use full interaction of clicks with elements. So instead
   * we just confirm that the right html has been generated and assume that the
   * library tests itself.
   *
   * Ensure that our handling of the palette is correctly handling different
   * types of color values. Like don't break if using commas in rgba values.
   */
  public function testColorFieldSpectrum(): void {
    $this->form
      ->setComponent('field_color_repeat', [
        'type' => 'color_field_widget_spectrum',
        'settings' => [
          'palette' => '["#0678BE","#53B0EB", "#96BC44"]',
          'show_palette' => FALSE,
        ],
      ])
      ->setComponent('field_color', [
        'type' => 'color_field_widget_spectrum',
        'settings' => [
          'palette' => '["#005493","#F5AA1C","#C63527","002754", hsv 0 100 100, "rgba(0,255,255,0.5)", green,hsl(0 100 50)]',
          'show_palette' => TRUE,
        ],
      ])
      ->save();

    // Disable alpha on second field.
    FieldConfig::load('node.article.field_color_repeat')
      ->setSetting('opacity', 0)
      ->save();

    $session = $this->getSession();
    $web_assert = $this->assertSession();
    $this->drupalGet('node/add/article');

    $page = $session->getPage();

    // Confirm that help text/label are present.
    $web_assert->responseContains('Freeform Color');
    $web_assert->responseContains('Color field description');

    // Wait for elements to be generated.
    $web_assert->waitForElementVisible('css', '.sp-preview');

    // Confirm that two fields aren't sharing settings.
    // Also confirms that custom palette is being used correctly and that the
    // one field's palette isn't shown. 4 for the one palette plus one each for
    // the widget and the current color value.
    $boxes = $page->findAll('css', '.sp-thumb-el');
    $this->assertEquals(13, count($boxes));

    // Confirm that alpha slider is hidden if the field doesn't support opacity.
    $alpha = $page->findAll('css', '.sp-alpha-enabled');
    $this->assertEquals(1, count($alpha));
  }

  /**
   * Test color_field_widget_grid widget.
   *
   * Unfortunately since the grid library ALSO uses clickable divs instead of
   * buttons. We could use $session->evaluateScript() to do it but we'll
   * presume it is  tested internally and just test the basic integration.
   */
  public function testColorFieldWidgetGrid(): void {
    $this->form
      ->setComponent('field_color_repeat', [
        'type' => 'color_field_widget_grid',
        'settings' => [
          'cell_width' => '20',
          'cell_height' => '20',
          'cell_margin' => '1',
          'box_width' => '250',
          'box_height' => '100',
          'columns' => '16',
        ],
      ])
      ->setComponent('field_color', [
        'type' => 'color_field_widget_grid',
        'settings' => [
          'cell_width' => '10',
          'cell_height' => '10',
          'cell_margin' => '1',
          'box_width' => '115',
          'box_height' => '20',
          'columns' => '16',
        ],
      ])
      ->save();

    $session = $this->getSession();
    $web_assert = $this->assertSession();
    $this->drupalGet('node/add/article');

    $page = $session->getPage();

    // Confirm that help text/label are present.
    $web_assert->responseContains('Freeform Color');
    $web_assert->responseContains('Color field description');

    // Wait for elements to be generated.
    $web_assert->waitForElementVisible('css', '.simpleColorDisplay');

    // Confirm that two fields aren't sharing settings.
    $boxes = $page->findAll('css', '.simpleColorDisplay');
    $this->assertEquals(2, count($boxes));
    $script = <<< HEREDOC
    (function() {
    fields = jQuery('.simpleColorDisplay');
    return jQuery(fields[0]).width() == jQuery(fields[1]).width()
    })()
HEREDOC;

    $this->assertFalse($session->evaluateScript($script));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article']);
    $user = $this->drupalCreateUser([
      'create article content', 'edit own article content',
    ]);
    $this->drupalLogin($user);
    $entityTypeManager = $this->container->get('entity_type.manager');
    FieldStorageConfig::create([
      'field_name' => 'field_color',
      'entity_type' => 'node',
      'type' => 'color_field_type',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_color',
      'label' => 'Freeform Color',
      'description' => 'Color field description',
      'entity_type' => 'node',
      'bundle' => 'article',
      'required' => TRUE,
      'default_value' => [
        [
          'color' => 'ffb81c',
          'opacity' => 0.5,
        ],
      ],
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_color_repeat',
      'entity_type' => 'node',
      'type' => 'color_field_type',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_color_repeat',
      'label' => 'Repeat Color',
      'description' => 'Color repeat description',
      'entity_type' => 'node',
      'bundle' => 'article',
      'required' => FALSE,
    ])->save();
    $this->form = $entityTypeManager->getStorage('entity_form_display')
      ->load('node.article.default');
    $this->display = $entityTypeManager->getStorage('entity_view_display')
      ->load('node.article.default');
  }

}
