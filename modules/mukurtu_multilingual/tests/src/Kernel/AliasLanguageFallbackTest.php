<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_multilingual\PathAlias\FallbackAliasRepository;
use Drupal\path_alias\Entity\PathAlias;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that untranslated content keeps its readable alias in every language.
 *
 * Runs through the real path_alias.manager service, so it also proves
 * mukurtu_multilingual.services.yml actually decorates the repository. User
 * paths stand in for node paths so the node module isn't needed.
 */
#[CoversClass(FallbackAliasRepository::class)]
#[Group('mukurtu_multilingual')]
class AliasLanguageFallbackTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'language',
    'path_alias',
    'mukurtu_multilingual',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('path_alias');
    $this->installConfig(['language']);
    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();
    // AliasManager skips paths whose first segment isn't a known route root,
    // and only modules with routes (user, not node, here) register one.
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Creates an alias and clears the alias manager's static caches.
   */
  private function createAlias(string $path, string $alias, string $langcode): void {
    PathAlias::create(['path' => $path, 'alias' => $alias, 'langcode' => $langcode])->save();
    $this->container->get('path_alias.manager')->cacheClear();
  }

  /**
   * The repository service is the Mukurtu decorator.
   */
  public function testServiceIsDecorated(): void {
    $this->assertInstanceOf(FallbackAliasRepository::class, $this->container->get('path_alias.repository'));
  }

  /**
   * An English-only alias is used for French URLs, both ways.
   */
  public function testFallsBackToDefaultLanguageAlias(): void {
    $this->createAlias('/user/19', '/digital-heritage/tomorrow-first-dawn', 'en');
    $manager = $this->container->get('path_alias.manager');

    $this->assertSame('/digital-heritage/tomorrow-first-dawn', $manager->getAliasByPath('/user/19', 'fr'));
    $this->assertSame('/user/19', $manager->getPathByAlias('/digital-heritage/tomorrow-first-dawn', 'fr'));
  }

  /**
   * Content created in a non-default language still gets an alias elsewhere.
   */
  public function testFallsBackToAnyLanguageAlias(): void {
    $this->createAlias('/user/20', '/digital-heritage/le-corbeau', 'fr');
    $manager = $this->container->get('path_alias.manager');

    $this->assertSame('/digital-heritage/le-corbeau', $manager->getAliasByPath('/user/20', 'es'));
    $this->assertSame('/user/20', $manager->getPathByAlias('/digital-heritage/le-corbeau', 'es'));
  }

  /**
   * A real alias in the requested language beats the fallback.
   */
  public function testOwnLanguageAliasWins(): void {
    $this->createAlias('/user/21', '/digital-heritage/crow-and-fox', 'en');
    $this->createAlias('/user/21', '/digital-heritage/le-corbeau-et-le-renard', 'fr');
    $manager = $this->container->get('path_alias.manager');

    $this->assertSame('/digital-heritage/le-corbeau-et-le-renard', $manager->getAliasByPath('/user/21', 'fr'));
    $this->assertSame('/digital-heritage/crow-and-fox', $manager->getAliasByPath('/user/21', 'en'));
  }

  /**
   * A path with no alias in any language is returned unchanged.
   */
  public function testUnaliasedPathIsUnchanged(): void {
    $this->createAlias('/user/22', '/something-else', 'en');
    $manager = $this->container->get('path_alias.manager');

    $this->assertSame('/user/23', $manager->getAliasByPath('/user/23', 'fr'));
    $this->assertSame('/no-such-alias', $manager->getPathByAlias('/no-such-alias', 'fr'));
  }

  /**
   * Looking up a translation's own alias never borrows another language's.
   *
   * Core's path field and pathauto use lookupBySystemPath() to find the alias
   * belonging to one translation. A fallback there would make saving the
   * French translation edit the English alias.
   */
  public function testSystemPathLookupStaysExact(): void {
    $this->createAlias('/user/24', '/digital-heritage/tomorrow-first-dawn', 'en');
    $repository = $this->container->get('path_alias.repository');

    $this->assertNull($repository->lookupBySystemPath('/user/24', 'fr'));
    $this->assertSame('en', $repository->lookupBySystemPath('/user/24', 'en')['langcode']);
  }

}
