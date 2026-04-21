<?php

namespace Drupal\Tests\message_digest\Kernel;

/**
 * Tests for the digest manager.
 *
 * @group message_digest
 *
 * @coversDefaultClass \Drupal\message_digest\DigestManager
 */
class DigestManagerTest extends DigestTestBase {

  /**
   * Tests processing.
   *
   * @covers ::processDigests
   */
  public function testProcessDigests() {
    // Verify last sent time is not set.
    $this->assertEquals(0, $this->container->get('state')->get('message_digest:weekly_last_run', 0));

    $expected = $this->container->get('datetime.time')->getRequestTime();
    $this->container->get('cron')->run();

    // Last run should be set.
    $last_run = $this->container->get('state')->get('message_digest:weekly_last_run', 0);
    $this->assertEquals($expected, $last_run);

    // Update request time.
    $this->container->get('request_stack')->getCurrentRequest()->server->set('REQUEST_TIME', $expected + 60);

    // Run again, the last_run should not be changed until sufficient time has
    // passed.
    $this->container->get('cron')->run();
    $this->assertEquals($expected, $this->container->get('state')->get('message_digest:weekly_last_run', 0));

    // Update request time to more than a week in the future.
    $this->container->get('request_stack')->getCurrentRequest()->server->set('REQUEST_TIME', $expected + 60 * 60 * 24 * 8);
    $expected = $this->container->get('datetime.time')->getRequestTime();
    $this->container->get('cron')->run();
    $this->assertEquals($expected, $this->container->get('state')->get('message_digest:weekly_last_run', 0));
  }

}
