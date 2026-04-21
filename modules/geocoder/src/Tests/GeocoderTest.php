<?php

namespace Drupal\geocoder\Tests;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the Geocoder module.
 *
 * @group Geocoder
 */
class GeocoderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['geocoder'];

  /**
   * {@inheritdoc}
   */
  private $user;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {

    parent::setUp();
    $this->user = $this->DrupalCreateUser([
      'administer site configuration',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function testMobileJsRedirectPageExists() {

    $this->drupalLogin($this->user);

    // Generator test:
    $this->drupalGet('admin/config/system/geocoder');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function testConfigForm() {

    // Test form structure.
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/config/system/geocoder');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('cache');

    $this->submitForm([], 'Save configuration');

    $this->drupalGet('admin/config/system/geocoder');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('cache');

  }

}
