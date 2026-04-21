<?php

namespace Drupal\Tests\features\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\config_update\ConfigDiffInterface;
use Drupal\config_update\ConfigRevertInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\features\ConfigurationItem;
use Drupal\features\Entity\FeaturesBundle;
use Drupal\features\FeaturesAssignerInterface;
use Drupal\features\FeaturesBundleInterface;
use Drupal\features\FeaturesExtensionStoragesInterface;
use Drupal\features\FeaturesManager;
use Drupal\features\FeaturesManagerInterface;
use Drupal\features\Package;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass Drupal\features\FeaturesManager
 * @group features
 */
class FeaturesManagerTest extends UnitTestCase {
  /**
   * The name of the install profile.
   *
   * @var string
   *   The name of the install profile.
   */
  const PROFILE_NAME = 'my_profile';
  use ProphecyTrait;

  /**
   * The core extension resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $extensionPathResolver;

  /**
   * The feature manager interface.
   *
   * @var \Drupal\features\FeaturesManagerInterface
   */
  protected $featuresManager;

  /**
   * The entity type manager object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The storage interface object.
   *
   * @var \Drupal\Core\Config\StorageInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configStorage;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The config manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The extension.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configReverter;

  /**
   * The module extension list mock.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleExtensionList;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->setParameter('app.root', $this->root);
    // Since in Drupal 8.3 the "\Drupal::installProfile()" was introduced
    // then we have to spoof a value for the "install_profile" parameter
    // because it will be used by "ExtensionInstallStorage" class, which
    // extends the "FeaturesInstallStorage".
    // @see \Drupal\features\FeaturesConfigInstaller::__construct()
    $container->setParameter('install_profile', '');
    \Drupal::setContainer($container);

    $entity_type = $this->createMock('\Drupal\Core\Config\Entity\ConfigEntityTypeInterface');
    $entity_type->expects($this->any())
      ->method('getConfigPrefix')
      ->willReturn('custom');
    $entity_type->expects($this->any())
      ->method('getProvider')
      ->willReturn('my_module');
    $this->entityTypeManager = $this->createMock('\Drupal\Core\Entity\EntityTypeManagerInterface');
    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->willReturn($entity_type);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configStorage = $this->createMock(StorageInterface::class);
    $this->configManager = $this->createMock(ConfigManagerInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->extensionPathResolver = $this->createMock(ExtensionPathResolver::class);
    // getModuleList should return an array of extension objects.
    // but we just need  isset($module_list[$provider]) for
    // ::getConfigDependency() and ::assignInterPackageDependencies().
    $this->moduleHandler->expects($this->any())
      ->method('getModuleList')
      ->willReturn([
        'my_module' => TRUE,
        'example' => TRUE,
        'example3' => TRUE,
        'my_feature' => TRUE,
        'my_other_feature' => TRUE,
        'package' => TRUE,
        'package2' => TRUE,
        'package3' => TRUE,
        'giraffe_package' => TRUE,
        'giraffe_package2' => TRUE,
        'giraffe_package3' => TRUE,
      ]);
    $this->configReverter = $this->createMock(ConfigRevertInterface::class);
    $this->configReverter->expects($this->any())
      ->method('import')
      ->willReturn(TRUE);
    $this->configReverter->expects($this->any())
      ->method('revert')
      ->willReturn(TRUE);
    $this->moduleExtensionList = $this->createMock(ModuleExtensionList::class);
    $this->moduleExtensionList->expects($this->any())
      ->method('getPath')
      ->willReturn('some/path');
    $this->moduleExtensionList->expects($this->any())
      ->method('getExtensionInfo')
      ->willReturn([]);
    $this->featuresManager = new FeaturesManager($this->root, $this->entityTypeManager, $this->configFactory, $this->configStorage, $this->configManager, $this->moduleHandler, $this->configReverter, $this->moduleExtensionList, $this->extensionPathResolver);
  }

  protected function setupVfsWithTestFeature() {
    vfsStream::setup('drupal');
    \Drupal::getContainer()->setParameter('app.root', 'vfs://drupal');
    vfsStream::create([
      'modules' => [
        'test_feature' => [
          'test_feature.info.yml' => <<<EOT
name: Test feature
type: module
core_version_requirement: "^10 || ^11"
description: test description
EOT
          ,
          'test_feature.features.yml' => <<<EOT
bundle: test
excluded:
  - system.theme
required: true
EOT
          ,
        ],
      ],
    ]);
  }

  /**
   * @covers ::getActiveStorage
   */
  public function testGetActiveStorage() {
    $this->assertInstanceOf('\Drupal\Core\Config\StorageInterface', $this->featuresManager->getActiveStorage());
  }

