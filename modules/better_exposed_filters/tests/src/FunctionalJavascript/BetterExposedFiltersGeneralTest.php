<?php

namespace Drupal\Tests\better_exposed_filters\FunctionalJavascript;

use Drupal\views\Views;

/**
 * Tests the basic AJAX functionality of BEF exposed forms.
 *
 * @group better_exposed_filters
 */
class BetterExposedFiltersGeneralTest extends BetterExposedFiltersTestBase {

  /**
   * Test label hidden setting.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testLabelHidden(): void {
    $view = Views::getView('bef_test');
    $view->storage->getDisplay('default')['display_options']['filters']['field_bef_price_value']['operator'] = 'between';
    $view->save();
    $session = $this->assertSession();

    $this->drupalGet('/bef-test');
    $session->elementAttributeNotContains('css', '#edit-field-bef-price-value-wrapper--2 legend span', 'class', 'visually-hidden');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_price_value' => [
          'plugin_id' => 'bef_sliders',
          'advanced' => [
            'hide_label' => TRUE,
          ],
        ],
      ],
    ]);

    $this->drupalGet('/bef-test');
    $session->elementAttributeContains('css', '#edit-field-bef-price-value-wrapper--2 legend span', 'class', 'visually-hidden');
  }

  /**
   * Tests when remember last selection is used.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRememberLastSelection(): void {
    $this->turnAjaxOn();
    $this->drupalLogin($this->createUser());
    $this->drupalGet('bef-test');
    $this->getSession()->getPage()->fillField('field_bef_email_value', 'bef-test2@drupal.org');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldValueEquals('field_bef_email_value', 'bef-test2@drupal.org');

    // Now go back and verify email was remembered.
    $this->drupalGet('bef-test');
    $this->assertSession()->fieldValueEquals('field_bef_email_value', 'bef-test2@drupal.org');

    // Click Reset button.
    $this->getSession()->getPage()->pressButton('Reset');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify field cleared.
    $this->assertSession()->fieldValueEquals('field_bef_email_value', '');
  }

  /**
   * Tests when remember last selection for checkboxes.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRememberLastSelectionCheckboxes(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_letters_value' => [
          'plugin_id' => 'bef',
        ],
      ],
    ]);

    $session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->createUser());
    $this->drupalGet('/bef-test');
    $page->findField('field_bef_letters_value[a]')->check();

    $this->getSession()->getPage()->pressButton('Apply');
    $session->checkboxChecked('field_bef_letters_value[a]');
    $session->checkboxNotChecked('field_bef_letters_value[b]');

    // Now reload the page without filters.
    $this->drupalGet('/bef-test');
    $session->checkboxChecked('field_bef_letters_value[a]');
    $session->checkboxNotChecked('field_bef_letters_value[b]');

    // Click Reset button.
    $this->getSession()->getPage()->pressButton('Reset');

    // Verify field cleared.
    $session->checkboxNotChecked('field_bef_letters_value[a]');
    $session->checkboxNotChecked('field_bef_letters_value[b]');
  }

  /**
   * Test filter classes setting.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFieldClasses(): void {
    $view = Views::getView('bef_test');
    $session = $this->assertSession();

    $this->drupalGet('/bef-test');
    $session->elementAttributeNotContains('css', 'input#edit-field-bef-email-value', 'class', 'bef-test-field-class');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_email_value' => [
          'advanced' => [
            'field_classes' => 'bef-test-field-class',
          ],
        ],
      ],
    ]);

    $this->drupalGet('bef-test');
    $session->elementAttributeContains('css', 'input#edit-field-bef-email-value', 'class', 'bef-test-field-class');
  }

  /**
   * Tests grouping with a secondary exposed option.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSecondaryCollapsibleOptions(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'general' => [
        'allow_secondary' => TRUE,
      ],
      'filter' => [
        'field_bef_letters_value' => [
          'plugin_id' => 'bef',
          'advanced' => [
            'collapsible' => TRUE,
            'is_secondary' => TRUE,
          ],
        ],
      ],
    ]);
    $page = $this->getSession()->getPage();

    $this->drupalGet('/bef-test');
    $this->click('details#edit-secondary');
    $this->click('details#edit-field-bef-letters-value-collapsible');
    $page->findField('field_bef_letters_value[a]')->check();
    $page->pressButton('Apply');

    // Verify fieldsets are open.
    $session = $this->assertSession();
    $session->elementAttributeContains('css', 'details.bef--secondary', 'open', 'open');
    $session->elementAttributeContains('css', 'details.bef--secondary details.form-item', 'open', 'open');
  }

  /**
   * Tests placing exposed filters inside a collapsible field-set.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSecondaryOptions(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'general' => [
        'allow_secondary' => TRUE,
        'secondary_label' => 'Secondary Options TEST',
        'autosubmit' => FALSE,
      ],
      'sort' => [
        'plugin_id' => 'default',
        'advanced' => [
          'is_secondary' => TRUE,
        ],
      ],
      'pager' => [
        'plugin_id' => 'default',
        'advanced' => [
          'is_secondary' => TRUE,
        ],
      ],
      'filter' => [
        'field_bef_boolean_value' => [
          'plugin_id' => 'default',
          'advanced' => [
            'is_secondary' => TRUE,
          ],
        ],
        'field_bef_integer_value' => [
          'plugin_id' => 'default',
          'advanced' => [
            'is_secondary' => TRUE,
            'collapsible' => TRUE,
          ],
        ],
      ],
    ]);

    // Visit the bef-test page.
    $this->drupalGet('bef-test');

    $session = $this->getSession();
    $page = $session->getPage();

    // Assert our fields are initially hidden inside the collapsible field-set.
    $secondary_options = $page->find('css', '.bef--secondary');
    $this->assertFalse($secondary_options->hasAttribute('open'));
    $secondary_options->hasField('field_bef_boolean_value');
    $this->assertTrue($secondary_options->hasField('field_bef_integer_value'), 'Integer field should be present in secondary options');

    // Submit form and set a value for the boolean field.
    $secondary_options->click();
    $this->submitForm(['field_bef_boolean_value' => 1], 'Apply');
    $session = $this->getSession();
    $page = $session->getPage();

    // Verify our field-set is open and our fields visible.
    $secondary_options = $page->find('css', '.bef--secondary');
    $this->assertTrue($secondary_options->hasAttribute('open'));
  }

  /**
   * Tests when filter is marked to be collapsed.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFilterCollapsible() {
    $view = Views::getView('bef_test');
    $session = $this->getSession();
    $page = $session->getPage();

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_email_value' => [
          'plugin_id' => 'default',
          'advanced' => [
            'collapsible' => TRUE,
            'collapsible_disable_automatic_open' => TRUE,
          ],
        ],
      ],
    ]);

    // Visit the bef-test page.
    $this->drupalGet('bef-test');

    // Assert the field is closed by default.
    $details_summary = $page->find('css', '#edit-field-bef-email-value-collapsible summary');
    $this->assertTrue($details_summary->hasAttribute('aria-expanded'));
    $this->assertEquals('false', $details_summary->getAttribute('aria-expanded'));

    // Verify field_bef_email is 2nd in the filter.
    $email_details = $page->find('css', '.views-exposed-form .form-item:nth-child(3)');
    $this->assertEquals('edit-field-bef-email-value-collapsible', $email_details->getAttribute('id'));

    // Assert the field is closed by default.
    $details_summary = $page->find('css', '#edit-field-bef-email-value-collapsible summary');
    $this->assertTrue($details_summary->hasAttribute('aria-expanded'));
    $this->assertEquals('false', $details_summary->getAttribute('aria-expanded'));
  }

  /**
   * Tests when filter is marked to be collapsed but open by default.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFilterCollapsibleOpenByDefault() {
    $view = Views::getView('bef_test');
    $session = $this->getSession();
    $page = $session->getPage();

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_email_value' => [
          'plugin_id' => 'default',
          'advanced' => [
            'collapsible' => TRUE,
            'open_by_default' => TRUE,
          ],
        ],
      ],
    ]);

    // Visit the bef-test page.
    $this->drupalGet('bef-test');

    // Assert the field is opened by default.
    $details_summary = $page->find('css', '#edit-field-bef-email-value-collapsible summary');
    $this->assertTrue($details_summary->hasAttribute('aria-expanded'));
    $this->assertEquals('true', $details_summary->getAttribute('aria-expanded'));
  }

  /**
   * Tests replacement setting.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testReplacementSetting(): void {
    $view = Views::getView('bef_test');

    // Test with filter_rewrite_values_key enabled first.
    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_integer_value' => [
          'plugin_id' => 'default',
          'advanced' => [
            'rewrite' => [
              'filter_rewrite_values' => "1|One replace\r\n2|",
              'filter_rewrite_values_key' => TRUE,
            ],
          ],
        ],
      ],
    ]);

    $this->drupalGet('bef-test');

    $this->assertSession()->optionExists('field_bef_integer_value', 'One replace');
    // Checking for empty value because optionNotExists() doesn't check key.
    $this->assertSession()->optionNotExists('field_bef_integer_value', '');
    $this->assertSession()->optionExists('field_bef_integer_value', 'Three');

    // Test with filter_rewrite_values_key disabled next.
    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_integer_value' => [
          'plugin_id' => 'default',
          'advanced' => [
            'rewrite' => [
              'filter_rewrite_values' => "One|One replace\r\nTwo|",
              'filter_rewrite_values_key' => FALSE,
            ],
          ],
        ],
      ],
    ]);

    $this->drupalGet('bef-test');

    $this->assertSession()->optionExists('field_bef_integer_value', 'One replace');
    // Checking for empty value because optionNotExists() doesn't check key.
    $this->assertSession()->optionNotExists('field_bef_integer_value', '');
    $this->assertSession()->optionExists('field_bef_integer_value', 'Three');
  }

  /**
   * Tests that a 404 page.
   *
   * With an exposed bef_links filter does not cause an error.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testExposedFormOn404Page(): void {
    // Load a random URL to trigger a 404.
    $this->drupalGet('page-not-found/456737');
    // Check for error.
    $this->assertSession()->pageTextNotContains('Symfony\Component\Routing\Exception\ResourceNotFoundException');

    $config_factory = $this->container->get('config.factory');
    // Set the test node as the 404 page.
    $config_factory->getEditable('system.site')
      ->set('page.404', '/bef-test')
      ->save();

    $this->drupalGet('page-not-found/456737');

    // Check random element on page.
    $this->assertSession()->pageTextContains('Page one');
    $this->assertSession()->pageTextContains('Page two');
  }

}
