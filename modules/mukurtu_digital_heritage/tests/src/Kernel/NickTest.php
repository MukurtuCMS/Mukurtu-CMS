<?php

namespace Drupal\Tests\mukurtu_digital_heritage\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\nick_entity_test\Entity\NickEntityTest;

/**
 * Test description.
 *
 * @group mukurtu_digital_heritage
 */
class NickTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'entity_test', 'nick_entity_test', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('nick_entity_test');
  }

  /**
   * Test callback.
   */
  public function testSomething() {
    $entity = NickEntityTest::create(['name' => 'test']);
    $result = $entity->getHello();
    $this->assertEquals('hello', $result);
  }

}
