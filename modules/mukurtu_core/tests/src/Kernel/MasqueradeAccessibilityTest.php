<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\masquerade\Form\MasqueradeForm;
use Drupal\masquerade\Masquerade;
use Drupal\mukurtu_core\Hook\MasqueradeHooks;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core's accessibility fixes to the Masquerade module's UI.
 *
 * @see \Drupal\mukurtu_core\Hook\MasqueradeHooks
 */
#[Group('mukurtu_core')]
class MasqueradeAccessibilityTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'geofield',
    'leaflet',
    'file',
    'image',
    'media',
    'masquerade',
    'og',
    'options',
    'mukurtu_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('og_membership');
    $this->installSchema('system', ['sequences']);
  }

  /**
   * The "Masquerade as" operation's accessible name includes the user's name.
   */
  public function testOperationNamesTheUser(): void {
    // The first user created is uid 1, which may masquerade as anyone.
    $this->setCurrentUser($this->createUser());
    $target = $this->createUser([], 'masquerade_target');

    $operations = \Drupal::entityTypeManager()->getListBuilder('user')->getOperations($target);

    $this->assertArrayHasKey('masquerade', $operations, 'Precondition: Masquerade did not add its operation, so there is nothing to label.');
    $attributes = $operations['masquerade']['url']->getOption('attributes');
    $this->assertSame('Masquerade as masquerade_target', (string) $attributes['aria-label']);
  }

  /**
   * The form shows its label and redirects after Masquerade's own submit.
   */
  public function testFormShowsLabelAndAddsSubmitHandler(): void {
    $this->setCurrentUser($this->createUser());

    $form = \Drupal::formBuilder()->getForm(MasqueradeForm::class);

    $this->assertSame('before', $form['autocomplete']['masquerade_as']['#title_display']);
    $this->assertSame(
      ['::submitForm', [MasqueradeHooks::class, 'masqueradeBlockFormSubmit']],
      $form['#submit'],
      'The redirect handler must run after Masquerade has switched the user.'
    );
  }

  /**
   * A successful switch goes to the front page and confirms it.
   */
  public function testSubmitRedirectsAndConfirmsAfterSwitch(): void {
    $this->stubMasquerading(TRUE);
    $form_state = (new FormState())->setValue('masquerade_target_account', $this->createUser([], 'masquerade_target'));
    $form = [];

    MasqueradeHooks::masqueradeBlockFormSubmit($form, $form_state);

    $this->assertSame('<front>', $form_state->getRedirect()->getRouteName());
    $messages = \Drupal::messenger()->messagesByType('status');
    $this->assertSame('You are now masquerading as masquerade_target.', (string) reset($messages));
  }

  /**
   * If the switch did not happen, the handler does nothing.
   */
  public function testSubmitDoesNothingWithoutSwitch(): void {
    $this->stubMasquerading(FALSE);
    $form_state = (new FormState())->setValue('masquerade_target_account', $this->createUser([], 'masquerade_target'));
    $form = [];

    MasqueradeHooks::masqueradeBlockFormSubmit($form, $form_state);

    $this->assertNull($form_state->getRedirect());
    $this->assertEmpty(\Drupal::messenger()->all());
  }

  /**
   * Replaces the masquerade service with one reporting a fixed state.
   *
   * Masquerade::switchTo() migrates the session, which a Kernel test cannot do.
   */
  protected function stubMasquerading(bool $is_masquerading): void {
    $masquerade = $this->createMock(Masquerade::class);
    $masquerade->method('isMasquerading')->willReturn($is_masquerading);
    $this->container->set('masquerade', $masquerade);
  }

}
