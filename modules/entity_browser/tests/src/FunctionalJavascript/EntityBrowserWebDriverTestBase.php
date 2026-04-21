<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\file\Entity\File;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Behat\Mink\Element\NodeElement;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Base class for Entity browser Javascript functional tests.
 *
 * @package Drupal\Tests\entity_browser\FunctionalJavascript
 */
abstract class EntityBrowserWebDriverTestBase extends WebDriverTestBase {

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
    'entity_browser_test',
    'contextual',
    'views',
    'block',
    'node',
    'file',
    'image',
    'field_ui',
    'views_ui',
    'system',
    'language',
  ];

  /**
   * Permissions for user that will be logged-in for test.
   *
   * @var array
   */
  protected static $userPermissions = [
    'access test_entity_browser_file entity browser pages',
    'create article content',
    'access content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FieldStorageConfig::create([
      'field_name' => 'field_reference',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'file',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_reference',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Reference',
      'settings' => [],
    ])->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_form_display')
      ->load('node.article.default');

    $form_display->setComponent('field_reference', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'test_entity_browser_file',
        'field_widget_display' => 'label',
        'open' => TRUE,
      ],
    ])->save();

    ConfigurableLanguage::createFromLangcode('fr')->save();

    $account = $this->drupalCreateUser(static::$userPermissions);
    $this->drupalLogin($account);
  }

  /**
   * Return an entity browser if it exists or creates a new one.
   *
   * @param string $browser_name
   *   The entity browser name.
   * @param string $display_id
   *   The display plugin id.
   * @param string $widget_selector_id
   *   The widget selector id.
   * @param string $selection_display_id
   *   The selection display id.
   * @param array $display_configuration
   *   The display plugin configuration.
   * @param array $widget_selector_configuration
   *   The widget selector configuration.
   * @param array $selection_display_configuration
   *   The selection display configuration.
   * @param array $widget_configurations
   *   Widget configurations. Have be provided with widget UUIDs.
   *
   * @return \Drupal\entity_browser\EntityBrowserInterface
   *   Returns an Entity Browser.
   */
  protected function getEntityBrowser($browser_name, $display_id, $widget_selector_id, $selection_display_id, array $display_configuration = [], array $widget_selector_configuration = [], array $selection_display_configuration = [], array $widget_configurations = []) {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('entity_browser');

    /** @var \Drupal\entity_browser\EntityBrowserInterface $browser */
    $browser = $storage->load($browser_name) ?: $storage->create(['name' => $browser_name]);

    $browser->setDisplay($display_id);
    if ($display_configuration) {
      $browser->getDisplay()->setConfiguration($display_configuration);
    }

    $browser->setWidgetSelector($widget_selector_id);
    if ($widget_selector_configuration) {
      $browser->getSelectionDisplay()
        ->setConfiguration($widget_selector_configuration);
    }

    $browser->setSelectionDisplay($selection_display_id);
    if ($selection_display_configuration) {
      $browser->getSelectionDisplay()
        ->setConfiguration($selection_display_configuration);
    }

    // Apply custom widget configurations.
    if ($widget_configurations) {
      foreach ($widget_configurations as $widget_uuid => $widget_config) {
        $view_widget = $browser->getWidget($widget_uuid);
        $view_widget->setConfiguration(NestedArray::mergeDeep($view_widget->getConfiguration(), $widget_config));
      }
    }

    $browser->save();

    // Clear caches after new browser is saved to remove old cached states.
    drupal_flush_all_caches();

    return $browser;
  }

  /**
   * Creates an image.
   *
   * @param string $name
   *   The name of the image.
   * @param string $extension
   *   File extension.
   *
   * @return \Drupal\file\FileInterface
   *   Returns an image.
   */
  protected function createFile($name, $extension = 'jpg') {
    file_put_contents('public://' . $name . '.' . $extension, $this->randomMachineName());

    $image = File::create([
      'filename' => $name . '.' . $extension,
      'uri' => 'public://' . $name . '.' . $extension,
    ]);
    $image->setPermanent();
    $image->save();

    return $image;
  }

  /**
   * Waits for jQuery to become ready and animations to complete.
   */
  protected function waitForAjaxToFinish() {
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Drag element in document with defined offset position.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   Element that will be dragged.
   * @param int $offsetX
   *   Vertical offset for element drag in pixels.
   * @param int $offsetY
   *   Horizontal offset for element drag in pixels.
   */
  protected function dragDropElement(NodeElement $element, $offsetX, $offsetY) {

    $elemXpath = $element->getXpath();

    $jsCode = "var fireMouseEvent = function (type, element, x, y) {
      var event = document.createEvent('MouseEvents');
      event.initMouseEvent(type, true, (type !== 'mousemove'), window, 0, 0, 0, x, y, false, false, false, false, 0, element);
      element.dispatchEvent(event); };";

    // XPath provided by getXpath uses single quote (') to encapsulate strings,
    // that's why xpath has to be quited with double quites in javascript code.
    $jsCode .= "(function() {
      var dragElement = document.evaluate(\"{$elemXpath}\", document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
      var pos = dragElement.getBoundingClientRect();
      var centerX = Math.floor((pos.left + pos.right) / 2);
      var centerY = Math.floor((pos.top + pos.bottom) / 2);
      fireMouseEvent('mousedown', dragElement, centerX, centerY);
      var xOffset = {$offsetX};
      var yOffset = {$offsetY};
      var moves = 3;
	    for (i = 0 ; i < moves ; i++ ) {
			  centerX += xOffset / moves;
			  centerY += yOffset / moves;
		    fireMouseEvent('mousemove', dragElement, Math.round(centerX), Math.round(centerY));
		  }
      fireMouseEvent('mouseup', dragElement, centerX, centerY);
    })();";

    $this->getSession()->executeScript($jsCode);
  }

  /**
   * Checks that a specific radio input element exists on the current page.
   *
   * @param string $value
   *   The string value of the radio element.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The radio input form element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function assertRadioExistsByValue($value) {
    $value = (string) $value;
    return $this->assertSession()
      ->elementExists('xpath', "//input[contains(@type, 'radio') and contains(@value, '" . $value . "')]");
  }

  /**
   * Checks that a specific radio input element does not exist on the current page.
   *
   * @param string $value
   *   The string value of the radio element.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The radio input form element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function assertRadioNotExistsByValue($value) {
    $value = (string) $value;
    return $this->assertSession()
      ->elementNotExists('xpath', "//input[contains(@type, 'radio') and contains(@value, '" . $value . "')]");
  }

  /**
   * Checks that a specific checkbox input element exists on the current page.
   *
   * @param string $value
   *   The string value of the radio element.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The radio input form element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function assertCheckboxExistsByValue($value) {
    $value = (string) $value;
    return $this->assertSession()
      ->elementExists('xpath', "//input[contains(@type, 'checkbox') and contains(@value, '" . $value . "')]");
  }

  /**
   * Checks that a specific checkbox input element does not exist on the current page.
   *
   * @param string $value
   *   The string value of the radio element.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The radio input form element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function assertCheckboxNotExistsByValue($value) {
    $value = (string) $value;
    return $this->assertSession()
      ->elementNotExists('xpath', "//input[contains(@type, 'checkbox') and contains(@value, '" . $value . "')]");
  }

  /**
   * Checks the core version.
   *
   * @param string $version
   *   The core version, for example 10.2.
   *
   * @return bool
   *   Whether the core version is higher than the requested one.
   */
  protected function coreVersion(string $version): bool {
    return version_compare(\Drupal::VERSION, $version, '>=');
  }

}