  /**
   * @covers ::getExtensionStorages
   */
  public function testGetExtensionStorages() {
    $this->assertInstanceOf('\Drupal\features\FeaturesExtensionStoragesInterface', $this->featuresManager->getExtensionStorages());
  }

  /**
   * @covers ::getFullName
   * @dataProvider providerTestGetFullName
   */
  public function testGetFullName($type, $name, $expected) {
    $this->assertEquals($this->featuresManager->getFullName($type, $name), $expected);
  }

  /**
   * Data provider for ::testGetFullName().
   */
  public static function providerTestGetFullName() {
    return [
      [NULL, 'name', 'name'],
      [FeaturesManagerInterface::SYSTEM_SIMPLE_CONFIG, 'name', 'name'],
      ['custom', 'name', 'custom.name'],
    ];
  }

  /**
   * @covers ::getPackage
   * @covers ::getPackages
   * @covers ::reset
   * @covers ::setPackages
   */
  public function testPackages() {
    $packages = ['foo' => 'bar'];
    $this->featuresManager->setPackages($packages);
    $this->assertEquals($packages, $this->featuresManager->getPackages());
    $this->assertEquals('bar', $this->featuresManager->getPackage('foo'));
    $this->featuresManager->reset();
    $this->assertEquals([], $this->featuresManager->getPackages());
    $this->assertNull($this->featuresManager->getPackage('foo'));
  }

  /**
   * @covers ::setConfigCollection
   * @covers ::getConfigCollection
   */
  public function testConfigCollection() {
    $config = ['config' => new ConfigurationItem('', [])];
    $this->featuresManager->setConfigCollection($config);
    $this->assertEquals($config, $this->featuresManager->getConfigCollection());
  }

  /**
   * @covers ::setPackage
   * @covers ::getPackage
   */
  public function testSetPackage() {
    $package = new Package('foo');
    $this->featuresManager->setPackage($package);
    $this->assertEquals($package, $this->featuresManager->getPackage('foo'));
  }

  /**
   * @covers ::filterPackages
   */
  public function testGetPackages() {
    $packages = [
      'package' => new Package('package', [
        'bundle' => '',
        'status' => FeaturesManagerInterface::STATUS_NO_EXPORT,
      ]),
      'package2' => new Package('package2', [
        'bundle' => '',
        'status' => FeaturesManagerInterface::STATUS_UNINSTALLED,
      ]),
      'package3' => new Package('package3', [
        'bundle' => 'my_bundle',
        'status' => FeaturesManagerInterface::STATUS_NO_EXPORT,
      ]),
      'package4' => new Package('package4', [
        'bundle' => 'my_bundle',
        'status' => FeaturesManagerInterface::STATUS_UNINSTALLED,
      ]),
    ];

    // Filter for the default bundle.
    $filtered_packages = $this->featuresManager->filterPackages($packages, FeaturesBundleInterface::DEFAULT_BUNDLE);
    $this->assertEquals(['package', 'package2'], array_keys($filtered_packages));

    // Filter for a custom bundle.
    $filtered_packages = $this->featuresManager->filterPackages($packages, 'my_bundle');
    $this->assertEquals(['package3', 'package4'], array_keys($filtered_packages));

    // Filter for a non-matching bundle.
    $filtered_packages = $this->featuresManager->filterPackages($packages, 'some_bundle');
    $this->assertEquals([], array_keys($filtered_packages));

    // Filter for the default bundle removing only exported.
    $filtered_packages = $this->featuresManager->filterPackages($packages, FeaturesBundleInterface::DEFAULT_BUNDLE, TRUE);
    $this->assertEquals(['package'], array_keys($filtered_packages));

    // Filter for a custom bundle removing only exported.
    $filtered_packages = $this->featuresManager->filterPackages($packages, 'my_bundle', TRUE);
    $this->assertEquals(['package3'], array_keys($filtered_packages));

    // Filter for a non-matching bundle removing only exported.
    $filtered_packages = $this->featuresManager->filterPackages($packages, 'some_bundle', TRUE);
    $this->assertEquals([], array_keys($filtered_packages));
  }

