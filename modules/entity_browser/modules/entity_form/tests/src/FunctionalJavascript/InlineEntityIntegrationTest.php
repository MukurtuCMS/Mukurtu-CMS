<?php

namespace Drupal\Tests\entity_browser_entity_form\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests integration with Inline entity form.
 *
 * @group entity_browser_entity_form
 */
class InlineEntityIntegrationTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_browser_entity_form',
    'entity_browser_test',
    'node',
    'field_ui',
    'entity_browser_entity_form_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Delete unnecessary entity browser.
    $browser = $this->container->get('entity_type.manager')->getStorage('entity_browser')->load('entity_browser_test_entity_form');
    $this->container->get('entity_type.manager')->getStorage('entity_browser')->delete([$browser]);
  }

  /**
   * Tests integration with Inline entity form.
   */
  public function testInlineEntityIntegration() {

    $this->createNode(['type' => 'article', 'title' => 'Daddy Shark']);
    $this->createNode(['type' => 'article', 'title' => 'Mommy Shark']);
    $this->createNode(['type' => 'article', 'title' => 'Baby Shark']);

    $account = $this->drupalCreateUser([
      'administer node form display',
      'administer node display',
      'create article content',
      'access test_entity_browser_iframe_node_view entity browser pages',
    ]);
    $this->drupalLogin($account);
    $this->drupalGet('admin/structure/types/manage/article/form-display');
    $this->assertSession()->buttonExists('Show row weights')->click();

    // Enable field (by default it's in the disabled region).
    $this->assertSession()
      ->selectExists('fields[field_content_reference][region]')
      ->selectOption('content');

    // Switch to using inline_entity_form_complex, so we can test
    // entity browser alterations to field widget settings form.
    $this->assertSession()
      ->selectExists('fields[field_content_reference][type]')
      ->setValue('inline_entity_form_complex');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Open field widget settings form.
    $this->assertSession()->waitForButton('field_content_reference_settings_edit')->press();
    $prefix = 'fields[field_content_reference][settings_edit_form]';
    $this->assertSession()
      ->waitforField($prefix . '[third_party_settings][entity_browser_entity_form][entity_browser_id]')
      ->setValue('test_entity_browser_iframe_node_view');

    $this->assertSession()
      ->fieldExists($prefix . '[settings][allow_existing]')
      ->check();

    $this->submitForm([], 'Save');
    $this->assertSession()->responseContains('Test entity browser iframe with view widget for nodes');

    $this->drupalGet('node/add/article');
    $this->assertSession()->buttonExists('Add existing node')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->responseContains('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->assertSession()->pageTextContains('Daddy Shark');
    $this->assertSession()->pageTextContains('Mommy Shark');
    $this->assertSession()->pageTextContains('Baby Shark');

    $storage = $this->container->get('entity_type.manager')->getStorage('entity_browser');
    $browsers = $storage->loadMultiple();
    $storage->delete($browsers);
    $this->drupalGet('admin/structure/types/manage/article/form-display');
    $this->assertSession()->buttonExists('field_content_reference_settings_edit')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('There are no entity browsers available. You can create one here');

  }

}
