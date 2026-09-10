<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\FormHooks;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the "run cron from outside the site" URL is pushed to the
 * bottom of the Cron settings form.
 *
 * @see \Drupal\mukurtu_core\Hook\FormHooks::formSystemCronSettingsAlter()
 */
#[Group('mukurtu_core')]
class FormHooksSystemCronSettingsFormAlterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'geofield',
    'leaflet',
    'node',
    'mukurtu_core',
  ];

  /**
   * Tests that the cron_url element's weight is set below other elements.
   */
  public function testCronUrlIsWeightedToBottom(): void {
    $form = [
      'description' => ['#markup' => 'Cron takes care of running periodic tasks.'],
      'run' => ['#type' => 'submit'],
      'status' => ['#markup' => 'Last run: never'],
      'cron_url' => ['#markup' => 'To run cron from outside the site, go to ...'],
      'cron' => ['#type' => 'details'],
      'actions' => ['submit' => ['#type' => 'submit']],
    ];
    $formState = new FormState();

    (new FormHooks())->formSystemCronSettingsAlter($form, $formState);

    $this->assertSame(100, $form['cron_url']['#weight']);
    // Untouched elements keep no explicit weight (default 0), so cron_url
    // sorts after them.
    $this->assertArrayNotHasKey('#weight', $form['cron']);
    $this->assertArrayNotHasKey('#weight', $form['actions']);
  }

  /**
   * Tests that a form without cron_url is left alone without error.
   */
  public function testFormWithoutCronUrlIsLeftAlone(): void {
    $form = ['cron' => ['#type' => 'details']];
    $formState = new FormState();

    (new FormHooks())->formSystemCronSettingsAlter($form, $formState);

    $this->assertSame(['cron' => ['#type' => 'details']], $form);
  }

  /**
   * Tests that Drupal's hook system actually discovers and invokes this
   * alter for the 'system_cron_settings' form ID, not just that the method
   * body works when called directly.
   */
  public function testHookIsWiredIntoModuleHandlerAlter(): void {
    $form = ['cron_url' => ['#markup' => 'To run cron from outside the site, go to ...']];
    $formState = new FormState();
    $formId = 'system_cron_settings';

    \Drupal::moduleHandler()->alter('form_system_cron_settings', $form, $formState, $formId);

    $this->assertSame(100, $form['cron_url']['#weight']);
  }

}
