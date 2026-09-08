<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ViewsSchemaHooks::configSchemaInfoAlter() (issue #1638).
 *
 * @see \Drupal\mukurtu_multilingual\Hook\ViewsSchemaHooks
 */
#[Group('mukurtu_multilingual')]
class ViewsSchemaHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'views', 'search_api', 'mukurtu_multilingual'];

  /**
   * The search_api_field Views field's rewrite-results text is marked
   * non-translatable, not core's default translatable "text" type - so
   * Drupal's locale tooling stops flagging the uuid/nid <div> markup as
   * a disallowed-HTML translatable string.
   */
  public function testRewriteTextIsNotTranslatable(): void {
    $definition = \Drupal::service('config.typed')->getDefinition('views.field.search_api_field');

    $this->assertSame(
      'string',
      $definition['mapping']['alter']['mapping']['text']['type'],
      'The search_api_field rewrite-results text should be typed as a plain (non-translatable) string.'
    );
  }

}
