<?php

namespace Drupal\Tests\better_exposed_filters\FunctionalJavascript;

use Drupal\views\Views;

/**
 * Tests the basic AJAX functionality of BEF exposed forms.
 *
 * @group better_exposed_filters
 */
class BetterExposedFiltersSliderTest extends BetterExposedFiltersTestBase {

  /**
   * Tests a single slider field.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBefSliderSingle(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_price_value' => [
          'plugin_id' => 'bef_sliders',
          'enable_tooltips' => TRUE,
          'tooltips_value_prefix' => 'Prefix',
          'tooltips_value_suffix' => 'Suffix',
        ],
      ],
    ]);
    $this->drupalGet('/bef-test');

    $session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Test for tooltips.
    $session->pageTextContains('Prefix 0 Suffix');

    // Verify input for slider is present.
    $session->fieldExists('field_bef_price_value');
    $session->fieldValueEquals('field_bef_price_value', '0');
    // Verify the slider from noUiSlider.
    $session->elementExists('css', '.bef-slider');
    $session->elementAttributeContains('css', '.bef-slider .noUi-handle', 'aria-valuenow', '0.0');

    // Update the input field to trigger the slider.
    $sliderField = $page->find('css', '#edit-field-bef-price-value');
    $sliderField->setValue('50');

    $page->find('css', '#edit-items-per-page')->focus();

    // Verify the slider updated.
    $session->elementAttributeContains('css', '.bef-slider .noUi-handle', 'aria-valuenow', '50.0');

    $this->submitForm([], 'Apply');

    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextNotContains('Page Two');
  }

  /**
   * Tests an in between slider.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBefSliderBetween(): void {
    $view = Views::getView('bef_test');
    $view->storage->getDisplay('default')['display_options']['filters']['field_bef_price_value']['operator'] = 'between';
    $view->save();

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_price_value' => [
          'plugin_id' => 'bef_sliders',
          'min' => 0,
          'max' => 100,
        ],
      ],
    ]);

    $this->drupalGet('/bef-test');

    $session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Verify input for slider is present.
    $session->fieldExists('field_bef_price_value[min]');
    $session->fieldExists('field_bef_price_value[max]');
    $session->fieldValueEquals('field_bef_price_value[min]', '0');
    $session->fieldValueEquals('field_bef_price_value[max]', '100');
    // Verify the slider from noUiSlider.
    $session->elementExists('css', '.bef-slider');
    $session->elementAttributeContains('css', '.bef-slider .noUi-handle.noUi-handle-lower', 'aria-valuenow', '0.0');
    $session->elementAttributeContains('css', '.bef-slider .noUi-handle.noUi-handle-upper', 'aria-valuenow', '100.0');

    // Update the input field to trigger the slider.
    $sliderFieldMin = $page->find('css', '#edit-field-bef-price-value-min');
    $sliderFieldMin->setValue('5');

    $sliderFieldMax = $page->find('css', '#edit-field-bef-price-value-max');
    $sliderFieldMax->setValue('15');

    $page->find('css', '#edit-items-per-page')->focus();

    // Verify the slider updated.
    $session->elementAttributeContains('css', '.bef-slider .noUi-handle.noUi-handle-lower', 'aria-valuenow', '5.0');
    $session->elementAttributeContains('css', '.bef-slider .noUi-handle.noUi-handle-upper', 'aria-valuenow', '15.0');

    $this->submitForm([], 'Apply');
    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextNotContains('Page Two');
  }

  /**
   * Tests an in between slider collapsible feature.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testBefSliderCollapsible(): void {
    $view = Views::getView('bef_test');
    $view->storage->getDisplay('default')['display_options']['filters']['field_bef_price_value']['operator'] = 'between';
    $view->save();

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_price_value' => [
          'plugin_id' => 'bef_sliders',
          'advanced' => [
            'collapsible' => TRUE,
          ],
        ],
      ],
    ]);

    $this->drupalGet('/bef-test');

    $session = $this->assertSession();
    $session->elementExists('css', '#edit-field-bef-price-value-collapsible');
  }

  /**
   * Test the placement of the slider.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testBefSliderPlacement(): void {
    $view = Views::getView('bef_test');
    $view->storage->getDisplay('default')['display_options']['filters']['field_bef_price_value']['operator'] = 'between';
    $view->save();

    $view = Views::getView('bef_test');
    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_price_value' => [
          'plugin_id' => 'bef_sliders',
          'placement_location' => 'start',
        ],
      ],
    ]);

    // First test start.
    $this->drupalGet('/bef-test');
    $session = $this->assertSession();
    $session->elementExists('css', '#edit-field-bef-price-value-wrapper--2 .fieldset-wrapper > :nth-child(1).bef-slider');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_price_value' => [
          'plugin_id' => 'bef_sliders',
          'placement_location' => 'middle',
        ],
      ],
    ]);

    // Second test middle.
    $this->drupalGet('/bef-test');
    $session = $this->assertSession();
    $session->elementExists('css', '#edit-field-bef-price-value-wrapper--2 .fieldset-wrapper > :nth-child(2).bef-slider');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_price_value' => [
          'plugin_id' => 'bef_sliders',
          'placement_location' => 'end',
        ],
      ],
    ]);

    // Lastly test end.
    $this->drupalGet('/bef-test');
    $session = $this->assertSession();
    $session->elementExists('css', '#edit-field-bef-price-value-wrapper--2 .fieldset-wrapper > .bef-slider:nth-child(3)');
  }

}
