<?php

namespace Drupal\Tests\message_subscribe_email\Kernel\Form;

use Drupal\Tests\message_subscribe\Kernel\Form\MessageSubscribeAdminSettingsTest;

/**
 * Test the admin settings form.
 *
 * @group message_subscribe
 */
class AdminSettingsTest extends MessageSubscribeAdminSettingsTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['message_subscribe_email'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->values['message_subscribe_email_flag_prefix'] = [
      '#value' => 'non_standard_email',
      '#config_name' => 'message_subscribe_email.settings',
      '#config_key' => 'flag_prefix',
    ];
  }

}