  /**
   * {@inheritDoc}
   */
  protected function getAssignInterPackageDependenciesConfigCollection() {
    $config_collection = [];
    $config_collection['example.config'] = (new ConfigurationItem('example.config', [
      'dependencies' => [
        'config' => [
          'example.config2',
          'example.config3',
          'example.config4',
          'example.config5',
          'example.config6',
          'example.config7',
        ],
      ],
    ]))
      ->setSubdirectory(InstallStorage::CONFIG_INSTALL_DIRECTORY)
      ->setPackage('package');
    $config_collection['example.config2'] = (new ConfigurationItem('example.config2', [
      'dependencies' => [],
    ]))
      ->setSubdirectory(InstallStorage::CONFIG_INSTALL_DIRECTORY)
      ->setPackage('package2')
      ->setProvider('my_feature');
    $config_collection['example.config3'] = (new ConfigurationItem('example.config3', [
      'dependencies' => [],
    ]))
      ->setSubdirectory(InstallStorage::CONFIG_INSTALL_DIRECTORY)
      ->setProvider('my_other_feature');
    $config_collection['example.config4'] = (new ConfigurationItem('example.config3', [
      'dependencies' => [],
    ]))
      ->setSubdirectory(InstallStorage::CONFIG_INSTALL_DIRECTORY)
      ->setProvider(static::PROFILE_NAME);
    $config_collection['example.config5'] = (new ConfigurationItem('example.config5', [
      'dependencies' => [],
    ]))
      ->setSubdirectory(InstallStorage::CONFIG_OPTIONAL_DIRECTORY)
      ->setPackage('package3');
    $config_collection['example.config6'] = (new ConfigurationItem('example.config6', [
      'dependencies' => [],
    ]))
      ->setSubdirectory(InstallStorage::CONFIG_INSTALL_DIRECTORY)
      ->setProvider('my_uninstalled_feature');
    $config_collection['example.config7'] = (new ConfigurationItem('example.config7', [
      'dependencies' => [],
    ]))
      ->setSubdirectory(InstallStorage::CONFIG_INSTALL_DIRECTORY)
      ->setProvider('package4');

    return $config_collection;
  }

  /**
   * @covers ::assignInterPackageDependencies
   */
  public function testAssignInterPackageDependenciesWithoutBundle() {
    $assigner = $this->prophesize(FeaturesAssignerInterface::class);
    $bundle = $this->prophesize(FeaturesBundleInterface::class);
    // Provide a bundle without any prefix.
    $bundle->getFullName('package')->willReturn('package');
    $bundle->getFullName('package2')->willReturn('package2');
    $bundle->getFullName('package3')->willReturn('package3');
    $bundle->getFullName('package4')->willReturn('package4');
    $bundle->isDefault()->willReturn(TRUE);
    $assigner->getBundle()->willReturn($bundle->reveal());
    // Use the wrapper because we need ::drupalGetProfile().
    $features_manager = new TestFeaturesManager($this->root, $this->entityTypeManager, $this->configFactory, $this->configStorage, $this->configManager, $this->moduleHandler, $this->configReverter, $this->moduleExtensionList, $this->extensionPathResolver);
    $features_manager->setAssigner($assigner->reveal());

    $features_manager->setConfigCollection($this->getAssignInterPackageDependenciesConfigCollection());

    $packages = [
      'package' => new Package('package', [
        'config' => ['example.config', 'example.config3'],
        'dependencies' => [],
        'bundle' => '',
      ]),
      'package2' => new Package('package2', [
        'config' => ['example.config2'],
        'dependencies' => [],
        'bundle' => '',
      ]),
      'package3' => new Package('package3', [
        'config' => ['example.config5'],
        'dependencies' => [],
        'bundle' => '',
      ]),
      'package4' => new Package('package4', [
        'config' => ['example.config7'],
        'dependencies' => [],
        'bundle' => '',
      ]),
    ];

    $features_manager->setPackages($packages);
    // Dependencies require the full package names.
    $package_names = array_keys($packages);
    $features_manager->setPackageBundleNames($bundle->reveal(), $package_names);
    $packages = $features_manager->getPackages();
    $features_manager->assignInterPackageDependencies($bundle->reveal(), $packages);
    // example.config3 has a providing_feature but no assigned package.
    // my_package2 provides configuration required by configuration in
    // my_package.
    // Because package assignments take precedence over providing_feature ones,
    // package2 should have been assigned rather than my_feature.
    // Because it is assigned to the InstallStorage::CONFIG_OPTIONAL_DIRECTORY
    // subdirectory, example.config5 does not create a dependency on its
    // providing feature, package3.
    // Because it's provided by an uninstalled module, example.config6 doesn't
    // create a dependency on my_uninstalled_feature.
    // Because it's provided by an uninstalled module, example.config7 doesn't
    // create a dependency on package4.
    $this->assertEquals(['my_other_feature', 'package2'], $packages['package']->getDependencies());
    $this->assertEquals([], $packages['package2']->getDependencies());
  }

