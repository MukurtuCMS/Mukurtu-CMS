<?php

namespace Drupal\Tests\readonly_field_widget\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests Readonly Field Widget basic behaviour.
 *
 * @group readonly_field_widget
 */
class ReadonlyFieldWidgetTest extends WebDriverTestBase {


  /**
   * {@inheritdoc}
   */
  protected static $modules = ['readonly_field_widget_test'];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = TRUE;

  /**
   * An admin user for testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $admin;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->createContentType(['name' => 'page', 'type' => 'page']);
    $this->createContentType(['name' => 'article', 'type' => 'article']);

    $tags_vocab = Vocabulary::create(['vid' => 'tags', 'name' => 'tags']);
    $tags_vocab->save();

    $this->admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($this->admin);

    $page = $this->getSession()->getPage();

    FieldStorageConfig::create([
      'field_name' => 'field_article_reference',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => 1,
    ])->save();

    $fieldStorage = FieldStorageConfig::loadByName('node', 'field_article_reference');

    FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'page',
      'label' => 'article reference',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => [
            'article' => 'article',
          ],
        ],
      ],
    ])->save();

    $efd = EntityFormDisplay::load('node.page.default');
    $efd->setComponent('field_article_reference', ['weight' => 0])->save();

    $evd = EntityViewDisplay::load('node.page.default');
    $evd->setComponent('field_article_reference')->save();

    $this->drupalGet('/admin/structure/types/manage/page/form-display');
    $page->fillField('fields[field_article_reference][type]', 'readonly_field_widget');
    $this->assertSession()->assertWaitOnAjaxRequest(1000);

    $this->assertSession()->waitForElementVisible('named', [
      'button',
      'field_article_reference_settings_edit',
    ])->press();
    $this->assertSession()->waitForElementVisible('named', ['select', 'Format']);
    $page->selectFieldOption('Format', 'Rendered entity');
    $this->assertSession()->assertWaitOnAjaxRequest(1000);
    $page->checkField('Show Description');
    $page->pressButton('Update');
    $this->assertSession()->assertWaitOnAjaxRequest(1000);
    $page->pressButton('Save');

    // Add a taxonomy term reference field.
    FieldStorageConfig::create([
      'field_name' => 'field_term_reference',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
      'cardinality' => 1,
    ])->save();

    $fieldStorage = FieldStorageConfig::loadByName('node', 'field_term_reference');

    FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'page',
      'label' => 'term reference',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'tags' => 'tags',
          ],
        ],
      ],
    ])->save();

    $efd = EntityFormDisplay::load('node.page.default');
    $efd->setComponent('field_term_reference', ['weight' => 0])->save();

    $evd = EntityViewDisplay::load('node.page.default');
    $evd->setComponent('field_term_reference')->save();

    $this->drupalGet('/admin/structure/types/manage/page/form-display');
    $page->fillField('Plugin for term reference', 'readonly_field_widget');
    $this->assertSession()->assertWaitOnAjaxRequest(1000);
    $page->pressButton('Save');

    // Add a simple text field.
    FieldStorageConfig::create([
      'field_name' => 'field_some_plain_text',
      'type' => 'string',
      'entity_type' => 'node',
      'cardinality' => 1,
    ])->save();

    $fieldStorage = FieldStorageConfig::loadByName('node', 'field_some_plain_text');

    FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'page',
      'label' => 'some plain text',
    ])->save();

    $efd = EntityFormDisplay::load('node.page.default');
    $efd->setComponent('field_some_plain_text', ['weight' => 0])->save();

    $evd = EntityViewDisplay::load('node.page.default');
    $evd->setComponent('field_some_plain_text')->save();

    $this->drupalGet('/admin/structure/types/manage/page/form-display');
    $page->fillField('Plugin for some plain text', 'readonly_field_widget');
    $this->assertSession()->assertWaitOnAjaxRequest(1000);
    $page->pressButton('Save');

    // Add a second text field.
    FieldStorageConfig::create([
      'field_name' => 'field_restricted_text',
      'type' => 'string',
      'entity_type' => 'node',
      'cardinality' => 1,
    ])->save();

    $fieldStorage = FieldStorageConfig::loadByName('node', 'field_restricted_text');

    FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'page',
      'label' => 'restricted text',
    ])->save();

    $efd = EntityFormDisplay::load('node.page.default');
    $efd->setComponent('field_restricted_text', ['weight' => 0])->save();

    $evd = EntityViewDisplay::load('node.page.default');
    $evd->setComponent('field_restricted_text')->save();

    $this->drupalGet('/admin/structure/types/manage/page/form-display');
    $page->fillField('Plugin for restricted text', 'readonly_field_widget');
    $this->assertSession()->assertWaitOnAjaxRequest(1000);
    $page->pressButton('Save');

    // Set the title to be read-only.
    $page->fillField('Plugin for Title', 'readonly_field_widget');
    $this->assertSession()->assertWaitOnAjaxRequest(1000);
    $page->pressButton('Save');

    $efd = EntityFormDisplay::load('node.page.default');
    $readonly_fields = [
      'title',
      'field_term_reference',
      'field_some_plain_text',
      'field_restricted_text',
      'field_article_reference',
    ];
    foreach ($readonly_fields as $field) {
      $this->assertSame('readonly_field_widget', $efd->getComponent($field)['type'], "$field is set to readonly");
    }
  }

  /**
   * Test that the widget still works when default values are set up.
   */
  public function testDefaultValues() {

    // Make article field required.
    $config = FieldConfig::load('node.page.field_article_reference');
    $config->set('required', TRUE);
    $config->save();
    $this->drupalGet('/admin/structure/types/manage/page/fields/node.page.field_article_reference');
    $page = $this->getSession()->getPage();

    // Set title to regular text field.
    $efd = EntityFormDisplay::load('node.page.default');
    $efd->setComponent('title', [
      'type' => 'string_textfield',
      'region' => 'content',
    ])->save();

    // Set default value of article field to a test article node.
    $article = $this->createNode([
      'type' => 'article',
      'title' => "article {$this->randomMachineName()}",
      'status' => 1,
    ]);
    $article->save();

    $config = FieldConfig::load('node.page.field_article_reference');
    $config->set('default_value', [['target_uuid' => $article->uuid()]]);
    $config->save();

    $this->drupalGet('/node/add/page');
    $this->assertSession()->pageTextContains($article->label());
    $new_title = $this->randomMachineName();
    $page->fillField('Title', $new_title);
    $page->pressButton('Save');
    $this->assertSession()->pageTextContains("page $new_title has been created.");
    $this->assertSession()->pageTextContains($article->label());
  }

