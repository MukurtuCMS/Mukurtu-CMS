<?php

namespace Drupal\Tests\better_exposed_filters\FunctionalJavascript;

use Drupal\views\Views;

/**
 * Tests the auto submit functionality of better exposed filters.
 *
 * @group better_exposed_filters
 */
class BetterExposedFiltersAutoSubmitTest extends BetterExposedFiltersTestBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $view = Views::getView('bef_test');
    $this->setBetterExposedOptions($view, [
      'general' => [
        'autosubmit' => TRUE,
        'autosubmit_exclude_textfield' => TRUE,
      ],
    ]);

    // Create a few test nodes.
    $this->createNode([
      'title' => 'Page One',
      'field_bef_price' => '10',
      'field_bef_letters' => 'a',
      'type' => 'bef_test',
      'created' => strtotime('-2 days'),
    ]);
    $this->createNode([
      'title' => 'Page Two',
      'field_bef_price' => '75',
      'field_bef_letters' => 'b',
      'type' => 'bef_test',
      'created' => strtotime('-3 days'),
    ]);
  }

  /**
   * Tests if filtering via auto-submit works with a selected breakpoint.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testAutoSubmitBreakpoint(): void {
    $view = Views::getView('bef_test');

    // Enable auto-submit, but disable for text fields.
    $this->setBetterExposedOptions($view, [
      'general' => [
        'autosubmit' => TRUE,
        'autosubmit_exclude_textfield' => FALSE,
        'autosubmit_breakpoint' => 'bef_test:bef_test.test',
      ],
    ]);

    $session = $this->getSession();
    // Prepare window.
    $session->resizeWindow(500, 500);

    // Visit the bef-test page.
    $this->drupalGet('bef-test');
    $page = $session->getPage();

    // Ensure that the content we're testing for is present.
    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextContains('Page Two');

    // Enter value in the email field.
    $field_bef_email = $page->find('css', '.form-item-field-bef-email-value input');
    /* Assert exposed operator field does not have attribute to exclude it from
    auto-submit. */
    $field_bef_exposed_operator_email = $page->find('css', '.form-item-field-bef-email-value input');
    $this->assertFalse($field_bef_exposed_operator_email->hasAttribute('data-bef-auto-submit-exclude'));
    $field_bef_email->setValue('1bef');

    // Verify that auto submit didn't run, due to breakpoint.
    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextContains('Page Two');

    // Prepare window.
    $session->resizeWindow(1000, 1000);

    $field_bef_email = $page->find('css', '.form-item-field-bef-email-value input');
    $field_bef_email->setValue('1bef');
    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextNotContains('Page Two');
  }

  /**
   * Tests if filtering via auto-submit works.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAutoSubmitMinLength(): void {
    $view = Views::getView('bef_test');

    // Enable auto-submit, but disable for text fields.
    $this->setBetterExposedOptions($view, [
      'general' => [
        'autosubmit_exclude_textfield' => FALSE,
        'autosubmit_textfield_minimum_length' => 3,
      ],
    ]);

    // Visit the bef-test page.
    $this->drupalGet('bef-test');

    $session = $this->getSession();
    $page = $session->getPage();

    // Ensure that the content we're testing for is present.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);

    // Enter value in email field.
    $field_bef_email = $page->find('css', '.form-item-field-bef-email-value input');
    /* Assert exposed operator field does not have attribute to exclude it from
    auto-submit. */
    $field_bef_exposed_operator_email = $page->find('css', '.form-item-field-bef-email-value input');
    $this->assertFalse($field_bef_exposed_operator_email->hasAttribute('data-bef-auto-submit-exclude'));
    $field_bef_email->setValue('1');
    // Verify that auto submit didn't run, due to less than 4 characters.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);

    $field_bef_email = $page->find('css', '.form-item-field-bef-email-value input');
    $field_bef_email->setValue('1bef');
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);
  }

  /**
   * Tests if filtering via auto-submit works.
   */
  public function testAutoSubmit(): void {
    // Visit the bef-test page.
    $this->drupalGet('bef-test');

    $session = $this->getSession();
    $page = $session->getPage();

    // Ensure that the content we're testing for is present.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);

    // Search for "Page One".
    $field_bef_integer = $page->findField('field_bef_integer_value');
    $field_bef_integer->setValue('1');

    // Verify that only the "Page One" Node is present.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);

    // Enter value in email field.
    $field_bef_email = $page->find('css', '.form-item-field-bef-email-value input');
    $field_bef_email->setValue('qwerty@test.com');

    // Enter value in exposed operator email field.
    $field_bef_exposed_operator_email = $page->find('css', '.form-item-field-bef-email-value input');
    $field_bef_exposed_operator_email->setValue('qwerty@test.com');

    // Verify nothing has changed.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);

    // Submit form.
    $this->submitForm([], 'Apply');

    // Verify no results are visible.
    $html = $page->getHtml();
    $this->assertStringNotContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);
  }

  /**
   * Tests if filtering via auto-submit works if exposed form is a block.
   */
  public function testAutoSubmitWithExposedFormBlock() {
    $this->drupalPlaceBlock('views_exposed_filter_block:bef_test-page_2');

    // Visit the bef-test page.
    $this->drupalGet('bef-test-with-block');

    $session = $this->getSession();
    $page = $session->getPage();

    // Ensure that the content we're testing for is present.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);

    // Search for "Page One".
    $field_bef_integer = $page->findField('field_bef_integer_value');
    $field_bef_integer->setValue('1');
    $field_bef_integer->blur();

    // Verify that only the "Page One" Node is present.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);

    // Enter value in email field.
    $field_bef_email = $page->find('css', '.form-item-field-bef-email-value input');
    $field_bef_email->setValue('qwerty@test.com');

    // Enter value in exposed operator email field.
    $field_bef_exposed_operator_email = $page->find('css', '.form-item-field-bef-email-value input');
    $field_bef_exposed_operator_email->setValue('qwerty@test.com');

    // Verify nothing has changed.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);

    // Submit form.
    $this->submitForm([], 'Apply');

    // Verify no results are visible.
    $html = $page->getHtml();
    $this->assertStringNotContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);
  }

  /**
   * Tests auto submit with checkboxes.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAutoSubmitWithCheckboxes(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_letters_value' => [
          'plugin_id' => 'bef',
        ],
      ],
    ]);

    // Visit the bef-test page.
    $this->drupalGet('/bef-test');

    $session = $this->getSession();
    $page = $session->getPage();

    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextContains('Page Two');

    $page->checkField('edit-field-bef-letters-value-a');
    $page->pressButton('Apply');

    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextNotContains('Page Two');
  }

  /**
   * Tests that auto submit select elements work and gain focus on ajax reload.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testAutoSubmitWithSelect(): void {
    $this->turnAjaxOn();

    // Visit the bef-test page.
    $this->drupalGet('bef-test');

    $session = $this->getSession();
    $page = $session->getPage();

    $field_bef_select = $page->find('css', 'select#edit-term-node-tid-depth');
    $field_bef_select->setValue('15');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->pageTextContains('Page Two');
    $this->assertSession()->pageTextNotContains('Page One');
    $active_selector = $this->getSession()->evaluateScript('document.activeElement.getAttribute("data-drupal-selector")');
    $this->assertEquals('edit-term-node-tid-depth', $active_selector, 'Element with correct data-drupal-selector has focus.');
  }

  /**
   * Tests auto submit sort only.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testAutoSubmitSortOnly(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'general' => [
        'autosubmit' => TRUE,
        'auto_submit_sort_only' => TRUE,
        'autosubmit_exclude_textfield' => FALSE,
        'autosubmit_textfield_minimum_length' => 3,
      ],
    ]);

    // Visit the bef-test page.
    $this->drupalGet('bef-test');

    $session = $this->getSession();
    $page = $session->getPage();

    // Ensure that the content we're testing for is present.
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);

    // This should trigger nothing.
    $field_bef_integer = $page->findField('field_bef_integer_value');
    $field_bef_integer->setValue('1');
    $html = $page->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);
    $field_bef_integer->setValue('All');

    // Change sort.
    $page->selectFieldOption('sort_order', 'ASC');
    $cells = $this->xpath('//table/tbody/tr/td[1]');
    $values = array_map(fn($cell) => $cell->getText(), $cells);

    // Now check the expected order.
    $this->assertEquals('Page Two', $values[0]);
    $this->assertEquals('Page One', $values[1]);
  }

}
