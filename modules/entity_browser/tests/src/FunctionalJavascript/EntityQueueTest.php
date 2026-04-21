<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\entityqueue\Entity\EntitySubqueue;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests entity browser used to manipulate EntitySubqueue.
 *
 * @group entity_browser
 *
 * @package Drupal\Tests\entity_browser\FunctionalJavascript
 */
class EntityQueueTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_browser',
    'entity_browser_test',
    'entityqueue',
    'entity_browser_test_entityqueue',
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
      'access widget_context_default_value entity browser pages',
      'manipulate all entityqueues',
    ]);
  }

  /**
   * Tests entityqueue buttons.
   */
  public function testEntityQueueButtons() {

    $this->drupalLogin($this->adminUser);

    $article1 = $this->createNode(['type' => 'article', 'title' => 'Article 1']);
    $article2 = $this->createNode(['type' => 'article', 'title' => 'Article 2']);
    $article3 = $this->createNode(['type' => 'article', 'title' => 'Article 3']);

    $subqueue = EntitySubqueue::load('nodes');

    $subqueue->items->setValue([$article1, $article2, $article3]);

    $subqueue->save();

    $this->drupalGet('/admin/structure/entityqueue/nodes/nodes');

    $correct_order = [
      1 => 'Article 1',
      2 => 'Article 2',
      3 => 'Article 3',
    ];
    foreach ($correct_order as $key => $value) {
      $this->assertSession()
        ->elementContains('xpath', "(//div[contains(@class, 'item-container')])[" . $key . "]", $value);
    }

    $this->assertSession()->buttonExists('Reverse')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $correct_order = [
      1 => 'Article 3',
      2 => 'Article 2',
      3 => 'Article 1',
    ];
    foreach ($correct_order as $key => $value) {
      $this->assertSession()
        ->elementContains('xpath', "(//div[contains(@class, 'item-container')])[" . $key . "]", $value);
    }

    $this->assertSession()->buttonExists('Clear')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()
      ->elementNotExists('xpath', "//div[contains(@class, 'item-container')]");
  }

}
