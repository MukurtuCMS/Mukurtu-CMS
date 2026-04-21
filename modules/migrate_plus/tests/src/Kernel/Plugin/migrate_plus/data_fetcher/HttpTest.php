<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_plus\Kernel\Plugin\migrate_plus\data_fetcher;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the http data_fetcher plugin.
 */
#[Group('migrate_plus')]
#[RunTestsInSeparateProcesses]
final class HttpTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['migrate_plus', 'migrate'];

  /**
   * Test http headers option.
   *
   * @dataProvider headerDataProvider
   */
  #[DataProvider('headerDataProvider')]
  public function testHttpHeaders(array $definition, array $expected, array $preSeed = []): void {
    $http = new Http($definition, 'http', [], $this->container->get('http_client'), $this->container->get('plugin.manager.migrate_plus.authentication'));
    $this->assertEquals($expected, $http->getRequestHeaders());
  }

  /**
   * Provides multiple test cases for the testHttpHeaders method.
   *
   * @return array
   *   The test cases
   */
  public static function headerDataProvider(): array {
    return [
      'dummy headers specified' => [
        'definition' => [
          'headers' => [
            'Accept' => 'application/json',
            'User-Agent' => 'Internet Explorer 6',
            'Authorization-Key' => 'secret',
            'Arbitrary-Header' => 'fooBarBaz',
          ],
        ],
        'expected' => [
          'Accept' => 'application/json',
          'User-Agent' => 'Internet Explorer 6',
          'Authorization-Key' => 'secret',
          'Arbitrary-Header' => 'fooBarBaz',
        ],
      ],
      'no headers specified' => [
        'definition' => [
          'no_headers_here' => 'foo',
        ],
        'expected' => [],
      ],
    ];
  }

}
