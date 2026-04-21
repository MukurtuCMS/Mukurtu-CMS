<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\entity_browser\Entity\EntityBrowser;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the config UI for adding and editing entity browsers.
 *
 * @group entity_browser
 *
 * @package Drupal\Tests\entity_browser\FunctionalJavascript
 */
class ConfigurationTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_browser',
    'entity_browser_entity_form',
    'entity_browser_test_configuration',
    'block',
    'node',
    'taxonomy',
    'views',
    'token',
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
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');

    $this->drupalCreateContentType(['type' => 'foo', 'name' => 'Foo']);

    $this->adminUser = $this->drupalCreateUser([
      'administer entity browsers',
    ]);

  }

  /**
   * Tests EntityBrowserEditForm.
   */
  public function testEntityBrowserEditForm() {

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/content/entity_browser');
    $this->assertSession()->responseNotContains('Access denied. You must log in to view this page.');
    $this->assertSession()->responseContains('There are no entity browser entities yet.');

    $this->clickLink('Add Entity browser');
    $this->assertSession()->fieldExists('label')->setValue('Test entity browser');
    $this->getSession()->executeScript("jQuery('.visually-hidden, .hidden').removeClass('visually-hidden hidden');");
    $this->assertSession()->fieldExists('name')->setValue('test_entity_browser');
    $this->assertSession()->selectExists('display')->selectOption('modal');
    // Make sure fields in details elements are visible.
    $this->getSession()->executeScript("jQuery('details').attr('open', 'open');");
    $this->assertSession()->fieldExists('display_configuration[width]')->setValue('700');
    $this->assertSession()->fieldExists('display_configuration[height]')->setValue('300');
    $this->assertSession()->fieldExists('display_configuration[link_text]')->setValue('Select some entities');
    $this->assertSession()->selectExists('widget_selector')->selectOption('tabs');
    $this->assertSession()->selectExists('selection_display')->selectOption('no_display');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('The entity browser Test entity browser has been added. Now you may configure the widgets you would like to use.');

    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/widgets');
    $this->assertSession()->selectExists('widget');

    $this->clickLink('General Settings');
    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/edit');

    $entity_browser = EntityBrowser::load('test_entity_browser');

    $this->assertEquals('modal', $entity_browser->display);
    $this->assertEquals('tabs', $entity_browser->widget_selector);
    $this->assertEquals('no_display', $entity_browser->selection_display);

    $display_configuration = $entity_browser->getDisplay()->getConfiguration();

    $this->assertEquals('700', $display_configuration['width']);
    $this->assertEquals('300', $display_configuration['height']);
    $this->assertEquals('Select some entities', $display_configuration['link_text']);

    $this->assertSession()->fieldValueEquals('display_configuration[width]', '700');
    $this->assertSession()->fieldValueEquals('display_configuration[height]', '300');
    $this->assertSession()->fieldValueEquals('display_configuration[link_text]', 'Select some entities');

    $this->assertSession()->selectExists('display')->selectOption('iframe');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists('display_configuration[auto_open]')->check();
    $this->assertSession()->fieldExists('display_configuration[width]')->setValue('100');
    $this->assertSession()->fieldExists('display_configuration[height]')->setValue('100');
    $this->assertSession()->fieldExists('display_configuration[link_text]')->setValue('All animals are created equal');
    $this->submitForm([], 'Save');
    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/edit');
    $this->assertSession()->pageTextContains('The entity browser Test entity browser has been updated.');

    $entity_browser = EntityBrowser::load('test_entity_browser');

    $this->assertEquals('iframe', $entity_browser->display);
    $this->assertEquals('tabs', $entity_browser->widget_selector);
    $this->assertEquals('no_display', $entity_browser->selection_display);

    $display_configuration = $entity_browser->getDisplay()->getConfiguration();

    $this->assertEquals('100', $display_configuration['width']);
    $this->assertEquals('100', $display_configuration['height']);
    $this->assertEquals('All animals are created equal', $display_configuration['link_text']);
    $this->assertEquals(TRUE, $display_configuration['auto_open']);
    $this->assertSession()->fieldExists('display_configuration[width]');
    $this->assertSession()->fieldValueEquals('display_configuration[width]', '100');
    $this->assertSession()->fieldValueEquals('display_configuration[height]', '100');
    $this->assertSession()->fieldValueEquals('display_configuration[link_text]', 'All animals are created equal');
    $this->assertSession()->checkboxChecked('display_configuration[auto_open]');

    $this->assertSession()->selectExists('display')->selectOption('standalone');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists('display_configuration[path]')->setValue('/all-animals');
    $this->submitForm([], 'Save');
    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/edit');
    $this->assertSession()->pageTextContains('The entity browser Test entity browser has been updated.');
    $this->clickLink('General Settings');
    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/edit');
    $this->getSession()->executeScript("jQuery('details').attr('open', 'open');");

    $entity_browser = EntityBrowser::load('test_entity_browser');

    $this->assertEquals('standalone', $entity_browser->display);

    $display_configuration = $entity_browser->getDisplay()->getConfiguration();

    $this->assertEquals('/all-animals', $display_configuration['path']);
    $this->assertSession()->fieldValueEquals('display_configuration[path]', '/all-animals');

    // Test validation of leading forward slash.
    $this->assertSession()->fieldExists('display_configuration[path]')->setValue('no-forward-slash');
    $this->submitForm([], 'Save');
    $this->assertSession()->responseContains('The Path field must begin with a forward slash.');
    $this->assertSession()->fieldExists('display_configuration[path]')->setValue('/all-animals');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('The entity browser Test entity browser has been updated.');
    $this->getSession()->executeScript("jQuery('details').attr('open', 'open');");

    // Test ajax update of display settings.
    $this->assertSession()->selectExists('display')->selectOption('iframe');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists('display_configuration[width]');
    $this->assertSession()->responseContains('Width of the iFrame');

    $this->assertSession()->selectExists('display')->selectOption('standalone');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists('display_configuration[path]');
    $this->assertSession()->responseContains('The path at which the browser will be accessible.');

    $this->assertSession()->selectExists('display')->selectOption('modal');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists('display_configuration[width]');
    $this->assertSession()->responseContains('Width of the modal');

    // Test ajax update of Selection display plugin settings.
    $this->assertSession()->selectExists('selection_display')->selectOption('multi_step_display');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists('selection_display_configuration[select_text]');
    $this->assertSession()->fieldExists('selection_display_configuration[selection_hidden]');
    $this->assertSession()->selectExists('selection_display_configuration[entity_type]');
    $this->assertSession()->selectExists('selection_display_configuration[display]')->selectOption('rendered_entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists('selection_display_configuration[display_settings][view_mode]');
    $this->assertSession()->responseContains('Select view mode to be used when rendering entities.');

    // Test ajax update of Multi step selection display "Entity display plugin".
    $this->assertSession()->selectExists('selection_display_configuration[display]')->selectOption('label');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldNotExists('selection_display_configuration[display_settings][view_mode]');
    $this->assertSession()->selectExists('selection_display_configuration[display]')->selectOption('rendered_entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists('selection_display_configuration[display_settings][view_mode]');
    $this->assertSession()->responseContains('Select view mode to be used when rendering entities.');

    // Test ajax update of Multi step selection display "Entity type".
    $entity_type = $this->assertSession()->selectExists('selection_display_configuration[entity_type]')->selectOption('taxonomy_term');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->optionExists('selection_display_configuration[display_settings][view_mode]', 'default');
    $this->assertSession()->optionExists('selection_display_configuration[display_settings][view_mode]', 'full');

    // Test view selection display.
    $this->assertSession()->selectExists('selection_display')->selectOption('view');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->optionExists('selection_display_configuration[view]', 'content.default');
    $this->assertSession()->responseContains('View display to use for displaying currently selected items.');

    $this->assertSession()->selectExists('selection_display')->selectOption('no_display');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementContains('css', 'details[data-drupal-selector="edit-selection-display-configuration"]', 'This plugin has no configuration options');

  }

  /**
   * Tests WidgetsConfig form.
   */
  public function testWidgetsConfig() {

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/content/entity_browser');
    $this->clickLink('Add Entity browser');
    $this->assertSession()->fieldExists('label')->setValue('Test entity browser');
    $this->getSession()->executeScript("jQuery('.visually-hidden, .hidden').removeClass('visually-hidden hidden');");
    $this->assertSession()->fieldExists('name')->setValue('test_entity_browser');
    // Use defaults and save to go to WidgetsConfig form.
    $this->submitForm([], 'Save');
    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/widgets');
    $this->assertSession()->pageTextContains('The entity browser Test entity browser has been added. Now you may configure the widgets you would like to use.');
    $widgetSelect = $this->assertSession()->selectExists('widget');

    $this->assertSession()->responseContains('The available plugins are:');
    $this->assertSession()->responseContains("<strong>Upload:</strong> Adds an upload field browser's widget.");
    $this->assertSession()->responseContains("<strong>View:</strong> Uses a view to provide entity listing in a browser's widget.");
    $this->assertSession()->responseContains("<strong>Entity form:</strong> Provides entity form widget.");

    // Test adding and removing entity form widget.
    $widgetSelect->selectOption('entity_form');
    $selector = $this->assertSession()
      ->waitforElementVisible('css', 'tr.draggable')
      ->getAttribute('data-drupal-selector');
    $uuid = str_replace('edit-table-', '', $selector);
    $this->assertSession()->fieldExists("table[$uuid][label]");
    $this->assertSession()->fieldExists("table[$uuid][form][submit_text]");
    $this->assertSession()->selectExists("table[$uuid][form][entity_type]")->selectOption('node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->selectExists("table[$uuid][form][bundle][select]")->selectOption('foo');
    $this->assertSession()->selectExists("table[$uuid][form][form_mode][form_select]");
    $this->submitForm([], 'Save');
    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/widgets');
    $this->assertSession()->pageTextContains('The entity browser Test entity browser has been updated.');

    $entity_browser = EntityBrowser::load('test_entity_browser');
    $widget = $entity_browser->getWidget($uuid);
    $widgetSettings = $widget->getConfiguration()['settings'];

    $this->assertEquals([
      'submit_text' => 'Save entity',
      'entity_type' => 'node',
      'bundle' => 'foo',
      'form_mode' => 'default',
    ], $widgetSettings, 'Entity browser widget configuration was correctly saved.');

    $this->assertSession()->buttonExists("edit-table-$uuid-remove")->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementNotExists('css', 'tr[data-drupal-selector="edit-table-' . $uuid . '"]');
    // There should be no widgets now.
    $this->assertSession()->elementNotExists('css', 'tr.draggable');

    // Test adding and removing view widget.
    $widgetSelect->selectOption('view');
    $selector = $this->assertSession()
      ->waitforElementVisible('css', 'tr.draggable')
      ->getAttribute('data-drupal-selector');
    $uuid = str_replace('edit-table-', '', $selector);
    $this->assertSession()->fieldExists("table[$uuid][label]");
    $this->assertSession()->fieldExists("table[$uuid][form][submit_text]");
    $this->assertSession()->fieldExists("table[$uuid][form][auto_select]")->check();
    $this->assertSession()->selectExists("table[$uuid][form][view]")->selectOption('nodes_entity_browser.entity_browser_1');
    $this->submitForm([], 'Save');
    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/widgets');
    $this->assertSession()->pageTextContains('The entity browser Test entity browser has been updated.');

    $entity_browser = EntityBrowser::load('test_entity_browser');
    $widget = $entity_browser->getWidget($uuid);
    $widgetSettings = $widget->getConfiguration()['settings'];

    $this->assertEquals([
      'view' => 'nodes_entity_browser',
      'view_display' => 'entity_browser_1',
      'submit_text' => 'Select entities',
      'auto_select' => TRUE,
    ], $widgetSettings, 'Entity browser widget configuration was correctly saved.');

    $this->assertSession()->buttonExists("edit-table-$uuid-remove")->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementNotExists('css', 'tr[data-drupal-selector="edit-table-' . $uuid . '"]');
    // There should be no widgets now.
    $this->assertSession()->elementNotExists('css', 'tr.draggable');

    // Test adding and removing upload widget.
    $widgetSelect->selectOption('upload');
    $selector = $this->assertSession()
      ->waitforElementVisible('css', 'tr.draggable')
      ->getAttribute('data-drupal-selector');
    $uuid = str_replace('edit-table-', '', $selector);
    $this->assertSession()->fieldExists("table[$uuid][label]");
    $this->assertSession()->fieldExists("table[$uuid][form][submit_text]");
    $this->assertSession()->fieldExists("table[$uuid][form][upload_location]");
    $this->assertSession()->fieldExists("table[$uuid][form][multiple]");
    $this->assertSession()->fieldExists("table[$uuid][form][upload_location]");
    $this->assertSession()->elementExists('css', 'a.token-dialog.use-ajax');
    $this->submitForm([], 'Save');
    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/widgets');
    $this->assertSession()->pageTextContains('The entity browser Test entity browser has been updated.');

    $entity_browser = EntityBrowser::load('test_entity_browser');
    $widget = $entity_browser->getWidget($uuid);
    $widgetSettings = $widget->getConfiguration()['settings'];

    $this->assertEquals([
      'upload_location' => 'public://',
      'multiple' => TRUE,
      'submit_text' => 'Select files',
      'extensions' => 'jpg jpeg gif png txt doc xls pdf ppt pps odt ods odp',
    ], $widgetSettings, 'Entity browser widget configuration was correctly saved.');

    $this->assertSession()->buttonExists("edit-table-$uuid-remove")->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementNotExists('css', 'tr[data-drupal-selector="edit-table-' . $uuid . '"]');
    // There should be no widgets now.
    $this->assertSession()->elementNotExists('css', 'tr.draggable');

    // Go back to listing page.
    $this->drupalGet('/admin/config/content/entity_browser');
    $this->assertSession()->responseContains('admin/config/content/entity_browser/test_entity_browser/edit');

    // Test that removing widget without saving doesn't remove it permanently.
    $entity_browser = EntityBrowser::load('test_entity_browser');
    $widget = $entity_browser->getWidget($uuid);
    $this->assertNotEmpty($widget);

  }

}
