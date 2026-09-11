<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_footer\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the footer Contact link, on fresh installs and on upgrades.
 *
 * The site-wide contact form at /contact has always been installed and
 * permitted for anonymous users, but nothing linked to it: core's footer menu
 * link for the route ships disabled, and the block rendering the core footer
 * menu is disabled too.
 *
 * @see mukurtu_footer_install()
 */
#[Group('mukurtu_footer')]
class FooterContactLinkTest extends KernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'block',
    'block_content',
    'user',
    'text',
    'link',
    'filter',
    'options',
    'token',
    'file',
    'image',
    'entity_reference_revisions',
    'paragraphs',
    'mukurtu_footer',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('system', 'sequences');
    $this->installSchema('file', 'file_usage');
    $this->installConfig(['field', 'filter', 'user', 'mukurtu_footer']);

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_footer');
    require_once $module_path . '/mukurtu_footer.install';
  }

  /**
   * Loads the footer block content entity, if any.
   */
  protected function loadFooter(): ?BlockContent {
    $footers = \Drupal::entityTypeManager()
      ->getStorage('block_content')
      ->loadByProperties(['type' => 'mukurtu_footer']);

    return $footers ? reset($footers) : NULL;
  }

  /**
   * Returns the footer's other links as uri => title pairs.
   */
  protected function otherLinks(): array {
    $links = [];
    foreach ($this->loadFooter()->get('field_footer_other_links') as $item) {
      $links[$item->uri] = $item->title;
    }

    return $links;
  }

  /**
   * A fresh install gets the Contact link.
   */
  public function testInstallAddsContactLink(): void {
    mukurtu_footer_install();

    $this->assertSame(['internal:/contact' => 'Contact'], $this->otherLinks());
  }

  /**
   * The link resolves to /contact when the block plugin renders it.
   */
  public function testContactLinkResolvesToContactPath(): void {
    mukurtu_footer_install();

    $item = $this->loadFooter()->get('field_footer_other_links')->first();
    $this->assertSame('/contact', $item->getUrl()->toString());
  }
}
