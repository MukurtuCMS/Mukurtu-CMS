<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Asserts the permissions a fresh Mukurtu install actually grants.
 *
 * A fresh install builds each role from two sources, and reading only one of
 * them gives a false answer:
 *
 * - config/install/user.role.*.yml, imported by the config installer.
 * - $roles[...] lists in mukurtu_install(), applied with grantPermission().
 *
 * That split has already caused a real bug. mukurtu_core_update_40118() revoked
 * "use text format full_html" from the authenticated role, on the stated
 * reasoning that Full HTML adds object, embed and video tags that an ordinary
 * logged-in user does not need. Its docblock asserted that "the shipped
 * authenticated role has never granted it", which was true of the YAML file and
 * untrue of mukurtu_install(), which granted it programmatically. So the hook
 * fixed every existing site and every fresh install kept getting it, for the
 * whole of 4.0.x.
 *
 * These tests therefore assert the union of both sources, which is what a new
 * site really ends up with. Nine kernel tests that each ran an update hook to
 * check one permission are replaced by this.
 *
 * A pure filesystem and YAML check, so no Drupal bootstrap is needed.
 *
 * @see mukurtu_install()
 */
#[Group('mukurtu')]
class ShippedRolePermissionsTest extends UnitTestCase {

  /**
   * Resolves the profile root from this file's location.
   */
  private static function profileRoot(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Permissions a role receives from its shipped config/install YAML.
   */
  private static function permissionsFromConfig(string $role): array {
    $path = self::profileRoot() . "/config/install/user.role.$role.yml";
    if (!file_exists($path)) {
      return [];
    }

    return Yaml::parseFile($path)['permissions'] ?? [];
  }

  /**
   * Permissions a role receives from mukurtu_install()'s own lists.
   *
   * The lists are flat arrays of single-quoted literals, so they are read
   * straight out of the source. Doing this rather than running the installer is
   * a deliberate trade: mukurtu_install() cannot run in a kernel test, since a
   * kernel test has no active profile, and asserting nothing would be worse
   * than asserting the source of truth textually.
   */
  private static function permissionsFromInstall(string $role): array {
    $source = file_get_contents(self::profileRoot() . '/mukurtu.install');
    if ($source === FALSE) {
      return [];
    }

    $pattern = '/\$roles\[.' . preg_quote($role, '/') . '.\]\s*=\s*\[(.*?)\n  \];/s';
    if (!preg_match($pattern, $source, $block)) {
      return [];
    }

    // Skip commented-out lines so a documented omission is not read as a grant.
    $body = preg_replace('!^\s*//.*$!m', '', $block[1]);
    preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $body, $matches);

    return $matches[1];
  }

  /**
   * Everything a role holds on a fresh install, from both sources.
   */
  private static function effectivePermissions(string $role): array {
    return array_values(array_unique(array_merge(
      self::permissionsFromConfig($role),
      self::permissionsFromInstall($role)
    )));
  }

  /**
   * Sanity check that both readers actually return something.
   *
   * If either silently returned an empty list, every positive assertion below
   * would fail loudly but every negative one would pass vacuously, which is the
   * more dangerous direction.
   */
  public function testBothPermissionSourcesAreReadable(): void {
    $this->assertNotEmpty(self::permissionsFromConfig('mukurtu_manager'), 'Read no permissions from the mukurtu_manager YAML.');
    $this->assertNotEmpty(self::permissionsFromInstall('mukurtu_manager'), 'Read no permissions from mukurtu_install().');
    $this->assertNotEmpty(self::permissionsFromInstall('authenticated'), 'Read no permissions from the authenticated list in mukurtu_install().');
  }

  /**
   * Permissions the Mukurtu Manager role must hold.
   *
   * Each of these was added by its own update hook during 4.0.x, because the
   * role shipped without it and site managers hit the resulting dead end.
   */
  public static function managerPermissionProvider(): \Generator {
    yield 'media overview' => ['access media overview'];
    yield 'site reports' => ['access site reports'];
    yield 'message subscribe' => ['administer message subscribe'];
    yield 'comments' => ['administer comments'];
    yield 'submissions settings' => ['administer mukurtu submissions'];
    yield 'submission review' => ['review mukurtu submissions'];
    yield 'flag content for export' => ['flag export_content'];
    yield 'unflag content for export' => ['unflag export_content'];
    yield 'flag media for export' => ['flag export_media'];
    yield 'unflag media for export' => ['unflag export_media'];
  }

  #[DataProvider('managerPermissionProvider')]
  public function testManagerHoldsPermission(string $permission): void {
    $this->assertContains(
      $permission,
      self::effectivePermissions('mukurtu_manager'),
      "The Mukurtu Manager role does not hold '$permission' on a fresh install."
    );
  }

