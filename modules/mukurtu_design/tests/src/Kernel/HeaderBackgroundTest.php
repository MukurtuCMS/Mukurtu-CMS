<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

use Drupal\Core\Path\PathMatcherInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\file\Entity\File;
use Drupal\mukurtu_design\HeaderBackground;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which header background a page gets, if any.
 */
#[Group('mukurtu_design')]
class HeaderBackgroundTest extends KernelTestBase {

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
  protected function headerBackground(bool $is_front = FALSE): HeaderBackground {
    $matcher = $this->createMock(PathMatcherInterface::class);
    $matcher->method('isFrontPage')->willReturn($is_front);

    return new HeaderBackground(
      $this->container->get('config.factory'),
      $this->container->get('entity_type.manager'),
      $this->container->get('file_url_generator'),
      $matcher,
    );
  }

  /**
   * Creates a file entity and points the config at it.
   */
  protected function setHeaderImage(): File {
    $file = File::create(['uri' => 'public://header.png', 'status' => 1]);
    $file->save();

    $this->config('mukurtu_design.settings')->set('header.image', (int) $file->id())->save();

    return $file;
  }

  /**
   * A site that has not chosen an image gets no background.
   */
  public function testNoImageMeansNoBackground(): void {
    $this->assertSame([], $this->headerBackground()->resolve());
  }

  /**
   * A configured image is returned with its treatment.
   */
  public function testReturnsTheConfiguredImage(): void {
    $this->setHeaderImage();

    $result = $this->headerBackground()->resolve();

    $this->assertStringEndsWith('/header.png', $result['url']);
    $this->assertSame('light', $result['treatment']);
  }

  /**
   * The front page opt-out suppresses the background only on the front page.
   */
  public function testFrontPageOptOut(): void {
    $this->setHeaderImage();
    $this->config('mukurtu_design.settings')->set('header.show_on_front', FALSE)->save();

    $this->assertSame([], $this->headerBackground(TRUE)->resolve(), 'The front page opts out.');
    $this->assertNotSame([], $this->headerBackground(FALSE)->resolve(), 'Other pages keep the background.');
  }

  /**
   * With the opt-out off, the front page keeps the background.
   */
  public function testFrontPageKeepsBackgroundWhenEnabled(): void {
    $this->setHeaderImage();

    $this->assertNotSame([], $this->headerBackground(TRUE)->resolve());
  }

  /**
   * A deleted file must not fatal, and must not emit a broken url().
   *
   * Nothing stops an admin deleting the file from the files listing, which
   * would otherwise leave a dangling fid in config.
   */
  public function testDanglingFileIdIsIgnored(): void {
    $file = $this->setHeaderImage();
    $file->delete();

    $this->assertSame([], $this->headerBackground()->resolve());
  }

  /**
   * An unrecognised treatment falls back to the readable default.
   */
  public function testUnknownTreatmentFallsBackToLight(): void {
    $this->setHeaderImage();
    $this->config('mukurtu_design.settings')->set('header.text_treatment', 'chartreuse')->save();

    $this->assertSame('light', $this->headerBackground()->resolve()['treatment']);
  }

  /**
   * The result must be cacheable per config and per front-page-ness.
   *
   * Without the url.path.is_front context the front page opt-out would leak
   * one page's answer onto the other.
   */
  public function testCacheability(): void {
    $metadata = $this->headerBackground()->getCacheableMetadata();

    $this->assertContains('config:mukurtu_design.settings', $metadata->getCacheTags());
    $this->assertContains('url.path.is_front', $metadata->getCacheContexts());
  }

}
