<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the "Bulk edit media" action link's button style.
 *
 * Core marks action links as "add" buttons, which the admin theme draws with
 * a plus sign. Bulk editing doesn't add anything, so its link drops that mark.
 */
#[Group('mukurtu_core')]
class BulkEditActionLinkTest extends KernelTestBase {

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
    'views',
    'views_bulk_operations',
    'mukurtu_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Renders an action link to a route.
   */
  protected function renderAction(string $route): string {
    $element = [
      '#theme' => 'menu_local_action',
      '#link' => ['title' => 'Link', 'url' => Url::fromRoute($route)],
    ];
    return (string) $this->container->get('renderer')->renderInIsolation($element);
  }

  /**
   * Only the bulk edit media link loses the "add" mark.
   */
  public function testActionClasses(): void {
    $bulk = $this->renderAction('mukurtu_core.bulk_edit_media');
    $this->assertStringContainsString('button', $bulk);
    $this->assertStringNotContainsString('button-action', $bulk);

    $this->assertStringContainsString('button-action', $this->renderAction('node.add_page'));
  }

}