  /**
   * @covers ::assignInterPackageDependencies
   */
  public function testAssignInterPackageDependenciesWithBundle() {
    $assigner = $this->prophesize(FeaturesAssignerInterface::class);
    $bundle = $this->prophesize(FeaturesBundleInterface::class);
    // Provide a bundle without any prefix.
    $bundle->getFullName('package')->willReturn('giraffe_package');
    $bundle->getFullName('package2')->willReturn('giraffe_package2');
    $bundle->getFullName('package3')->willReturn('giraffe_package3');
    $bundle->getFullName('package4')->willReturn('giraffe_package4');
    $bundle->getFullName('giraffe_package')->willReturn('giraffe_package');
    $bundle->getFullName('giraffe_package2')->willReturn('giraffe_package2');
    $bundle->isDefault()->willReturn(FALSE);
    $bundle->getMachineName()->willReturn('giraffe');
    $assigner->getBundle('giraffe')->willReturn($bundle->reveal());
    // Use the wrapper because we need ::drupalGetProfile().
    $features_manager = new TestFeaturesManager($this->root, $this->entityTypeManager, $this->configFactory, $this->configStorage, $this->configManager, $this->moduleHandler, $this->configReverter, $this->moduleExtensionList, $this->extensionPathResolver);
    $features_manager->setAssigner($assigner->reveal());
    $features_manager->setConfigCollection($this->getAssignInterPackageDependenciesConfigCollection());

    $packages = [
      'package' => new Package('package', [
        'config' => ['example.config'],
        'dependencies' => [],
        'bundle' => 'giraffe',
      ]),
      'package2' => new Package('package2', [
        'config' => ['example.config2'],
        'dependencies' => [],
        'bundle' => 'giraffe',
      ]),
      'package3' => new Package('package3', [
        'config' => ['example.config5'],
        'dependencies' => [],
        'bundle' => 'giraffe',
      ]),
      'package4' => new Package('package4', [
        'config' => ['example.config7'],
        'dependencies' => [],
        'bundle' => 'giraffe',
      ]),
    ];

    $features_manager->setPackages($packages);
    // Dependencies require the full package names.
    $package_names = array_keys($packages);
    $features_manager->setPackageBundleNames($bundle->reveal(), $package_names);
    $packages = $features_manager->getPackages();
    $features_manager->assignInterPackageDependencies($bundle->reveal(), $packages);
    // example.config3 has a providing_feature but no assigned package.
    // my_package2 provides configuration required by configuration in
    // my_package.
    // Because package assignments take precedence over providing_feature ones,
    // package2 should have been assigned rather than my_feature.
    // Because it is assigned to the InstallStorage::CONFIG_OPTIONAL_DIRECTORY
    // subdirectory, example.config5 does not create a dependency on its
    // providing feature, package3.
    // Because it's provided by an uninstalled module, example.config6 doesn't
    // create a dependency on my_uninstalled_feature.
    // Because it's provided by an uninstalled module, example.config7 doesn't
    // create a dependency on giraffe_package4.
    $expected = ['giraffe_package2', 'my_other_feature'];
    $this->assertEquals($expected, $packages['giraffe_package']->getDependencies());
  }

  /**
   * @covers ::assignInterPackageDependencies
   */
  public function testAssignInterPackageDependenciesPrematureCall() {
    $bundle = $this->prophesize(FeaturesBundleInterface::class);
    $packages = [
      'package' => new Package('package', [
        'config' => ['example.config', 'example.config3'],
        'dependencies' => [],
        'bundle' => 'giraffe',
      ]),
    ];
    // TBD: why 'Error' and not 'Exception'?
    $this->expectException('Error');
    $this->expectExceptionMessag('The packages have not yet been prefixed with a bundle name');
    $this->featuresManager->assignInterPackageDependencies($bundle->reveal(), $packages);
  }

  /**
   * @covers ::reset
   */
  public function testReset() {
    $packages = [
      'package' => [
        'machine_name' => 'package',
        'config' => ['example.config', 'example.config3'],
        'dependencies' => [],
        'bundle' => 'giraffe',
      ],
      'package2' => [
        'machine_name' => 'package2',
        'config' => ['example.config2'],
        'dependencies' => [],
        'bundle' => 'giraffe',
      ],
    ];
    $this->featuresManager->setPackages($packages);

    $config_item = new ConfigurationItem('example', [], ['package' => 'package']);
    $config_item2 = new ConfigurationItem('example2', [], ['package' => 'package2']);
    $this->featuresManager->setConfigCollection([$config_item, $config_item2]);

    $this->featuresManager->reset();
    $this->assertEmpty($this->featuresManager->getPackages());
    $config_collection = $this->featuresManager->getConfigCollection();
    $this->assertEquals('', $config_collection[0]->getPackage());
    $this->assertEquals('', $config_collection[1]->getPackage());
  }

  /**
   * @covers ::detectMissing
   */
  public function testDetectMissing() {
    $package = new Package('test-package', [
      'configOrig' => ['test_config', 'test_config_non_existing'],
    ]);

    $config_collection = [];
    $config_collection['test_config'] = new ConfigurationItem('test_config', []);
    $this->featuresManager->setConfigCollection($config_collection);

    $this->assertEquals(['test_config_non_existing'], $this->featuresManager->detectMissing($package));
  }

