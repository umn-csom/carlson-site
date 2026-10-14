<?php

namespace Drupal\Tests\user_expire\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the user_expire_get_role_rules() function.
 */
#[RunTestsInSeparateProcesses]
class UserExpireGetRoleRulesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'user_expire',
  ];

  /**
   * Tests that valid role rules are returned when one is disabled.
   */
  public function testGetRoleRulesWithOneRole() {
    $rules = [
      'authenticated' => 3600,
      'editor' => 0,
    ];

    $expected_result_with_disabled_roles_removed = [
      'authenticated' => 3600,
    ];
    // Set the configuration for user_expire.settings.
    $config = $this->container->get('config.factory')->getEditable('user_expire.settings');
    $config->set('user_expire_roles', $rules)->save();
    // Call the function under test.
    $result = user_expire_get_role_rules();
    // Assert that the result matches the rules we set.
    $this->assertEquals($expected_result_with_disabled_roles_removed, $result, 'Disabled roles should be removed from the returned role rules.');
  }

  /**
   * Tests that valid role rules are returned.
   */
  public function testGetRoleRulesWithMultipleRoles() {
    $rules = [
      'authenticated' => 3600,
      'editor' => 7200,
    ];
    // Set the configuration for user_expire.settings.
    $config = $this->container->get('config.factory')->getEditable('user_expire.settings');
    $config->set('user_expire_roles', $rules)->save();
    // Call the function under test.
    $result = user_expire_get_role_rules();
    // Assert that the result matches the rules we set.
    $this->assertEquals($rules, $result, 'Disabled roles should be removed from the returned role rules.');
  }

  /**
   * Tests that an empty array is returned when no rules are configured.
   */
  public function testGetRoleRulesEmptyWhenNoRules() {
    // Set an empty array as the configuration.
    $config = $this->container->get('config.factory')->getEditable('user_expire.settings');
    $config->set('user_expire_roles', [])->save();

    // Call the function under test.
    $result = user_expire_get_role_rules();
    // Assert that an empty array is returned.
    $this->assertEquals([], $result, 'The returned role rules should be an empty array when no rules are configured.');
  }

}
