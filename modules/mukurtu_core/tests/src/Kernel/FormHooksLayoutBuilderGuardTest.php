<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\FormHooks;

/**
 * Tests the Layout Builder guard rail on Manage Display forms.
 *
 * @see \Drupal\mukurtu_core\Hook\FormHooks::formEntityViewDisplayEditFormAlter()
 * @group mukurtu_core
 */
class FormHooksLayoutBuilderGuardTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'mukurtu_core',
  ];

  /**
   * Tests that the checkbox is disabled only for unsupported node bundles.
   *
   * @dataProvider bundleProvider
   */
  public function testLayoutBuilderCheckboxGuard(string $entityTypeId, string $bundle, bool $expectDisabled): void {
    $display = $this->createMock(EntityViewDisplayInterface::class);
    $display->method('getTargetEntityTypeId')->willReturn($entityTypeId);
    $display->method('getTargetBundle')->willReturn($bundle);

    $formObject = $this->createMock(EntityFormInterface::class);
    $formObject->method('getEntity')->willReturn($display);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($formObject);

    $form = ['layout' => ['enabled' => ['#type' => 'checkbox']]];

    (new FormHooks())->formEntityViewDisplayEditFormAlter($form, $formState);

    if ($expectDisabled) {
      $this->assertTrue($form['layout']['enabled']['#disabled']);
      $this->assertNotEmpty((string) $form['layout']['enabled']['#description']);
    }
    else {
      $this->assertArrayNotHasKey('#disabled', $form['layout']['enabled']);
    }
  }

  /**
   * Data provider of node bundles and entity types.
   *
   * Covers the Layout Builder guard rail's unsupported list, plus a
   * supported node bundle and a non-node entity type for contrast.
   */
  public static function bundleProvider(): array {
    return [
      'unsupported bundle: article' => ['node', 'article', TRUE],
      'unsupported bundle: page' => ['node', 'page', TRUE],
      'unsupported bundle: digital_heritage' => ['node', 'digital_heritage', TRUE],
      'unsupported bundle: place' => ['node', 'place', TRUE],
      'supported node bundle is left alone' => ['node', 'landing_page', FALSE],
      'non-node entity type is left alone' => ['media', 'article', FALSE],
    ];
  }

  /**
   * Tests that forms without a Layout Builder element are left untouched.
   *
   * The display entity should never be inspected in that case.
   */
  public function testFormWithoutLayoutElementIsLeftAlone(): void {
    $formState = $this->createMock(FormStateInterface::class);
    $formState->expects($this->never())->method('getFormObject');

    $form = [];
    (new FormHooks())->formEntityViewDisplayEditFormAlter($form, $formState);

    $this->assertSame([], $form);
  }

}
