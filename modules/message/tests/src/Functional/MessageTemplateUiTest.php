<?php

namespace Drupal\Tests\message\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;

/**
 * Testing the CRUD functionality for the Message template entity.
 *
 * @group Message
 */
class MessageTemplateUiTest extends MessageTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'config_translation',
    'message',
    'filter_test',
  ];

  /**
   * The user object.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function setUp():void {
    parent::setUp();
    $this->account = $this->drupalCreateUser([
      'administer message templates',
      'translate configuration',
      'use text format filtered_html',
    ]);
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Test the translation interface for message templates.
   */
  public function testMessageTemplateTranslate() {
    $this->drupalLogin($this->account);

    // Test the creation of a message template.
    $edit = [
      'label' => 'Dummy message',
      'template' => 'dummy_message',
      'description' => 'This is a dummy text',
      // Use some HTML to ensure text formatting is working in ::getText().
      'text[0][value]' => '<p>This is a dummy message with some dummy text</p>',
      'text[0][format]' => 'filtered_html',
    ];
    $this->drupalGet('admin/structure/message/template/add');
    $this->submitForm($edit, 'Save message template');
    $this->assertSession()->pageTextContains('The message template Dummy message created successfully.');
    $this->drupalGet('admin/structure/message/manage/dummy_message');

    $elements = [
      '//input[@value="Dummy message"]' => 'The label input text exists on the page with the right text.',
      '//input[@value="This is a dummy text"]' => 'The description of the message exists on the page.',
      '//textarea[.="<p>This is a dummy message with some dummy text</p>"]' => 'The body of the message exists in the page.',
    ];
    $this->verifyFormElements($elements);

    // Test the editing of a message template.
    $edit = [
      'label' => 'Edited dummy message',
      'description' => 'This is a dummy text after editing',
      'text[0][value]' => '<p>This is a dummy message with some edited dummy text</p>',
    ];
    $this->drupalGet('admin/structure/message/manage/dummy_message');
    $this->submitForm($edit, 'Save message template');
    $this->assertSession()->pageTextContains('The message template Edited Dummy message has been updated.');

    $this->drupalGet('admin/structure/message/manage/dummy_message');

    $elements = [
      '//input[@value="Edited dummy message"]' => 'The label input text exists on the page with the right text.',
      '//input[@value="This is a dummy text after editing"]' => 'The description of the message exists on the page.',
      '//textarea[.="<p>This is a dummy message with some edited dummy text</p>"]' => 'The body of the message exists in the page.',
    ];
    $this->verifyFormElements($elements);

    // Add language.
    ConfigurableLanguage::create(['id' => 'he', 'name' => 'Hebrew'])->save();

    // Change to post form and add text different then the original.
    $edit = [
      'translation[config_names][message.template.dummy_message][label]' => 'Translated dummy message to Hebrew',
      'translation[config_names][message.template.dummy_message][description]' => 'This is a dummy text after translation to Hebrew',
      'translation[config_names][message.template.dummy_message][text][0][value]' => '<p>This is a dummy message with translated text to Hebrew</p>',
    ];
    $this->drupalGet('admin/structure/message/manage/dummy_message/translate/he/add');
    $this->submitForm($edit, 'Save translation');

    // Go to the edit form and verify text.
    $this->drupalGet('admin/structure/message/manage/dummy_message/translate/he/edit');

    $elements = [
      '//input[@value="Translated dummy message to Hebrew"]' => 'The text in the form translation is the expected string in Hebrew.',
      '//textarea[.="This is a dummy text after translation to Hebrew"]' => 'The description element have the expected value in Hebrew.',
      '//textarea[.="<p>This is a dummy message with translated text to Hebrew</p>"]' => 'The text element have the expected value in Hebrew.',
    ];
    $this->verifyFormElements($elements);

    // Load the message template via code in hebrew and english and verify the
    // text. Also verify that when no translation, nothing gets returned.
    /** @var \Drupal\message\Entity\MessageTemplate $template */
    $template = MessageTemplate::load('dummy_message');
    $this->assertEquals('<p>This is a dummy message with translated text to Hebrew</p>', (string) $template->getText('he')[0], 'The text in hebrew pulled correctly.');
    $this->assertEquals('<p>This is a dummy message with some edited dummy text</p>', (string) $template->getText()[0], 'The text in english pulled correctly.');
    $this->assertEquals([], $template->getText('fi'), 'Nonexistent translation pulled empty.');

    // Create a message using that same template and test that multilingual text
    // still works.
    /** @var \Drupal\message\Entity\Message $template */
    $message = Message::create([
      'template' => 'dummy_message',
    ]);
    /** @var \Drupal\message\MessageViewBuilder $builder */
    $builder = $this->entityTypeManager->getViewBuilder('message');
    $this->assertEquals('<p>This is a dummy message with translated text to Hebrew</p>', (string) $message->getText('he')[0], 'The text in hebrew pulled correctly.');
    $this->assertEquals('<p>This is a dummy message with some edited dummy text</p>', (string) $message->getText()[0], 'The text in english pulled correctly.');
    $build = $builder->view($message);
    $this->assertEquals(['#markup' => '<p>This is a dummy message with some edited dummy text</p>'], $build['partial_0'], 'The text in english built correctly.');

    // Test changing the language of the message template with setLanguage().
    $message->setLanguage('he');
    $this->assertEquals(['<p>This is a dummy message with translated text to Hebrew</p>'], $message->getText(), 'The text in hebrew pulled correctly.');
    $build = $builder->view($message);
    $this->assertEquals(['#markup' => '<p>This is a dummy message with translated text to Hebrew</p>'], $build['partial_0'], 'The text in hebrew built correctly.');

    // Test again with nonexistent translation.
    $message->setLanguage('fi');
    $this->assertEquals([], $message->getText(), 'Nonexistent translation pulled empty.');
    $build = $builder->view($message);
    $this->assertArrayNotHasKey('partial_0', $build, 'Nonexistent translation built empty.');

    // Delete message via the UI.
    $this->drupalGet('admin/structure/message/delete/dummy_message');
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('There are no message templates yet.');
    $this->assertNull(MessageTemplate::load('dummy_message'), 'The message deleted via the UI successfully.');
  }

  /**
   * Verifying the form elements values in easy way.
   *
   * When all the elements are passing a pass message with the text "The
   * expected values is in the form." When one of the Xpath expression return
   * false the message will be display on screen.
   *
   * @param array $elements
   *   Array mapped by in the next format.
   *
   * @code
   *   [XPATH_EXPRESSION => MESSAGE]
   * @endcode
   */
  private function verifyFormElements(array $elements) {
    $errors = [];
    foreach ($elements as $xpath => $message) {
      $element = $this->xpath($xpath);
      if (!$element) {
        $errors[] = $message;
      }
    }

    $this->assertEmpty($errors);
  }

}
