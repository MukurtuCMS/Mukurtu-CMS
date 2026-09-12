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
   *
   * Defaults to the front page, since that is the only place a background
   * applies at all.
   */
  protected function pageBackground(bool $is_front = TRUE): PageBackground {
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
   * Interior pages get no background, however it is configured.
   *
   * Behind an interior page the image sits under content laid out for a plain
   * ground and mostly shows as empty space. The Plateau Peoples' Web Portal,
   * the reference for this feature, carries no background on its interior
   * pages either.
   */
  public function testInteriorPagesGetNoBackground(): void {
    $this->setBackgroundImage();

    $this->assertNotSame([], $this->pageBackground(TRUE)->resolve(), 'The front page has it.');
    $this->assertSame([], $this->pageBackground(FALSE)->resolve(), 'Interior pages do not.');
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
   * Creates a header image file and points the config at it.
   */
  protected function setHeaderImage(): File {
    $file = File::create(['uri' => 'public://header.png', 'status' => 1]);
    $file->save();

    $this->config('mukurtu_design.settings')->set('header.image', (int) $file->id())->save();

    return $file;
  }

  /**
   * The header image applies on interior pages, where the page one does not.
   */
  public function testHeaderImageAppliesToInteriorPages(): void {
    $this->setHeaderImage();

    $result = $this->pageBackground(FALSE)->resolveHeader();

    $this->assertStringEndsWith('/header.png', $result['url']);
    $this->assertSame('light', $result['treatment']);
  }

  /**
   * With no page background, the header image covers the front page too.
   */
  public function testHeaderImageAppliesToFrontPageWhenNoPageBackground(): void {
    $this->setHeaderImage();

    $this->assertNotSame([], $this->pageBackground(TRUE)->resolveHeader());
  }

  /**
   * The page background wins on the front page.
   *
   * It already covers the header, so a header image there would sit on top of
   * it. This is the rule that lets a site use one tall image on the home page
   * and a separate short one everywhere else, which is what the Plateau
   * Peoples' Web Portal does with bg.jpg and bg_interior.jpg.
   */
  public function testPageBackgroundSuppressesTheHeaderImageOnTheFrontPage(): void {
    $this->setBackgroundImage();
    $this->setHeaderImage();

    $this->assertSame([], $this->pageBackground(TRUE)->resolveHeader(), 'Front page: page background only.');
    $this->assertNotSame([], $this->pageBackground(TRUE)->resolve(), 'Front page still has the page background.');
    $this->assertNotSame([], $this->pageBackground(FALSE)->resolveHeader(), 'Interior pages keep the header image.');
    $this->assertSame([], $this->pageBackground(FALSE)->resolve(), 'Interior pages have no page background.');
  }

  /**
   * No header image configured means no header background.
   */
  public function testNoHeaderImageMeansNoHeaderBackground(): void {
    $this->assertSame([], $this->pageBackground(FALSE)->resolveHeader());
  }

  /**
   * A deleted header file must not fatal.
   */
  public function testDanglingHeaderFileIsIgnored(): void {
    $file = $this->setHeaderImage();
    $file->delete();

    $this->assertSame([], $this->pageBackground(FALSE)->resolveHeader());
  }

  /**
   * The header treatment is independent of the page one.
   */
  public function testHeaderTreatmentIsSeparate(): void {
    $this->setHeaderImage();
    $this->config('mukurtu_design.settings')
      ->set('background.text_treatment', 'light')
      ->set('header.text_treatment', 'dark')
      ->save();

    $this->assertSame('dark', $this->pageBackground(FALSE)->resolveHeader()['treatment']);
  }

  /**
   * An unrecognised header treatment falls back to the readable default.
   */
  public function testUnknownHeaderTreatmentFallsBackToLight(): void {
    $this->setHeaderImage();
    $this->config('mukurtu_design.settings')->set('header.text_treatment', 'chartreuse')->save();

    $this->assertSame('light', $this->pageBackground(FALSE)->resolveHeader()['treatment']);
  }

  /**
   * The result must be cacheable per config and per front-page-ness.
   *
   * Without the url.path.is_front context the front page's answer would be
   * cached and served for interior pages, and the other way round.
   */
  public function testCacheability(): void {
    $metadata = $this->pageBackground()->getCacheableMetadata();

    $this->assertContains('config:mukurtu_design.settings', $metadata->getCacheTags());
    $this->assertContains('url.path.is_front', $metadata->getCacheContexts());
  }

}