  /**
   * Authenticated users get Basic HTML but not Full HTML.
   *
   * Full HTML adds object, embed and video tags over Basic HTML, which already
   * permits iframes and media embeds. This is asserted against both sources
   * because checking only the YAML is what let the gap survive 4.0.x.
   */
  public function testAuthenticatedGetsBasicHtmlButNotFullHtml(): void {
    $effective = self::effectivePermissions('authenticated');

    $this->assertContains('use text format basic_html', $effective, 'Logged-in users need Basic HTML to author anything.');
    $this->assertNotContains(
      'use text format full_html',
      $effective,
      'Every logged-in user would be able to use Full HTML, which grants object, embed and video tags. Check mukurtu_install() as well as user.role.authenticated.yml.'
    );
  }

  /**
   * Logged-in users see the administration theme.
   *
   * Mukurtu puts ordinary contributors through admin-themed forms for content
   * creation, so without this a logged-in contributor gets those forms rendered
   * in the front-end theme, which was never designed for them.
   */
  public function testAuthenticatedSeesTheAdministrationTheme(): void {
    $this->assertContains(
      'view the administration theme',
      self::effectivePermissions('authenticated'),
      'Logged-in users would see content forms in the front-end theme.'
    );
  }

  /**
   * Anonymous users get no text format at all.
   */
  public function testAnonymousGetsNoTextFormat(): void {
    foreach (self::effectivePermissions('anonymous') as $permission) {
      $this->assertStringStartsNotWith(
        'use text format',
        $permission,
        "Anonymous users hold '$permission'."
      );
    }
  }

  /**
   * The submission reviewer role ships with review access.
   *
   * The role is shipped as config so a fresh install has it, and notify_uids
   * members are auto-enrolled into it.
   */
  public function testSubmissionReviewerRoleShipsWithReviewAccess(): void {
    $reviewer = self::permissionsFromConfig('mukurtu_submission_reviewer');
    $this->assertNotEmpty($reviewer, 'The submission reviewer role is not shipped as config.');
    $this->assertContains('review mukurtu submissions', $reviewer);
    $this->assertNotContains(
      'administer mukurtu submissions',
      $reviewer,
      'Reviewers must not also be able to administer the submission settings.'
    );
  }

  /**
   * Protocol roles that can view media, and the two that deliberately cannot.
   *
   * Media access inside a protocol is what makes shared items visible to its
   * members. Affiliates and plain members are intentionally excluded, so both
   * directions are asserted.
   */
  public static function protocolRoleProvider(): \Generator {
    foreach (['community_record_steward', 'contributor', 'curator', 'language_contributor', 'language_steward', 'protocol_steward'] as $role) {
      yield "$role can view media" => [$role, TRUE];
    }
    foreach (['protocol_affiliate', 'protocol_member'] as $role) {
      yield "$role cannot view media" => [$role, FALSE];
    }
  }

  #[DataProvider('protocolRoleProvider')]
  public function testProtocolRoleMediaAccess(string $role, bool $expected): void {
    $path = self::profileRoot() . "/config/install/og.og_role.protocol-protocol-$role.yml";
    $this->assertFileExists($path, "The $role protocol role is no longer shipped.");

    $permissions = Yaml::parseFile($path)['permissions'] ?? [];

    if ($expected) {
      $this->assertContains('view media', $permissions, "The $role protocol role can no longer view media.");
    }
    else {
      $this->assertNotContains('view media', $permissions, "The $role protocol role has gained media access.");
    }
  }

  /**
   * The stock content editor role loses the article permissions.
   *
   * standard_install() creates content_editor with article permissions, and
   * mukurtu_install() revokes them because Mukurtu manages article access
   * through its own roles. Asserted against the source, since the revocation
   * happens in code rather than config.
   */
  public function testContentEditorLosesArticlePermissions(): void {
    $source = file_get_contents(self::profileRoot() . '/mukurtu.install');
    $this->assertIsString($source);

    $this->assertMatchesRegularExpression(
      '/\$article_permissions\s*=\s*\[/',
      $source,
      'mukurtu_install() no longer builds the article permission list.'
    );
    $this->assertStringContainsString(
      "revokePermission(\$permission)",
      $source,
      'mukurtu_install() no longer revokes the article permissions from content_editor.'
    );

    preg_match('/\$article_permissions\s*=\s*\[(.*?)\];/s', $source, $block);
    preg_match_all("/'([^']+)'/", $block[1] ?? '', $matches);

    foreach (['create article content', 'delete article revisions', 'delete own article content', 'edit own article content'] as $permission) {
      $this->assertContains($permission, $matches[1], "'$permission' is no longer revoked from content_editor.");
    }
  }

}
