<?php

namespace Drupal\Tests\features\Unit;

use Drupal\features\Entity\FeaturesBundle;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass Drupal\features\Entity\FeaturesBundle
 * @group features
 */
class FeaturesBundleTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Mock an assigner.
    $manager = new DummyPluginManager();

    // Mock the container.
    $container = $this->prophesize('\Symfony\Component\DependencyInjection\ContainerInterface');
    $container->get('plugin.manager.features_assignment_method')
      ->willReturn($manager);
    \Drupal::setContainer($container->reveal());
  }

  /**
   * @covers ::getEnabledAssignments
   * @covers ::getAssignmentWeights
   * @covers ::getAssignmentSettings
   * @covers ::setAssignmentSettings
   * @covers ::setAssignmentWeights
   * @covers ::setEnabledAssignments
   */
  public function testAssignmentSetting() {
    // Create an entity.
    $settings = [
      'foo' => [
        'enabled' => TRUE,
        'weight' => 0,
        'my_setting' => 42,
      ],
      'bar' => [
        'enabled' => FALSE,
        'weight' => 1,
        'another_setting' => 'value',
      ],
    ];
    $bundle = new FeaturesBundle([
      'assignments' => $settings,
    ], 'features_bundle');

    // Get assignments and attributes.
    $this->assertEquals(
      ['foo' => 'foo'],
      $bundle->getEnabledAssignments(),
      'Can get enabled assignments'
    );
    $this->assertEquals(
      ['foo' => 0, 'bar' => 1],
      $bundle->getAssignmentWeights(),
      'Can get assignment weights'
    );
    $this->assertEquals(
      $settings['foo'],
      $bundle->getAssignmentSettings('foo'),
      'Can get assignment settings'
    );
    $this->assertEquals(
      $settings,
      $bundle->getAssignmentSettings(),
      'Can get all assignment settings'
    );

    // Change settings.
    $settings['foo']['my_setting'] = 97;
    $bundle->setAssignmentSettings('foo', $settings['foo']);
    $this->assertEquals(
      $settings['foo'],
      $bundle->getAssignmentSettings('foo'),
      'Can change assignment settings'
    );

    // Change weights.
    $settings['foo']['weight'] = 1;
    $settings['bar']['weight'] = 0;
    $bundle->setAssignmentWeights(['foo' => 1, 'bar' => 0]);
    $this->assertEquals(
      ['foo' => 1, 'bar' => 0],
      $bundle->getAssignmentWeights(),
      'Can change assignment weights'
    );
    $this->assertEquals(
      $settings,
      $bundle->getAssignmentSettings(),
      'Weight changes are reflected in settings'
    );

    // Enable existing assignment.
    $settings['bar']['enabled'] = TRUE;
    $bundle->setEnabledAssignments(['foo', 'bar']);
    $this->assertEquals(
      ['foo' => 'foo', 'bar' => 'bar'],
      $bundle->getEnabledAssignments(),
      'Can enable assignment'
    );
    $this->assertEquals(
      $settings,
      $bundle->getAssignmentSettings(),
      'Enabled assignment status is reflected in settings'
    );

    // Disable existing assignments.
    $settings['foo']['enabled'] = FALSE;
    $settings['bar']['enabled'] = FALSE;
    $bundle->setEnabledAssignments([]);
    $this->assertEquals(
      [],
      $bundle->getEnabledAssignments(),
      'Can disable assignments'
    );
    $this->assertEquals(
      $settings,
      $bundle->getAssignmentSettings(),
      'Disabled assignment status is reflected in settings'
    );

    // Enable a new assignment.
    $settings['foo']['enabled'] = TRUE;
    $settings['iggy'] = ['enabled' => TRUE, 'weight' => 0, 'new_setting' => 3];
    $bundle->setEnabledAssignments(['foo', 'iggy']);
    $this->assertEquals(
      ['foo' => 'foo', 'iggy' => 'iggy'],
      $bundle->getEnabledAssignments(),
      'Can enable new assignment'
    );
    $bundle->setAssignmentSettings('iggy', $settings['iggy']);
    $this->assertEquals(
      $settings,
      $bundle->getAssignmentSettings(),
      'New enabled assignment status is reflected in settings'
    );

  }

  /**
   * @covers ::getFullName
   * @covers ::getShortName
   * @covers ::SetIsProfile
   * @covers ::isProfile
   * @covers ::getProfileName
   * @covers ::isProfilePackage
   * @covers ::inBundle
   */
  public function testFullname() {
    $bundle = new FeaturesBundle([
      'machine_name' => 'mybundle',
      'profile_name' => 'mybundle',
    ], 'mybundle');
    $this->assertFalse($bundle->isProfile());
    // Settings:get('profile_name') isn't defined in test, so this returns NULL.
    $this->assertNull($bundle->getProfileName());
    $this->assertFalse($bundle->isProfilePackage('mybundle'));
    $this->assertEquals('mybundle_test', $bundle->getFullName('test'));
    $this->assertEquals('mybundle_test', $bundle->getFullName('mybundle_test'));
    $this->assertEquals('mybundle_mybundle', $bundle->getFullName('mybundle'));
    $this->assertEquals('test', $bundle->getShortName('test'));
    $this->assertEquals('test', $bundle->getShortName('mybundle_test'));
    $this->assertEquals('mybundle', $bundle->getShortName('mybundle_mybundle'));
    $this->assertEquals('mybundle', $bundle->getShortName('mybundle'));
    $this->assertFalse($bundle->inBundle('test'));
    $this->assertTrue($bundle->inBundle('mybundle_test'));
    $this->assertFalse($bundle->inBundle('mybundle'));

    // Now test it as a profile bundle.
    $bundle->setIsProfile(TRUE);
    $this->assertTrue($bundle->isProfile());
    $this->assertTrue($bundle->isProfilePackage('mybundle'));
    $this->assertFalse($bundle->isProfilePackage('standard'));
    $this->assertEquals('mybundle', $bundle->getProfileName());
    $this->assertEquals('mybundle', $bundle->getFullName('mybundle'));
    $this->assertFalse($bundle->inBundle('test'));
    $this->assertTrue($bundle->inBundle('mybundle_test'));
    $this->assertTrue($bundle->inBundle('mybundle'));
  }

}

/**
 * A dummy plugin manager, to help testing.
 */
class DummyPluginManager {

  /**
   * {@inheritDoc}
   */
  public function getDefinition($method_id) {
    $definition = [
      'enabled' => TRUE,
      'weight' => 0,
      'default_settings' => [
        'my_setting' => 42,
      ],
    ];
    return $definition;
  }

}
