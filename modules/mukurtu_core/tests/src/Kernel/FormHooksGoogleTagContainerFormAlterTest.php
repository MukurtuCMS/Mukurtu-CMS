<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\FormHooks;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests removal of the broken og_group_type condition from the Google Tag
 * container form.
 *
 * @see \Drupal\mukurtu_core\Hook\FormHooks::formGoogleTagContainerFormAlter()
 */
#[Group('mukurtu_core')]
class FormHooksGoogleTagContainerFormAlterTest extends KernelTestBase {

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
   * Tests that the og_group_type condition is removed when present.
   *
   * The condition's required "Group" context-mapping select has no usable
   * default on this admin route, which blocks saving the container
   * entirely; removing the condition's fieldset here also removes it from
   * $form_state->getValue('conditions') on submit, since
   * TagContainerForm::validateConditionsForm()/submitConditionsForm() only
   * iterate submitted values.
   */
  public function testOgGroupTypeConditionIsRemoved(): void {
    $form = [
      'conditions' => [
        'og_group_type' => ['#type' => 'details'],
        'request_path' => ['#type' => 'details'],
      ],
    ];
    $formState = new FormState();

    (new FormHooks())->formGoogleTagContainerFormAlter($form, $formState);

    $this->assertArrayNotHasKey('og_group_type', $form['conditions']);
    $this->assertArrayHasKey('request_path', $form['conditions']);
  }

  /**
   * Tests that a form without the condition is left alone without error.
   */
  public function testFormWithoutOgGroupTypeConditionIsLeftAlone(): void {
    $form = ['conditions' => ['request_path' => ['#type' => 'details']]];
    $formState = new FormState();

    (new FormHooks())->formGoogleTagContainerFormAlter($form, $formState);

    $this->assertSame(['conditions' => ['request_path' => ['#type' => 'details']]], $form);
  }

}
