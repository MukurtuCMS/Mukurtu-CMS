<?php

namespace Drupal\Tests\message\Functional;

/**
 * Testing the CRUD functionality for the Message template entity.
 *
 * @group Message
 */
class MessageTemplateCrudTest extends MessageTestBase {

  /**
   * Creating/reading/updating/deleting the message template entity and test it.
   */
  public function testCrudEntityType() {
    // Create the message template.
    $created_message_template = $this->createMessageTemplate('dummy_message', 'Dummy test', 'This is a dummy message with a dummy message', ['Dummy message']);

    // Reset any static cache.
    drupal_static_reset();

    // Load the message and verify the message template structure.
    $template = $this->loadMessageTemplate('dummy_message');

    $values = [
      'template' => 'Template',
      'label' => 'Label',
      'description' => 'Description',
      'text' => 'Text',
    ];
    foreach ($values as $key => $label) {
      $this->assertEquals(
        call_user_func([$template, 'get' . $key]),
        call_user_func([$created_message_template, 'get' . $key]),
        'The ' . $label . ' between the message we created and loaded were not the same');
    }

    // Verifying updating action.
    $template->setLabel('New label');
    $template->save();

    // Reset any static cache.
    drupal_static_reset();

    $template = $this->loadMessageTemplate('dummy_message');
    $this->assertEquals($template->getLabel(), 'New label', 'The message was updated successfully');

    // Delete the message any try to load it from the DB.
    $template->delete();

    // Reset any static cache.
    drupal_static_reset();

    $this->assertNull($this->loadMessageTemplate('dummy_message'), 'The message was not found in the DB');
  }

}