  /**
   * @covers ::detectOverrides
   */
  public function testDetectOverrides() {
    $config_diff = $this->prophesize(ConfigDiffInterface::class);
    $config_diff->same(Argument::cetera())->will(function ($args) {
      return $args[0] == $args[1];
    });
    \Drupal::getContainer()->set('config_update.config_diff', $config_diff->reveal());

    $package = new Package('test-package', [
      'config' => ['test_config', 'test_overridden'],
    ]);

    $config_storage = $this->prophesize(StorageInterface::class);
    $config_storage->read('test_config')->willReturn([
      'key' => 'value',
    ]);
    $config_storage->read('test_overridden')->willReturn([
      'key2' => 'value2',
    ]);

    $extension_storage = $this->prophesize(FeaturesExtensionStoragesInterface::class);
    $extension_storage->read('test_config')->willReturn([
      'key' => 'value',
    ]);
    $extension_storage->read('test_overridden')->willReturn([
      'key2' => 'value0',
    ]);

    $features_manager = new TestFeaturesManager($this->root, $this->entityTypeManager, $this->configFactory, $config_storage->reveal(), $this->configManager, $this->moduleHandler, $this->configReverter, $this->moduleExtensionList, $this->extensionPathResolver);
    $features_manager->setExtensionStorages($extension_storage->reveal());

    $this->assertEquals(['test_overridden'], $features_manager->detectOverrides($package));
  }

  /**
   * @covers ::assignConfigPackage
   */
  public function testAssignConfigPackageWithNonProviderExcludedConfig() {
    $assigner = $this->prophesize(FeaturesAssignerInterface::class);
    $bundle = $this->prophesize(FeaturesBundleInterface::class);
    $bundle->isProfilePackage('test_package')->willReturn(FALSE);
    $bundle->isProfilePackage('test_package2')->willReturn(FALSE);
    $assigner->getBundle(NULL)->willReturn($bundle->reveal());
    $this->featuresManager->setAssigner($assigner->reveal());

    $config_collection = [
      'test_config' => new ConfigurationItem('test_config', []),
      'test_config2' => new ConfigurationItem('test_config2', [
        'dependencies' => [
          'module' => ['example', 'example2'],
        ],
      ], [
        'subdirectory' => InstallStorage::CONFIG_INSTALL_DIRECTORY,
      ]),
      'example3.settings' => new ConfigurationItem('example3.settings', [], [
        'type' => FeaturesManagerInterface::SYSTEM_SIMPLE_CONFIG,
        'subdirectory' => InstallStorage::CONFIG_INSTALL_DIRECTORY,
      ]),
      'test_config3' => new ConfigurationItem('test_config3', [
        'dependencies' => [
          'module' => ['example2'],
        ],
      ], [
        'subdirectory' => InstallStorage::CONFIG_OPTIONAL_DIRECTORY,
      ]),
    ];
    $this->featuresManager->setConfigCollection($config_collection);

    $package = new Package('test_package');
    $this->featuresManager->setPackage($package);

    $this->featuresManager->assignConfigPackage('test_package', ['test_config', 'test_config2', 'example3.settings']);

    $this->assertEquals(['test_config', 'test_config2', 'example3.settings'], $this->featuresManager->getPackage('test_package')->getConfig());
    // 'example2' is not returned by ::getModuleList() and so isn't a
    // dependency.
    $this->assertEquals(['example', 'example3', 'my_module'], $this->featuresManager->getPackage('test_package')->getDependencies());

    // Test optional config, which doesn't create module dependencies.
    $package = new Package('test_package2');
    $this->featuresManager->setPackage($package);

    $this->featuresManager->assignConfigPackage('test_package2', ['test_config3']);

    $this->assertEquals(['test_config3'], $this->featuresManager->getPackage('test_package2')->getConfig());
    $this->assertEquals([], $this->featuresManager->getPackage('test_package2')->getDependencies());
  }

  /**
   * @covers ::assignConfigPackage
   */
  public function testAssignConfigPackageWithProviderExcludedConfig() {
    $config_collection = [
      'test_config' => new ConfigurationItem('test_config', []),
      'test_config2' => new ConfigurationItem('test_config2', [], ['providerExcluded' => TRUE]),
    ];
    $this->featuresManager->setConfigCollection($config_collection);

    $feature_assigner = $this->prophesize(FeaturesAssignerInterface::class);
    $feature_assigner->getBundle(NULL)->willReturn(new FeaturesBundle(['machine_name' => FeaturesBundleInterface::DEFAULT_BUNDLE], 'features_bundle'));
    $this->featuresManager->setAssigner($feature_assigner->reveal());

    $package = new Package('test_package');
    $original_package = clone $package;

    $this->featuresManager->setPackage($package);
    $this->featuresManager->assignConfigPackage('test_package', ['test_config', 'test_config2']);
    $this->assertEquals(['test_config'], $this->featuresManager->getPackage('test_package')->getConfig(), 'just assign new packages');

    $this->featuresManager->setPackage($original_package);
    $this->featuresManager->assignConfigPackage('test_package', ['test_config', 'test_config2'], TRUE);
    $this->assertEquals(['test_config', 'test_config2'], $this->featuresManager->getPackage('test_package')->getConfig(), 'just assign new packages');
  }

