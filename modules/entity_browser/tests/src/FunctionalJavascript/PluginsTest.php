<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\Core\Entity\Sql\SqlContentEntityStorageException;

/**
 * Tests the entity_browser plugins.
 *
 * @group entity_browser
 */
class PluginsTest extends EntityBrowserWebDriverTestBase {

  /**
   * Tests the Entity browser iframe display plugin.
   */
  public function testIframeDisplayPlugin() {
    $browser = $this->getEntityBrowser('test_entity_browser_file', 'iframe', 'single', 'no_display');

    $image = $this->createFile('lama');

    // Tests view widget on single display.
    $this->drupalGet('node/add/article');
    $this->assertSession()->linkExists('Select entities');
    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_file');

    $this->getSession()->getPage()->checkField('entity_browser_select[file:' . $image->id() . ']');
    $this->getSession()->getPage()->pressButton('Select entities');
    $this->getSession()->switchToIFrame();
    $this->assertSession()->waitForElement('css', '.field--type-entity-reference .button');
    $this->assertSession()->pageTextContains('lama.jpg');

    // Tests upload widget on single display. Gets the upload widget and sets
    // the weight so we can test the view widget.
    $upload_widget = $browser->getWidget('2dc1ab07-2f8f-42c9-aab7-7eef7f8b7d87');
    $upload_widget->setWeight(0);
    $browser->save();

    $this->drupalGet('node/add/article');
    $this->assertSession()->linkExists('Select entities');
    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_file');

    $this->getSession()->getPage()->checkField('entity_browser_select[file:' . $image->id() . ']');
    $this->getSession()->getPage()->pressButton('Select entities');
    $this->getSession()->switchToIFrame();
    $this->assertSession()->waitForElement('css', '.field--type-entity-reference .button');
    $this->assertSession()->pageTextContains('lama.jpg');

    // Tests view tab with tabs widget selector.
    $this->getEntityBrowser('test_entity_browser_file', 'iframe', 'tabs', 'no_display');

    $this->drupalGet('node/add/article');
    $this->assertSession()->linkExists('Select entities');
    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_file');
    $this->assertSession()->linkExists('view');
    $this->assertSession()->linkExists('upload');

    $this->clickLink('view');
    $this->getSession()->getPage()->checkField('entity_browser_select[file:' . $image->id() . ']');
    $this->getSession()->getPage()->pressButton('Select entities');
    $this->getSession()->switchToIFrame();
    $this->assertSession()->waitForElement('css', '.field--type-entity-reference .button');
    $this->assertSession()->pageTextContains('lama.jpg');

    // Tests upload tab with tabs widget selector.
    $this->drupalGet('node/add/article');
    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_file');
    $this->clickLink('upload');
    $this->getSession()->getPage()->attachFileToField('edit-upload-upload', $this->container->get('file_system')->realpath($image->getFileUri()));
    $this->assertSession()->assertWaitOnAjaxRequest();
    $image2 = $this->getLastUploadedFile();
    $this->assertEquals('lama_0.jpg', $image2->label());
    $this->getSession()->getPage()->checkField('upload[file_' . $image2->id() . '][selected]');
    $this->getSession()->getPage()->pressButton('Select files');
    $this->getSession()->switchToIFrame();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('lama_0.jpg');
    // Tests view widget with drop down widget selector.
    $this->getEntityBrowser('test_entity_browser_file', 'iframe', 'drop_down', 'no_display');

    // DropDown widget selector does not work with exposed view filter. This is
    // a known bug and we need to remove exposed filters from the view until
    // that is fixed.
    /** @var \Drupal\views\Entity\View $view */
    $view = $this->container->get('entity_type.manager')->getStorage('view')->load('files_entity_browser');
    $display = &$view->getDisplay('default');
    $display['display_options']['filters'] = [];
    $view->save();

    $this->drupalGet('node/add/article');
    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_file');
    $this->assertSession()->selectExists('edit-widget');
    $this->getSession()->getPage()->checkField('entity_browser_select[file:' . $image->id() . ']');
    $this->getSession()->getPage()->pressButton('Select entities');
    $this->getSession()->switchToIFrame();
    $this->assertSession()->waitForElement('css', '.field--type-entity-reference .button');
    $this->assertSession()->pageTextContains('lama.jpg');

    // Tests upload widget with drop down widget selector.
    $this->drupalGet('node/add/article');

    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_file');
    $this->getSession()->getPage()->selectFieldOption('edit-widget', '2dc1ab07-2f8f-42c9-aab7-7eef7f8b7d87');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->attachFileToField('files[upload][]', $this->container->get('file_system')->realpath($image->getFileUri()));
    $this->assertSession()->assertWaitOnAjaxRequest();
    $image3 = $this->getLastUploadedFile();
    $this->getSession()->getPage()->checkField('upload[file_' . $image3->id() . '][selected]');
    $this->getSession()->getPage()->pressButton('Select files');
    $this->getSession()->switchToIFrame();
    $this->assertSession()->assertWaitOnAjaxRequest();
    // In iframe I get page not found, so this fails.
    $this->assertSession()->pageTextContains($image3->label());
    // Tests view selection display.
    $view_configuration = [
      'view' => 'test_selection_display_view',
      'view_display' => 'entity_browser_1',
    ];
    $browser = $this->getEntityBrowser('test_entity_browser_file', 'iframe', 'single', 'view', [], [], $view_configuration);

    $this->drupalGet('node/add/article');
    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_file');

    // Tests multistep selection display.
    $dragon_image = $this->createFile('dragon');
    $unicorn_image = $this->createFile('unicorn');

    $upload_widget = $browser->getWidget('2dc1ab07-2f8f-42c9-aab7-7eef7f8b7d87');
    $upload_widget->setWeight(-9);
    $browser->save();

    $multistep_configuration = [
      'entity_type' => 'file',
      'display' => 'label',
      'display_settings' => [],
      'select_text' => 'Use selected',
      'selection_hidden' => 0,
    ];

    $browser = $this->getEntityBrowser('test_entity_browser_file', 'iframe', 'tabs', 'multi_step_display', [], [], $multistep_configuration);
    $upload_widget = $browser->getWidget('774798f1-5ec5-4b63-84bd-124cd51ec07d');
    $upload_widget->setWeight(0);
    $browser->save();

    $this->drupalGet('node/add/article');
    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_file');

    $this->getSession()->getPage()->attachFileToField('files[upload][]', $this->container->get('file_system')->realpath($dragon_image->getFileUri()));
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Select files');

    $image4 = $this->getLastUploadedFile();
    $this->assertEquals('dragon_0.jpg', $image4->label());

    $this->assertSession()->pageTextContains('dragon_0.jpg');
    $this->assertSession()->pageTextNotContains('unicorn.jpg');

    $this->getSession()->getPage()->clickLink('view');

    $this->assertSession()->waitForField('entity_browser_select[file:' . $unicorn_image->id() . ']')->check();
    $this->getSession()->getPage()->pressButton('Select entities');
    $this->assertSession()
      ->waitForElement('css', '#edit-selected-items-2-1-remove-button');
    $this->assertSession()
      ->waitForElement('css', '#edit-selected-items-1-0-remove-button');
    $this->getSession()->getPage()->pressButton('Use selected');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame();

    if (!$this->coreVersion('10.2')) {
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    $this->assertSession()->pageTextContains('dragon_0.jpg');
    $this->assertSession()->pageTextContains('unicorn.jpg');
  }

  /**
   * Tests Entity browser modal display plugin.
   */
  public function testModalDisplay() {
    $modal_display_config = [
      'width' => '650',
      'height' => '500',
      'link_text' => 'Select entities',
    ];
    $this->getEntityBrowser('test_entity_browser_file', 'modal', 'single', 'no_display', $modal_display_config);

    $image = $this->createFile('lama');

    $this->drupalGet('node/add/article');
    $this->assertSession()->buttonExists('Select entities');
    $this->getSession()->getPage()->pressButton('Select entities');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_file');

    $this->getSession()->getPage()->checkField('entity_browser_select[file:' . $image->id() . ']');
    $this->getSession()->getPage()->pressButton('Select entities');
    $this->getSession()->switchToIFrame();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->pageTextContains('lama');
  }

  /**
   * Tests Entity browser standalone display plugin.
   */
  public function testStandaloneDisplay() {
    $image = $this->createFile('lama');
    $standalone_configuration = [
      'entity_browser_id' => 'test_entity_browser_file',
      'path' => 'test',
    ];
    $this->getEntityBrowser('test_entity_browser_file', 'standalone', 'single', 'no_display', $standalone_configuration);

    $this->drupalGet('test');

    $this->assertSession()->buttonExists('Select entities');
    $this->getSession()->getPage()->pressButton('Select entities');

    $this->getSession()->getPage()->checkField('entity_browser_select[file:' . $image->id() . ']');
    $this->getSession()->getPage()->pressButton('Select entities');

    // @todo test if entities were selected. Will most likely need a custom event
    // subscriber that displays a message or something along those lines.
  }

  /**
   * Get the most recently uploaded file.
   *
   * @return \Drupal\file\FileInterface
   *   File entity.
   *
   * @throws \Drupal\Core\Entity\Sql\SqlContentEntityStorageException
   *   Thrown if no results from query.
   */
  protected function getLastUploadedFile() {

    $entity_type_manager = \Drupal::service('entity_type.manager');

    $results = $entity_type_manager
      ->getStorage('file')->getQuery()
      ->accessCheck(TRUE)
      ->range(0, 1)
      ->sort('fid', 'DESC')
      ->execute();

    if (!empty($results)) {
      return $entity_type_manager->getStorage('file')->load(reset($results));
    }
    else {
      throw new SqlContentEntityStorageException('File not found');
    }
  }

}
