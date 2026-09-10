<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Pins the shipped default value of settings a fresh site depends on.
 *
 * Each of these was corrected at some point during 4.0.x by an update hook,
 * with a kernel test that ran the hook and asserted the result. The hooks are
 * gone in 4.0.1, so the shipped default is now the only thing standing between
 * a new site and the behaviour those hooks were written to fix.
 *
 * Several are security or privacy relevant and are called out individually
 * below rather than left in the general table, because getting them wrong on a
 * fresh install is worse than cosmetic: comments open to the public without
 * moderation, or a consent-gated third party script loading by default.
 *
 * A pure filesystem and YAML check, so no Drupal bootstrap is needed.
 */
#[Group('mukurtu')]
class ShippedSettingsDefaultsTest extends UnitTestCase {

  /**
   * Shipped scalar defaults, as file => [dot path => expected value].
   */
  private static function expectations(): array {
    return [
      // Limits how many facet links a crawler can follow before the bot
      // blocker steps in. Raising it re-opens the crawl explosion.
      'modules/mukurtu_bot_protection/config/install/facet_bot_blocker.settings.yml' => [
        'facets_bot_blocker_limit' => 4,
      ],
      // Media downloads stay off until a site opts in, since what may be
      // downloaded is a cultural protocol decision, not a default.
      'modules/mukurtu_media/config/install/mukurtu_media.settings.yml' => [
        'mukurtu_media_download_enabled' => FALSE,
      ],
      // How long a project deleted upstream is tolerated before removal, and
      // how many consecutive fetch failures count as gone rather than a blip.
      'modules/mukurtu_local_contexts/config/install/mukurtu_local_contexts.settings.yml' => [
        'deleted_project_grace_period' => 2419200,
        'deleted_project_min_consecutive_failures' => 4,
      ],
      // ALTCHA renders inside the login form, where the vendor logo and
      // footer are noise.
      'config/install/altcha.settings.yml' => [
        'hide_logo' => TRUE,
        'hide_footer' => TRUE,
      ],
      // An hour, rather than core's default of every request.
      'config/install/automated_cron.settings.yml' => [
        'interval' => 3600,
      ],
      'modules/mukurtu_core/config/install/mukurtu_core.not_found.yml' => [
        'title' => 'Page Not Found',
      ],
    ];
  }

  /**
   * Resolves the profile root from this file's location.
   */
  private function profileRoot(): string {
    $root = dirname(__DIR__, 3);
    $this->assertFileExists("$root/mukurtu.info.yml", 'Sanity check: resolved profile root is wrong.');
    return $root;
  }

  /**
   * Reads a dot-delimited path out of a parsed YAML file.
   */
  private function shippedValue(string $file, string $path): mixed {
    $data = Yaml::parseFile($this->profileRoot() . '/' . $file);
    foreach (explode('.', $path) as $segment) {
      $this->assertIsArray($data, "$file: '$path' runs past a scalar.");
      $this->assertArrayHasKey($segment, $data, "$file does not ship '$path'.");
      $data = $data[$segment];
    }
    return $data;
  }

  public static function settingProvider(): \Generator {
    foreach (self::expectations() as $file => $paths) {
      foreach ($paths as $path => $expected) {
        yield "$file: $path" => [$file, $path, $expected];
      }
    }
  }

  #[DataProvider('settingProvider')]
  public function testShippedDefault(string $file, string $path, mixed $expected): void {
    $this->assertSame(
      $expected,
      $this->shippedValue($file, $path),
      "$file ships a different default for '$path' than the one a fresh site needs."
    );
  }

  /**
   * Comments ship closed, moderated, and attributable.
   *
   * All three together are what keeps a brand new public site from being an
   * open spam target before anyone has configured it, so they are asserted as
   * a set rather than individually.
   */
  public function testCommentsShipLockedDown(): void {
    $file = 'modules/mukurtu_protocol/config/install/mukurtu_protocol.comment_settings.yml';

    $this->assertFalse($this->shippedValue($file, 'site_comments_enabled'), 'Comments must be off on a fresh site.');
    $this->assertTrue($this->shippedValue($file, 'site_comments_require_approval'), 'Comments must require approval when a site turns them on.');
    $this->assertTrue($this->shippedValue($file, 'anonymous_comments_require_email'), 'Anonymous comments must require an email address.');
  }

  /**
   * Klaro gates Stripe rather than loading it.
   *
   * Stripe is a third party script subject to consent. Shipping it enabled, or
   * as a required service the visitor cannot decline, would load it before any
   * consent decision.
   */
  public function testStripeIsConsentGatedAndOff(): void {
    $file = 'config/install/klaro.klaro_app.stripe.yml';

    $this->assertFalse($this->shippedValue($file, 'status'), 'The Stripe Klaro app must ship disabled.');
    $this->assertFalse($this->shippedValue($file, 'default'), 'Stripe must not be opted in by default.');
    $this->assertFalse($this->shippedValue($file, 'required'), 'Stripe must be declinable.');
  }

  /**
   * Toastify is registered with Klaro as a required service.
   *
   * Klaro blocks external resources it does not know about. Toastify serves the
   * notification toasts used across the admin UI, including inside Layout
   * Builder, so an unregistered Toastify breaks editing rather than merely
   * suppressing a nicety.
   */
  public function testToastifyIsRegisteredWithKlaro(): void {
    $file = 'modules/mukurtu_gin_custom/config/install/klaro.klaro_app.toastify.yml';

    $this->assertTrue($this->shippedValue($file, 'status'), 'The Toastify Klaro app must ship enabled.');
    $this->assertTrue($this->shippedValue($file, 'required'), 'Toastify is required for the admin UI to function.');
    $this->assertContains(
      'cdn.jsdelivr.net/npm/toastify-js',
      $this->shippedValue($file, 'javascripts'),
      'Toastify must declare the CDN host Klaro would otherwise block.'
    );
  }

  /**
   * View counting covers every entity type Mukurtu lets people browse.
   *
   * A type missing here records no views, which shows up much later as an
   * empty "most viewed" listing rather than as an error.
   */
  public function testViewCountingCoversBrowsableEntityTypes(): void {
    $types = $this->shippedValue('config/install/visitors.config.yml', 'counter.entity_types');

    foreach (['node', 'media', 'community', 'protocol', 'personal_collection', 'multipage_item', 'taxonomy_term'] as $type) {
      $this->assertContains($type, $types, "Visitor view counting does not cover '$type'.");
    }
  }

  /**
   * The custom 404 path resolves to a route that actually exists.
   *
   * mukurtu_install() points system.site page.404 at /mukurtu/not-found. If
   * that route is ever renamed the site's 404 handler silently 404s itself,
   * so the two are asserted against each other.
   */
  public function testCustomNotFoundPathHasARoute(): void {
    $root = $this->profileRoot();

    $install = file_get_contents("$root/mukurtu.install");
    $this->assertStringContainsString(
      "->set('page.404', '/mukurtu/not-found')",
      $install,
      'mukurtu_install() no longer sets the custom 404 path.'
    );

    $routes = Yaml::parseFile("$root/modules/mukurtu_core/mukurtu_core.routing.yml");
    $paths = array_column($routes, 'path');
    $this->assertContains(
      '/mukurtu/not-found',
      $paths,
      'The /mukurtu/not-found route is gone, so the configured 404 page would itself 404.'
    );
  }

}