  /**
   * @covers ::assignConfigPackage
   */
  public function testAssignConfigPackageWithPackageExcludedConfig() {
    $config_collection = [
      'test_config' => new ConfigurationItem('test_config', []),
      'test_config2' => new ConfigurationItem('test_config2', [], ['packageExcluded' => ['test_package']]),
    ];
    $this->featuresManager->setConfigCollection($config_collection);

    $feature_assigner = $this->prophesize(FeaturesAssignerInterface::class);
    $feature_assigner->getBundle(NULL)->willReturn(new FeaturesBundle(['machine_name' => 'default'], 'features_bundle'));
    $this->featuresManager->setAssigner($feature_assigner->reveal());

    $package = new Package('test_package');
    $original_package = clone $package;

    $this->featuresManager->setPackage($package);
    $this->featuresManager->assignConfigPackage('test_package', ['test_config', 'test_config2']);
    $this->assertEquals(['test_config'], $this->featuresManager->getPackage('test_package')->getConfig(), 'just assign new packages');

    $this->featuresManager->setPackage($original_package);
    $this->featuresManager->assignConfigPackage('test_package', ['test_config', 'test_config2'], TRUE);
    $this->assertEquals(['test_config', 'test_config2'], $this->featuresManager->getPackage('test_package')->getConfig(), 'just assign new packages');
  }

  /**
   * @covers ::initPackageFromExtension
   * @covers ::getPackageObject
   */
  public function testInitPackageFromNonInstalledExtension() {
    $this->setupVfsWithTestFeature();
    $extension = new Extension('vfs://drupal', 'module', 'modules/test_feature/test_feature.info.yml');

    $bundle = $this->prophesize(FeaturesBundle::class);
    $bundle->getFullName('test_feature')->willReturn('test_feature');
    $bundle->isDefault()->willReturn(TRUE);

    $assigner = $this->prophesize(FeaturesAssignerInterface::class);
    $assigner->findBundle(Argument::cetera())->willReturn($bundle->reveal());
    $this->featuresManager->setRoot('vfs://drupal');
    $this->featuresManager->setAssigner($assigner->reveal());

    $result = $this->featuresManager->initPackageFromExtension($extension);
    $this->assertInstanceOf(Package::class, $result);
    // Ensure that that calling the function twice works.
    $result = $this->featuresManager->initPackageFromExtension($extension);
    $this->assertInstanceOf(Package::class, $result);

    $this->assertEquals('test_feature', $result->getMachineName());
    $this->assertEquals('Test feature', $result->getName());
    $this->assertEquals('test description', $result->getDescription());
    $this->assertEquals('module', $result->getType());

    $this->assertEquals(FeaturesManagerInterface::STATUS_UNINSTALLED, $result->getStatus());
  }

  /**
   * @covers ::initPackageFromExtension
   * @covers ::getPackageObject
   */
  public function testInitPackageFromInstalledExtension() {
    $this->setupVfsWithTestFeature();
    $extension = new Extension('vfs://drupal', 'module', 'modules/test_feature/test_feature.info.yml');

    $bundle = $this->prophesize(FeaturesBundle::class);
    $bundle->getFullName('test_feature')->willReturn('test_feature');
    $bundle->isDefault()->willReturn(TRUE);

    $assigner = $this->prophesize(FeaturesAssignerInterface::class);
    $assigner->findBundle(Argument::cetera())->willReturn($bundle->reveal());
    $this->featuresManager->setRoot('vfs://drupal');
    $this->featuresManager->setAssigner($assigner->reveal());

    $this->moduleHandler->expects($this->any())
      ->method('moduleExists')
      ->with('test_feature')
      ->willReturn(TRUE);

    $result = $this->featuresManager->initPackageFromExtension($extension);
    $this->assertEquals(FeaturesManagerInterface::STATUS_INSTALLED, $result->getStatus());
  }

  /**
   * {@inheritDoc}
   */
  public function testDetectNewWithNoConfig() {
    $package = new Package('test_feature');

    $this->assertEmpty($this->featuresManager->detectNew($package));
  }

