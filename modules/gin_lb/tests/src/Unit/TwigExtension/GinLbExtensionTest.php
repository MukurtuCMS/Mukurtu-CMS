<?php

declare(strict_types=1);

namespace Drupal\Tests\gin_lb\Unit\TwigExtension;

use Drupal\Core\Template\Attribute;
use Drupal\gin_lb\TwigExtension\GinLbExtension;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\gin_lb\TwigExtension\GinLbExtension
 *
 * @group gin_lb
 */
class GinLbExtensionTest extends UnitTestCase {

  /**
   * @covers ::calculateDependencies
   */
  public function testGinClasses(): void {
    $extension = new GinLbExtension();

    $attributes = new Attribute();
    $attributes->addClass('form-item');
    $attributes->addClass('js-form-item');

    $cleaned_attributes = $extension->ginClasses($attributes);
    $this->assertSame('class="glb-form-item js-form-item"', $cleaned_attributes->getClass()->render());
  }

}
