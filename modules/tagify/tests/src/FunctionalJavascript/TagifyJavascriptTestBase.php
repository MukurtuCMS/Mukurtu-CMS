<?php

namespace Drupal\Tests\tagify\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\tagify\Traits\TagifyTestTrait;

/**
 * Class TagifyJavascriptTestBase.
 *
 * Base class for tagify Javascript tests.
 */
abstract class TagifyJavascriptTestBase extends WebDriverTestBase {

  use TagifyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'tagify', 'options'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    // Create a new content type.
    $this->drupalCreateContentType(['type' => 'test']);
    // Create a new user with the proper permissions.
    $user = $this->drupalCreateUser([
      'access content',
      'edit own test content',
      'create test content',
    ]);

    // Login into the site with the user previously created.
    $this->drupalLogin($user);
  }

  /**
   * Scroll element with defined css selector in middle of browser view.
   *
   * @param string $cssSelector
   *   CSS Selector for element that should be centralized.
   */
  protected function scrollElementInView($cssSelector) {
    $this->getSession()
      ->executeScript('
        var viewPortHeight = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
        var element = jQuery(\'' . addcslashes($cssSelector, '\'') . '\');
        var scrollTop = element.offset().top - (viewPortHeight/2);
        var scrollableParent = jQuery.isFunction(element.scrollParent) ? element.scrollParent() : [];
        if (scrollableParent.length > 0 && scrollableParent[0] !== document && scrollableParent[0] !== document.body) { scrollableParent[0].scrollTop = scrollTop } else { window.scroll(0, scrollTop); };
      ');
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

    $elemXpath = addslashes($element->getXpath());

    $jsCode = "var fireMouseEvent = function (type, element, x, y) {"
      . "  var event = document.createEvent('MouseEvents');"
      . "  event.initMouseEvent(type, true, (type !== 'mousemove'), window, 0, 0, 0, x, y, false, false, false, false, 0, element);"
      . "  element.dispatchEvent(event); };";

    // XPath provided by getXpath uses single quote (') to encapsulate strings,
    // that's why xpath has to be quited with double quites in javascript code.
    $jsCode .= "(function() {" .
      "  var dragElement = document.evaluate(\"{$elemXpath}\", document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;" .
      "  var pos = dragElement.getBoundingClientRect();" .
      "  var centerX = Math.floor((pos.left + pos.right) / 2);" .
      "  var centerY = Math.floor((pos.top + pos.bottom) / 2);" .
      "  fireMouseEvent('mousedown', dragElement, centerX, centerY);" .
      "  fireMouseEvent('mousemove', document, centerX + {$offsetX}, centerY + {$offsetY});" .
      "  fireMouseEvent('mouseup', dragElement, centerX + {$offsetX}, centerY + {$offsetY});" .
      "})();";

    $this->getSession()->executeScript($jsCode);
  }

}