  /**
   * {@inheritDoc}
   */
  public function testDetectNewWithNoNewConfig() {
    $package = new Package('test_feature', ['config' => ['test_config']]);

    $extension_storage = $this->prophesize(FeaturesExtensionStoragesInterface::class);
    $extension_storage->read('test_config')->willReturn([
      'key' => 'value',
    ]);

    $features_manager = new TestFeaturesManager($this->root, $this->entityTypeManager, $this->configFactory, $this->configStorage, $this->configManager, $this->moduleHandler, $this->configReverter, $this->moduleExtensionList, $this->extensionPathResolver);
    $features_manager->setExtensionStorages($extension_storage->reveal());

    $this->assertEmpty($features_manager->detectNew($package));
  }

  /**
   * {@inheritDoc}
   */
  public function testDetectNewWithNewConfig() {
    $package = new Package('test_feature', ['config' => ['test_config']]);

    $extension_storage = $this->prophesize(FeaturesExtensionStoragesInterface::class);
    $extension_storage->read('test_config')->willReturn(FALSE);

    $features_manager = new TestFeaturesManager($this->root, $this->entityTypeManager, $this->configFactory, $this->configStorage, $this->configManager, $this->moduleHandler, $this->configReverter, $this->moduleExtensionList, $this->extensionPathResolver);
    $features_manager->setExtensionStorages($extension_storage->reveal());

    $this->assertEquals(['test_config'], $features_manager->detectNew($package));
  }

  /**
   * The test for merge info array.
   *
   * @todo This could have of course much more test coverage.
   *
   * @covers ::mergeInfoArray
   *
   * @dataProvider providerTestMergeInfoArray
   */
  public function testMergeInfoArray($expected, $info1, $info2, $keys = []) {
    $this->assertSame($expected, $this->featuresManager->mergeInfoArray($info1, $info2, $keys));
  }

  /**
   * {@inheritDoc}
   */
  public static function providerTestMergeInfoArray() {
    $data = [];
    $data['empty-info'] = [[], [], []];
    $data['override-info'] = [
      ['name' => 'New name', 'core_version_requirement' => FeaturesBundleInterface::CORE_VERSION_REQUIREMENT],
      ['name' => 'Old name', 'core_version_requirement' => FeaturesBundleInterface::CORE_VERSION_REQUIREMENT],
      ['name' => 'New name'],
    ];
    $data['dependency-merging'] = [
      ['dependencies' => ['a:a', 'b:b', 'c:c', 'd:d', 'e:e']],
      ['dependencies' => ['b', 'd', 'c']],
      ['dependencies' => ['a:a', 'b:b', 'e:e']],
      [],
    ];

    return $data;
  }

  /**
   * @covers ::initPackage
   */
  public function testInitPackageWithNewPackage() {
    $bundle = new FeaturesBundle(['machine_name' => 'test'], 'features_bundle');

    $features_manager = new TestFeaturesManager($this->root, $this->entityTypeManager, $this->configFactory, $this->configStorage, $this->configManager, $this->moduleHandler, $this->configReverter, $this->moduleExtensionList, $this->extensionPathResolver);
    $features_manager->setAllModules([]);

    $package = $features_manager->initPackage('test_feature', 'test name', 'test description', 'module', $bundle);

    $this->assertInstanceOf(Package::class, $package);
    $this->assertEquals('test_feature', $package->getMachineName());
    $this->assertEquals('test name', $package->getName());
    $this->assertEquals('test description', $package->getDescription());
    $this->assertEquals('module', $package->getType());
    $this->assertEquals(['bundle' => 'test'], $package->getFeaturesInfo());
    $this->assertEquals('test', $package->getBundle());
    $this->assertEquals(FALSE, $package->getRequired());
    $this->assertEquals([], $package->getExcluded());
  }

  /**
   * @covers ::getFeaturesInfo
   * @covers ::getFeaturesModules
   */
  public function testInitPackageWithExistingPackage() {
    $bundle = new FeaturesBundle(['machine_name' => 'test'], 'features_bundle');

    $features_manager = new TestFeaturesManager('vfs://drupal', $this->entityTypeManager, $this->configFactory, $this->configStorage, $this->configManager, $this->moduleHandler, $this->configReverter, $this->moduleExtensionList, $this->extensionPathResolver);

    $this->setupVfsWithTestFeature();
    $extension = new Extension('vfs://drupal', 'module', 'modules/test_feature/test_feature.info.yml');
    $features_manager->setAllModules(['test_feature' => $extension]);

    $this->moduleHandler->expects($this->any())
      ->method('moduleExists')
      ->with('test_feature')
      ->willReturn(TRUE);

    $package = $features_manager->initPackage('test_feature', 'test name', 'test description', 'module', $bundle);

    $this->assertEquals([
      'bundle' => 'test',
      'excluded' => [
        0 => 'system.theme',
      ],
      'required' => TRUE,
    ], $features_manager->getFeaturesInfo($extension));
    $this->assertEquals(['test_feature' => $extension], $features_manager->getFeaturesModules($bundle));

    $this->assertInstanceOf(Package::class, $package);
    $this->assertEquals([
      'bundle' => 'test',
      'excluded' => [
        0 => 'system.theme',
      ],
      'required' => TRUE,
    ], $package->getFeaturesInfo());
    $this->assertEquals('test', $package->getBundle());
    $this->assertEquals(TRUE, $package->getRequired());
    $this->assertEquals(['system.theme'], $package->getExcluded());
  }

