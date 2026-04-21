<?php

namespace Drupal\Tests\dashboards\Functional;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;
use Drupal\Tests\system\Traits\OffCanvasTestTrait;

/**
 * Tests that the Rules UI pages are reachable.
 *
 * @group dashboards
 */
class PersonalizeTest extends WebDriverTestBase {

  use OffCanvasTestTrait;
  use ContextualLinkClickTrait;

  const INLINE_BLOCK_LOCATOR = '.block-inline-blockbasic';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'dashboards',
    'block',
    'block_content',
    'user',
    'node',
    'contextual',
  ];

  /**
   * User with proper permissions for module configuration.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * User with content access.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $normalUser;

  /**
   * Testing dashboard name.
   *
   * @var string
   */
  public const DASHBOARD_NAME = "default";

  /**
   * Theme to enable.
   *
   * @var string
   */
  protected $defaultTheme = 'olivero';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->container->get('theme_installer')->install(['claro']);

    $config = $this->config('system.theme');
    $config->set('admin', 'claro');
    $config->set('default', 'olivero');
    $config->save();
    $this->createBlockContentType('basic', 'Basic block');

    $this->adminUser = $this->drupalCreateUser([
      'administer dashboards',
      'view the administration theme',
      'administer blocks',
      'create and edit custom blocks',
      'access contextual links',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Creates a block content type.
   *
   * @param string $id
   *   The block type id.
   * @param string $label
   *   The block type label.
   */
  protected function createBlockContentType($id, $label) {
    $bundle = BlockContentType::create([
      'id' => $id,
      'label' => $label,
      'revision' => 1,
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());
  }

  /**
   * Testing permission "administer dashboards".
   */
  public function testPersonalize() {
    $this->setupCreateDashboard();
    /**
     * @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert
     */
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalGet('dashboards/' . static::DASHBOARD_NAME . '/layout');

    $page->clickLink('Add section');
    $assert_session->waitForElementVisible('named', ['link', 'One column']);
    $assert_session->pageTextNotContains('You have unsaved changes.');
    $page->clickLink('One column');
    $assert_session->waitForElementVisible('named', ['button', 'Add section']);
    $page->pressButton('Add section');
    // This call is needed to avoid error in tests: Failed asserting that the
    // page matches the pattern 'You have unsaved changes'.
    $assert_session->assertWaitOnAjaxRequest();

    $assert_session->pageTextContainsOnce('You have unsaved changes.');
    $this->addInlineBlockToLayout('MY BLOCK', 'HERE IS MY BLOCK');
    $page->findButton('Save layout')->click();
    $assert_session->pageTextContains('MY BLOCK');

    $this->drupalGet('dashboard/' . static::DASHBOARD_NAME . '/override');
    $this->configureInlineBlock('HERE IS MY BLOCK', 'HERE IS MY NEW BLOCK', static::INLINE_BLOCK_LOCATOR);
    $page->findButton('Save layout')->click();
    $assert_session->pageTextContains('HERE IS MY NEW BLOCK');

    $this->drupalGet('dashboard/' . static::DASHBOARD_NAME . '/override');
    $assert_session->pageTextContains('HERE IS MY NEW BLOCK');
    $page->findButton('Reset to default')->click();
    $assert_session->pageTextContains('HERE IS MY BLOCK');
  }

  /**
   * Create a basic block and add to layout.
   *
   * @param string $title
   *   Title for block.
   * @param string $body
   *   Body for block.
   */
  protected function addInlineBlockToLayout($title, $body) {
    /**
     * @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert
     */
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $page->clickLink('Add block');
    // The link label is subject to changes in Layout builder.
    $this->assertNotEmpty($assert_session->waitForLink('Create content block'));
    $this->clickLink('Create content block');
    $textarea = $assert_session->waitForElement('css', '[name="settings[block_form][body][0][value]"]');
    $this->assertNotEmpty($textarea);
    $assert_session->fieldValueEquals('Title', '');
    $page->findField('Title')->setValue($title);
    $textarea->setValue($body);
    $page->pressButton('Add block');
    $this->assertDialogClosedAndTextVisible($body, static::INLINE_BLOCK_LOCATOR);
  }

  /**
   * Configures an inline block in the Layout Builder.
   *
   * @param string $old_body
   *   The old body field value.
   * @param string $new_body
   *   The new body field value.
   * @param string $block_css_locator
   *   The CSS locator to use to select the contextual link.
   */
  protected function configureInlineBlock($old_body, $new_body, $block_css_locator = NULL) {
    /**
     * @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert
     */
    $assert_session = $this->assertSession();
    $block_css_locator = $block_css_locator ?: static::INLINE_BLOCK_LOCATOR;
    $page = $this->getSession()->getPage();
    $this->clickContextualLink($block_css_locator, 'Configure');
    $textarea = $assert_session->waitForElementVisible('css', '[name="settings[block_form][body][0][value]"]');
    $this->assertNotEmpty($textarea);
    $this->assertSame($old_body, $textarea->getValue());
    $textarea->setValue($new_body);
    $page->pressButton('Update');
    $assert_session->assertNoElementAfterWait('css', '#drupal-off-canvas');
    $this->assertDialogClosedAndTextVisible($new_body);
  }

  /**
   * Setup dashboard for testing purposes.
   */
  private function setupCreateDashboard() {
    $this->drupalGet('admin/structure/dashboards');
    $this->clickLink('New dashboard');

    $this->submitForm([
      'admin_label' => static::DASHBOARD_NAME,
    ], 'Save');

    $this->assertSession()->pageTextContains(static::DASHBOARD_NAME);
  }

  /**
   * Asserts that the dialog closes and the new text appears on the main canvas.
   *
   * @param string $text
   *   The text.
   * @param string|null $css_locator
   *   The css locator to use inside the main canvas if any.
   */
  protected function assertDialogClosedAndTextVisible($text, $css_locator = NULL) {
    /**
     * @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert
     */
    $assert_session = $this->assertSession();
    $assert_session->assertNoElementAfterWait('css', '#drupal-off-canvas');
    $assert_session->elementNotExists('css', '#drupal-off-canvas');
    if ($css_locator) {
      $this->assertNotEmpty($assert_session->waitForElementVisible('css', ".dialog-off-canvas-main-canvas $css_locator:contains('$text')"));
    }
    else {
      $this->assertNotEmpty($assert_session->waitForElementVisible('css', ".dialog-off-canvas-main-canvas:contains('$text')"));
    }
  }

}
