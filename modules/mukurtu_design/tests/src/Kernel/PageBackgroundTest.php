<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

use Drupal\Core\Path\PathMatcherInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\file\Entity\File;
use Drupal\mukurtu_design\PageBackground;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which page background a request gets, if any.
 */
#[Group('mukurtu_design')]
class PageBackgroundTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'mukurtu_design'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['mukurtu_design']);
  }

  /**
   * Returns the service, with isFrontPage() forced to the given answer.
   */
  protected function pageBackground(bool $is_front = FALSE): PageBackground {
    $matcher = $this->createMock(PathMatcherInterface::class);
    $matcher->method('isFrontPage')->willReturn($is_front);

    return new PageBackground(
      $this->container->get('config.factory'),
      $this->container->get('entity_type.manager'),
      $this->container->get('file_url_generator'),
      $matcher,
    );
  }

  /**
   * Creates a file entity and points the config at it.
   */
  protected function setBackgroundImage(): File {
    $file = File::create(['uri' => 'public://background.png', 'status' => 1]);
    $file->save();

    $this->config('mukurtu_design.settings')->set('background.image', (int) $file->id())->save();

    return $file;
  }

  /**
   * A site that has not chosen an image gets no background.
   */
  public function testNoImageMeansNoBackground(): void {
    $this->assertSame([], $this->pageBackground()->resolve());
  }

  /**
   * A configured image is returned with its treatment.
   */
  public function testReturnsTheConfiguredImage(): void {
    $this->setBackgroundImage();

    $result = $this->pageBackground()->resolve();

    $this->assertStringEndsWith('/background.png', $result['url']);
    $this->assertSame('light', $result['treatment']);
  }

  /**
   * The front page opt-out suppresses the background only on the front page.
   */
  public function testFrontPageOptOut(): void {
    $this->setBackgroundImage();
    $this->config('mukurtu_design.settings')->set('background.show_on_front', FALSE)->save();

    $this->assertSame([], $this->pageBackground(TRUE)->resolve(), 'The front page opts out.');
    $this->assertNotSame([], $this->pageBackground(FALSE)->resolve(), 'Other pages keep the background.');
  }

  /**
   * With the opt-out off, the front page keeps the background.
   */
  public function testFrontPageKeepsBackgroundWhenEnabled(): void {
    $this->setBackgroundImage();

    $this->assertNotSame([], $this->pageBackground(TRUE)->resolve());
  }

  /**
   * A deleted file must not fatal, and must not emit a broken url().
   *
   * Nothing stops an admin deleting the file from the files listing, which
   * would otherwise leave a dangling fid in config.
   */
  public function testDanglingFileIdIsIgnored(): void {
    $file = $this->setBackgroundImage();
    $file->delete();

    $this->assertSame([], $this->pageBackground()->resolve());
  }

  /**
   * An unrecognised treatment falls back to the readable default.
   */
  public function testUnknownTreatmentFallsBackToLight(): void {
    $this->setBackgroundImage();
    $this->config('mukurtu_design.settings')->set('background.text_treatment', 'chartreuse')->save();

    $this->assertSame('light', $this->pageBackground()->resolve()['treatment']);
  }

  /**
   * The result must be cacheable per config and per front-page-ness.
   *
   * Without the url.path.is_front context the front page opt-out would leak
   * one page's answer onto the other.
   */
  public function testCacheability(): void {
    $metadata = $this->pageBackground()->getCacheableMetadata();

    $this->assertContains('config:mukurtu_design.settings', $metadata->getCacheTags());
    $this->assertContains('url.path.is_front', $metadata->getCacheContexts());
  }

}