  /**
   * @covers ::prepareFiles
   * @covers ::addInfoFile
   */
  public function testPrepareFiles() {
    $packages = [];
    $packages['test_feature'] = new Package('test_feature', [
      'config' => ['test_config'],
      'name' => 'Test feature',
    ]);

    $packages['test_feature2'] = new Package('test_feature2', [
      'config' => ['test_config2'],
      'name' => 'Test feature 2',
      'type' => 'profile',
      'excluded' => ['my_config'],
      'required' => ['test_config2'],
    ]);

    $config_collection = [];
    $config_collection['test_config'] = new ConfigurationItem('test_config', ['foo' => 'bar']);
    $config_collection['test_config2'] = new ConfigurationItem('test_config2', ['foo' => 'bar']);

    $this->featuresManager->setConfigCollection($config_collection);
    $this->featuresManager->prepareFiles($packages);

    // Test test_feature package.
    $files = $packages['test_feature']->getFiles();
    $this->assertCount(3, $files);
    $this->assertEquals('test_feature.info.yml', $files['info']['filename']);
    $this->assertEquals(Yaml::encode([
      'name' => 'Test feature',
      'type' => 'module',
      'core_version_requirement' => FeaturesBundleInterface::CORE_VERSION_REQUIREMENT,
    ]), $files['info']['string']);
    $this->assertEquals(Yaml::encode(TRUE), $files['features']['string']);

    $this->assertEquals('test_config.yml', $files['test_config']['filename']);
    $this->assertEquals(Yaml::encode([
      'foo' => 'bar',
    ]), $files['test_config']['string']);

    $this->assertEquals('test_feature.features.yml', $files['features']['filename']);
    $this->assertEquals(Yaml::encode(TRUE), $files['features']['string']);

    // Test test_feature2 package.
    $files = $packages['test_feature2']->getFiles();

    $this->assertEquals(Yaml::encode([
      'excluded' => ['my_config'],
      'required' => ['test_config2'],
    ]), $files['features']['string']);
  }

  /**
   * @covers ::getExportInfo
   */
  public function testGetExportInfoWithoutBundle() {
    $config_factory = $this->getConfigFactoryStub([
      'features.settings' => [
        'export' => [
          'folder' => 'custom',
        ],
      ],
    ]);
    $this->featuresManager = new FeaturesManager($this->root, $this->entityTypeManager, $config_factory, $this->configStorage, $this->configManager, $this->moduleHandler, $this->configReverter, $this->moduleExtensionList, $this->extensionPathResolver);

    $package = new Package('test_feature');
    $result = $this->featuresManager->getExportInfo($package);

    $this->assertEquals(['test_feature', 'modules/custom'], $result);
  }

  /**
   * @covers ::getExportInfo
   */
  public function testGetExportInfoWithBundle() {
    $config_factory = $this->getConfigFactoryStub([
      'features.settings' => [
        'export' => [
          'folder' => 'custom',
        ],
      ],
    ]);
    $this->featuresManager = new FeaturesManager($this->root, $this->entityTypeManager, $config_factory, $this->configStorage, $this->configManager, $this->moduleHandler, $this->configReverter, $this->moduleExtensionList, $this->extensionPathResolver);

    $package = new Package('test_feature');
    $bundle = new FeaturesBundle(['machine_name' => 'test_bundle'], 'features_bundle');

    $result = $this->featuresManager->getExportInfo($package, $bundle);

    $this->assertEquals(['test_bundle_test_feature', 'modules/custom'], $result);
  }

}
/**
 * {@inheritDoc}
 */
class TestFeaturesManager extends FeaturesManager {

  protected $allModules;

  /**
   * Set extension storages.
   *
   * @param \Drupal\features\FeaturesExtensionStoragesInterface $extensionStorages
   *   The feature extension storages interface.
   */
  public function setExtensionStorages($extensionStorages) {
    $this->extensionStorages = $extensionStorages;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllModules() {
    if (isset($this->allModules)) {
      return $this->allModules;
    }
    return parent::getAllModules();
  }

  /**
   * Set all modules.
   *
   * @param mixed $all_modules
   */
  public function setAllModules($all_modules) {
    $this->allModules = $all_modules;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  protected function drupalGetProfile() {
    return FeaturesManagerTest::PROFILE_NAME;
  }

}
