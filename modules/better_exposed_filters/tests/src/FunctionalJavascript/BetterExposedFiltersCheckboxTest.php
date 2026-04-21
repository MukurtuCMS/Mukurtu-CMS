<?php

namespace Drupal\Tests\better_exposed_filters\FunctionalJavascript;

use Drupal\views\Views;

/**
 * Tests functionality around checkboxes.
 *
 * @group better_exposed_filters
 */
class BetterExposedFiltersCheckboxTest extends BetterExposedFiltersTestBase {

  /**
   * Tests the single checkbox.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSingleCheckbox(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'status' => [
          'plugin_id' => 'bef_single',
        ],
        'field_bef_letters_value' => [
          'plugin_id' => 'bef',
        ],
      ],
    ]);
    $session = $this->assertSession();

    $this->drupalGet('/bef-test');
    $session->checkboxChecked('status');
    $session->pageTextContains('Page one');
    $session->pageTextNotContains('Page unpublished');

    $page = $this->getSession()->getPage();
    $page->findField('status')->uncheck();
    $page->pressButton('Apply');

    $session->checkboxNotChecked('status');
    // Both should display because treat_as_false is unchecked.
    $session->pageTextContains('Page one');
    $session->pageTextContains('Page unpublished');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'status' => [
          'plugin_id' => 'bef_single',
          'treat_as_false' => TRUE,
        ],
      ],
    ]);

    // Now test the same again.
    $this->drupalGet('/bef-test');
    $session->checkboxChecked('status');
    $session->pageTextContains('Page one');
    $session->pageTextNotContains('Page unpublished');

    $page = $this->getSession()->getPage();
    $page->findField('status')->uncheck();
    $page->pressButton('Apply');

    $session->checkboxNotChecked('status');
    // Now only the unpublished should appear.
    $session->pageTextNotContains('Page one');
    $session->pageTextContains('Page unpublished');
  }

  /**
   * Tests the soft limit feature.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBefCheckboxSoftLimit(): void {
    $view = Views::getView('bef_test');
    $session = $this->assertSession();

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_letters_value' => [
          'plugin_id' => 'bef',
          'soft_limit' => '3',
          'soft_limit_label_less' => 'Less test',
          'soft_limit_label_more' => 'More test',
        ],
      ],
    ]);

    $this->drupalGet('/bef-test');
    $session->elementTextEquals('css', '.bef-soft-limit-link', 'More test');
    $session->pageTextContains('Aardvark');
    $session->pageTextContains('Bumble & the Bee');
    $session->pageTextContains('Le Chimpanzé');
    $session->pageTextNotContains('Donkey');
    $session->pageTextNotContains('Elephant');
    $this->clickLink('More test');
    $session->pageTextContains('Aardvark');
    $session->pageTextContains('Bumble & the Bee');
    $session->pageTextContains('Le Chimpanzé');
    $session->pageTextContains('Donkey');
    $session->pageTextContains('Elephant');
    $session->elementTextEquals('css', '.bef-soft-limit-link', 'Less test');

    // Now lets test soft limit on links.
    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_letters_value' => [
          'plugin_id' => 'bef_links',
          'soft_limit' => '3',
          'soft_limit_label_less' => 'Less test',
          'soft_limit_label_more' => 'More test',
        ],
      ],
    ]);

    $this->drupalGet('/bef-test');
    $session->elementTextEquals('css', '.bef-soft-limit-link', 'More test');
    $session->pageTextContains('Aardvark');
    $session->pageTextContains('Bumble & the Bee');
    $session->pageTextContains('Le Chimpanzé');
    $session->pageTextNotContains('Donkey');
    $session->pageTextNotContains('Elephant');
    $this->clickLink('More test');
    $session->pageTextContains('Aardvark');
    $session->pageTextContains('Bumble & the Bee');
    $session->pageTextContains('Le Chimpanzé');
    $session->pageTextContains('Donkey');
    $session->pageTextContains('Elephant');
    $session->elementTextEquals('css', '.bef-soft-limit-link', 'Less test');
  }

}