  /**
   * Test field access on readonly fields.
   */
  public function testFieldAccess() {

    $assert = $this->assertSession();

    $test_string = $this->randomMachineName();
    $restricted_test_string = $this->randomMachineName();

    $article = $this->createNode([
      'type' => 'article',
      'title' => 'test-article',
    ]);

    $tag_term = Term::create(['vid' => 'tags', 'name' => 'test-tag']);
    $tag_term->save();

    $page = $this->createNode([
      'type' => 'page',
      'field_some_plain_text' => [['value' => $test_string]],
      'field_restricted_text' => [['value' => $restricted_test_string]],
      'field_article_reference' => $article,
      'field_term_reference' => $tag_term,
    ]);

    // As an admin, verify the widgets are readonly.
    $this->drupalLogin($this->admin);
    $this->drupalGet('node/' . $page->id() . '/edit');

    // Test the title field shows with a label.
    $field_wrapper = $assert->elementExists('css', '#edit-title-wrapper');
    $assert->elementNotExists('css', 'input', $field_wrapper);
    $assert->elementNotExists('css', 'a', $field_wrapper);
    $this->assertFieldWrapperContainsString('Title', $field_wrapper);
    $this->assertFieldWrapperContainsString($page->label(), $field_wrapper);

    $field_wrapper = $assert->elementExists('css', '#edit-field-some-plain-text-wrapper');
    $this->assertFieldWrapperContainsString($test_string, $field_wrapper);
    $assert->elementNotExists('css', 'input', $field_wrapper);

    // This shouldn't be editable by admin, but they can view it.
    $field_wrapper = $assert->elementExists('css', '#edit-field-restricted-text-wrapper');
    $this->assertFieldWrapperContainsString($restricted_test_string, $field_wrapper);
    $assert->elementNotExists('css', 'input', $field_wrapper);

    $field_wrapper = $assert->elementExists('css', '#edit-field-article-reference-wrapper');
    $this->assertFieldWrapperContainsString('test-article', $field_wrapper);
    $title_element = $assert->elementExists('css', 'h2 a span', $field_wrapper);
    $this->assertEquals($title_element->getText(), 'test-article');
    $assert->elementNotExists('css', 'input', $field_wrapper);
    $assert->elementNotExists('css', 'select', $field_wrapper);

    $field_wrapper = $assert->elementExists('css', '#edit-field-term-reference-wrapper');
    $this->assertFieldWrapperContainsString('test-tag', $field_wrapper);
    $title_element = $assert->elementExists('css', 'div:nth-child(2) a', $field_wrapper);
    $this->assertEquals($title_element->getText(), 'test-tag');
    $assert->elementNotExists('css', 'input', $field_wrapper);
    $assert->elementNotExists('css', 'select', $field_wrapper);

    // Create a regular who can update page nodes.
    $user = $this->createUser(['edit any page content']);
    $this->drupalLogin($user);
    $this->drupalGet('node/' . $page->id() . '/edit');
    $field_wrapper = $assert->elementExists('css', '#edit-field-some-plain-text-wrapper');
    $this->assertFieldWrapperContainsString($test_string, $field_wrapper);
    $assert->elementNotExists('css', 'input', $field_wrapper);

    // This field is restricted via hooks in readonly_field_widget_test.module.
    $assert->elementNotExists('css', '#edit-field-restricted-text-wrapper');
    $this->assertSession()->responseNotContains($restricted_test_string);

    $field_wrapper = $assert->elementExists('css', '#edit-field-article-reference-wrapper');
    $this->assertFieldWrapperContainsString('test-article', $field_wrapper);
    $title_element = $assert->elementExists('css', 'h2 a span', $field_wrapper);
    $this->assertEquals($title_element->getText(), 'test-article');
    $assert->elementNotExists('css', 'input', $field_wrapper);
    $assert->elementNotExists('css', 'select', $field_wrapper);

    $field_wrapper = $assert->elementExists('css', '#edit-field-term-reference-wrapper');
    $this->assertFieldWrapperContainsString('test-tag', $field_wrapper);
    $title_element = $assert->elementExists('css', 'div:nth-child(2) a', $field_wrapper);
    $this->assertEquals($title_element->getText(), 'test-tag');
    $assert->elementNotExists('css', 'input', $field_wrapper);
    $assert->elementNotExists('css', 'select', $field_wrapper);
  }

  /**
   * Check if the field widget wrapper contains the passed in string.
   */
  private function assertFieldWrapperContainsString($string, NodeElement $element) {
    $this->assertTrue((bool) preg_match('/' . $string . '/', $element->getHtml()), "field wrapper contains '" . $string . "'");
  }

}
