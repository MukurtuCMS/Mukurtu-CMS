<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;

/**
 * Tests entity browser within entity embed.
 *
 * @group entity_browser
 *
 * @package Drupal\Tests\entity_browser\FunctionalJavascript
 */
class EntityEmbedTest extends WebDriverTestBase {

  use CKEditor5TestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_browser',
    'entity_browser_test',
    'embed',
    'entity_embed',
    'entity_browser_entity_embed_test',
  ];

  /**
   * The test administrative user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'use text format full_html',
      'create test_entity_embed content',
      'access widget_context_default_value entity browser pages',
      'access bundle_filter entity browser pages',
    ]);
  }

  /**
   * Tests the EntityBrowserWidgetContext default argument plugin.
   */
  public function testEntityBrowserWidgetContext() {

    $this->drupalLogin($this->adminUser);

    $this->createNode(['type' => 'shark', 'title' => 'Luke']);
    $this->createNode(['type' => 'jet', 'title' => 'Leia']);
    $this->createNode(['type' => 'article', 'title' => 'Darth']);

    $this->drupalGet('/node/add/test_entity_embed');
    $this->waitForEditor();
    $this->pressEditorButton('Jet Shark Embed');
    $this->assertSession()->waitForId('views-exposed-form-widget-context-default-value-entity-browser-1');

    $this->getSession()->switchToIFrame('entity_browser_iframe_widget_context_default_value');

    // Check that only nodes of an allowed type are listed.
    $this->assertSession()->responseContains('Luke');
    $this->assertSession()->responseContains('Leia');
    $this->assertSession()->responseNotContains('Darth');

    // Change the allowed bundles on the entity embed.
    $embed_button = $this->container->get('entity_type.manager')
      ->getStorage('embed_button')
      ->load('jet_shark_embed');
    $type_settings = $embed_button->getTypeSettings();
    $type_settings['bundles'] = [
      'article' => 'article',
    ];
    $embed_button->set('type_settings', $type_settings);
    $embed_button->save();

    // Test the new bundle settings are affecting what is visible in the view.
    $this->drupalGet('/node/add/test_entity_embed');
    $this->waitForEditor();
    $this->pressEditorButton('Jet Shark Embed');
    $this->assertSession()->waitForId('views-exposed-form-widget-context-default-value-entity-browser-1');

    $this->getSession()->switchToIFrame('entity_browser_iframe_widget_context_default_value');

    // Check that only nodes of an allowed type are listed.
    $this->assertSession()->responseNotContains('Luke');
    $this->assertSession()->responseNotContains('Leia');
    $this->assertSession()->responseContains('Darth');

  }

  /**
   * Tests the ContextualBundle filter plugin.
   */
  public function testContextualBundle() {

    $this->drupalLogin($this->adminUser);

    $this->createNode(['type' => 'shark', 'title' => 'Luke']);
    $this->createNode(['type' => 'jet', 'title' => 'Leia']);
    $this->createNode(['type' => 'article', 'title' => 'Darth']);

    $this->drupalGet('/node/add/test_entity_embed');
    $this->waitForEditor();
    $this->pressEditorButton('Bundle Filter Test Embed');
    $this->assertSession()->waitForId('views-exposed-form-bundle-filter-entity-browser-1');

    $this->getSession()->switchToIFrame('entity_browser_iframe_bundle_filter');

    // Check that only nodes of an allowed type are listed.
    $this->assertSession()->responseContains('Luke');
    $this->assertSession()->responseContains('Leia');
    $this->assertSession()->responseNotContains('Darth');

    // Change the allowed bundles on the entity embed.
    $embed_button = $this->container->get('entity_type.manager')
      ->getStorage('embed_button')
      ->load('bundle_filter_test');
    $type_settings = $embed_button->getTypeSettings();
    $type_settings['bundles'] = [
      'article' => 'article',
    ];
    $embed_button->set('type_settings', $type_settings);
    $embed_button->save();

    // Test the new bundle settings are affecting what is visible in the view.
    $this->drupalGet('/node/add/test_entity_embed');
    $this->waitForEditor();
    $this->pressEditorButton('Bundle Filter Test Embed');
    $this->assertSession()->waitForId('views-exposed-form-bundle-filter-entity-browser-1');

    $this->getSession()->switchToIFrame('entity_browser_iframe_bundle_filter');

    // Check that only nodes of an allowed type are listed.
    $this->assertSession()->responseNotContains('Luke');
    $this->assertSession()->responseNotContains('Leia');
    $this->assertSession()->responseContains('Darth');

  }

  /**
   * Tests the ContextualBundle filter plugin with exposed option.
   */
  public function testContextualBundleExposed() {

    $this->config('entity_browser.browser.bundle_filter')
      ->set('widgets.b882a89d-9ce4-4dfe-9802-62df93af232a.settings.view', 'bundle_filter_exposed')
      ->save();

    $this->drupalLogin($this->adminUser);

    $this->createNode(['type' => 'shark', 'title' => 'Luke']);
    $this->createNode(['type' => 'jet', 'title' => 'Leia']);
    $this->createNode(['type' => 'article', 'title' => 'Darth']);

    $this->drupalGet('/node/add/test_entity_embed');
    $this->waitForEditor();
    $this->pressEditorButton('Bundle Filter Test Embed');
    $this->assertSession()->waitForId('views-exposed-form-bundle-filter-entity-browser-1');

    $this->getSession()->switchToIFrame('entity_browser_iframe_bundle_filter');

    // Check that only nodes of an allowed type are listed.
    $this->assertSession()->responseContains('Luke');
    $this->assertSession()->responseContains('Leia');
    $this->assertSession()->responseNotContains('Darth');

    // Test exposed form type filter.
    $this->assertSession()->selectExists('Type')->selectOption('jet');
    $this->assertSession()->buttonExists('Apply')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Check that only nodes of the type selected in the exposed filter display.
    $this->assertSession()->pageTextNotContains('Luke');
    $this->assertSession()->pageTextContains('Leia');
    $this->assertSession()->pageTextNotContains('Darth');

    $this->assertSession()->selectExists('Type')->selectOption('shark');
    $this->assertSession()->buttonExists('Apply')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Check that only nodes of the type selected in the exposed filter display.
    $this->assertSession()->pageTextContains('Luke');
    $this->assertSession()->pageTextNotContains('Leia');
    $this->assertSession()->pageTextNotContains('Darth');

    $this->assertSession()->selectExists('Type')->selectOption('All');
    $this->assertSession()->buttonExists('Apply')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Check that only nodes of the type selected in the exposed filter display.
    $this->assertSession()->pageTextContains('Luke');
    $this->assertSession()->pageTextContains('Leia');
    $this->assertSession()->pageTextNotContains('Darth');

    // Change the allowed bundles on the entity embed.
    $embed_button = $this->container->get('entity_type.manager')
      ->getStorage('embed_button')
      ->load('bundle_filter_test');
    $type_settings = $embed_button->getTypeSettings();
    $type_settings['bundles'] = [
      'article' => 'article',
    ];
    $embed_button->set('type_settings', $type_settings);
    $embed_button->save();

    // Test the new bundle settings are affecting what is visible in the view.
    $this->drupalGet('/node/add/test_entity_embed');
    $this->waitForEditor();
    $this->pressEditorButton('Bundle Filter Test Embed');
    $this->assertSession()->waitForId('views-exposed-form-bundle-filter-entity-browser-1');

    $this->getSession()->switchToIFrame('entity_browser_iframe_bundle_filter');

    // Check that only nodes of an allowed type are listed.
    $this->assertSession()->responseNotContains('Luke');
    $this->assertSession()->responseNotContains('Leia');
    $this->assertSession()->responseContains('Darth');

    // If there is just one target_bundle, the contextual filter
    // should not be visible.
    $this->assertSession()->fieldNotExists('Type');

  }

}
