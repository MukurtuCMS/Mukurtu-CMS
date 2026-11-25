<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Unit;

use Drupal\mukurtu_design\CustomCssValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which custom CSS is accepted and which is rejected.
 */
#[CoversClass(CustomCssValidator::class)]
#[Group('mukurtu_design')]
class CustomCssValidatorTest extends UnitTestCase {

  /**
   * CSS a site might reasonably write.
   */
  public static function acceptedCss(): array {
    return [
      'empty' => [''],
      'plain rules' => [".site-header { padding: 1rem; }\nh1 > a:hover { color: #222; }"],
      'site path' => ['body { background: url(/sites/default/files/bg.png); }'],
      'quoted relative path' => ['.logo { background-image: url("../images/logo.svg"); }'],
      'data URI' => ['.icon { background: url(data:image/png;base64,iVBORw0KGgo=); }'],
      'SVG data URI with namespace' => [".icon { background: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'%3E%3C/svg%3E\"); }"],
      'encoded namespace quotes' => [".icon { background: url(data:image/svg+xml,%3Csvg%20xmlns=%27http://www.w3.org/2000/svg%27%3E%3C/svg%3E); }"],
      'unicode escape in content' => ['q::before { content: "\201C"; }'],
      'font-face with local source' => ['@font-face { font-family: Local; src: local("Arial"), url(/sites/default/files/f.woff2); }'],
      'media query' => ['@media (min-width: 48rem) { .a { display: flex; } }'],
    ];
  }

  /**
   * Accepted CSS reports no problems.
   */
  #[DataProvider('acceptedCss')]
  public function testAccepted(string $css): void {
    $this->assertSame([], (new CustomCssValidator())->validate($css));
  }

  /**
   * CSS that must be rejected, with the problem it should report.
   */
  public static function rejectedCss(): array {
    return [
      'style breakout' => ['</style><script>alert(1)</script>', CustomCssValidator::MARKUP],
      'escaped less-than' => ['a { content: "\3c"; }', CustomCssValidator::MARKUP],
      'import' => ['@import "/sites/default/files/x.css";', CustomCssValidator::IMPORT],
      'import uppercase' => ['@IMPORT url(x.css);', CustomCssValidator::IMPORT],
      'import with hex escape' => ['@\69mport "x.css";', CustomCssValidator::IMPORT],
      'import with literal escape' => ['@im\port "x.css";', CustomCssValidator::IMPORT],
      'https url' => ['a { background: url(https://example.com/x.png); }', CustomCssValidator::EXTERNAL],
      'quoted http url' => ['a { background: url("http://example.com/x.png"); }', CustomCssValidator::EXTERNAL],
      'protocol-relative' => ['a { background: url(//example.com/x.png); }', CustomCssValidator::EXTERNAL],
      'scheme without slashes' => ['a { background: url(http:example.com/x.png); }', CustomCssValidator::EXTERNAL],
      'image-set string' => ['a { background: image-set("https://example.com/x.png" 1x); }', CustomCssValidator::EXTERNAL],
      'font-face remote' => ['@font-face { font-family: X; src: url(https://example.com/x.woff2); }', CustomCssValidator::EXTERNAL],
      'escaped scheme' => ['a { background: url(ht\74ps://example.com/x.png); }', CustomCssValidator::EXTERNAL],
      'escaped slash' => ['a { background: url(/\2f example.com/x.png); }', CustomCssValidator::EXTERNAL],
      // CSS "\\\\" is two backslashes, which a URL parser reads as "//".
      'backslashes as slashes' => ['a { background: url("\\\\\\\\example.com/x.png"); }', CustomCssValidator::EXTERNAL],
      'tab inside scheme' => ["a { background: url(\"ht\\9 tps://example.com/x.png\"); }", CustomCssValidator::EXTERNAL],
      'namespace exemption only for w3.org' => [".i { background: url(\"data:image/svg+xml,%3Csvg xmlns='http://example.com/'%3E\"); }", CustomCssValidator::EXTERNAL],
      'w3.org outside xmlns' => ['a { background: url(http://www.w3.org/2000/svg); }', CustomCssValidator::EXTERNAL],
    ];
  }

  /**
   * Rejected CSS reports the expected problem.
   */
  #[DataProvider('rejectedCss')]
  public function testRejected(string $css, string $problem): void {
    $this->assertContains($problem, (new CustomCssValidator())->validate($css));
  }

  /**
   * Every problem in the CSS is reported, not only the first.
   */
  public function testReportsEveryProblem(): void {
    $problems = (new CustomCssValidator())->validate('@import url(https://example.com/x.css); </style>');

    $this->assertEqualsCanonicalizing(
      [CustomCssValidator::MARKUP, CustomCssValidator::IMPORT, CustomCssValidator::EXTERNAL],
      $problems
    );
  }

  /**
   * Invalid UTF-8 cannot switch off escape decoding.
   */
  public function testInvalidUtf8StillDecodes(): void {
    $this->assertContains(
      CustomCssValidator::IMPORT,
      (new CustomCssValidator())->validate("@\\69mport \"x.css\"; /* \xC3\x28 */")
    );
  }

}
