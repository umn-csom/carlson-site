<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Kernel;

/**
 * Defines a class for testing the sitewide alert entity.
 *
 * @group sitewide_alert
 * @coversDefaultClass \Drupal\sitewide_alert\Entity\SitewideAlert
 */
final class SitewideAlertEntityTest extends SitewideAlertKernelTestBase {

  /**
   * Covers ::isPublished.
   *
   * @covers ::isPublished
   */
  public function testIsPublished(): void {
    $alert = $this->createSiteWideAlert();
    $this->assertTrue($alert->isPublished());

    $alert = $this->createSiteWideAlert([
      'status' => FALSE,
    ]);
    $this->assertFalse($alert->isPublished());
  }

  /**
   * Test basic crud.
   *
   * Tests basic entity crud.
   */
  public function testEntityCrud(): void {
    $name = $this->randomMachineName();
    $alert = $this->createSiteWideAlert([
      'name' => $name,
    ]);
    \Drupal::entityTypeManager()->getStorage('sitewide_alert')->loadUnchanged($alert->id());
    $this->assertEquals($name, $alert->label());
  }

}
