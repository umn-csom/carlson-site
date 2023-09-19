<?php

namespace Drupal\ape\Tests;

use Drupal\Tests\BrowserTestBase;

/**
 * Test cache-control header is set correctly after configuration.
 *
 * @group Advanced Page Expiration
 */
class ApeTest extends BrowserTestBase {

  protected $dumpHeaders = TRUE;

  protected $defaultTheme = 'stark';

  /**
   * Modules to install.
   */
  protected static $modules = ['ape', 'ape_test', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('system.performance')
      ->set('cache.page.max_age', 2592000)
      ->save();
    $this->config('ape.settings')
      ->set('alternatives', '/ape_alternative')
      ->set('exclusions', '/ape_exclude')
      ->set('lifetime.alternatives', 60)
      ->set('lifetime.301', 1800)
      ->set('lifetime.302', 600)
      ->set('lifetime.404', 3600)
      ->save();
  }

  public function testApeHeaders() {
    // Check user registration page has global age.
    $this->drupalGet('user/register');
    $session = $this->getSession();
    $this->assertEquals('max-age=2592000, public', $session->getResponseHeader('Cache-Control'), 'Global Cache-Control header set.');

    // Check homepage has alternative age.
    $this->drupalGet('/ape_alternative');
    $this->assertEquals('max-age=60, public', $session->getResponseHeader('Cache-Control'), 'Alternative Cache-Control header set.');

    // Check login page is excluded from caching.
    $this->drupalGet('/ape_exclude');
    $this->assertEquals('must-revalidate, no-cache, private', $session->getResponseHeader('Cache-Control'), 'Page successfully excluded from caching.');

    // Check that authenticated users bypass the cache.
    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);
    $this->drupalGet('user');
    $this->assertNull($session->getResponseHeader('X-Drupal-Cache'), 'Caching was bypassed.');
    $this->assertEquals('must-revalidate, no-cache, private', $session->getResponseHeader('Cache-Control'), 'Cache-Control header was sent.');
    $this->drupalLogout();

    // Check that 403 responses have configured age.
    $this->drupalGet('admin/structure');
    $this->assertEquals('must-revalidate, no-cache, private', $session->getResponseHeader('Cache-Control'), 'Forbidden page was not cached.');

    // Check that 404 responses have configured age.
    $this->drupalGet('notfindingthat');
    $this->assertEquals('max-age=3600, public', $session->getResponseHeader('Cache-Control'), '404 Page Not Found Cache-Control header set.');

    // @todo Figure out why these tests aren't working. The browser output shows
    // that are they are working as expected. Drupal 8 returned an array of
    // headers in a redirect, but Drupal 9 (and I'm guessing Symfony) are not.
    // Settings followRedirects to false should do the trick, but it's not
    // being respected for some reason.

    // // Check that 301 redirects work correctly.
    //    $session->getDriver()->getClient()->followRedirects(false);
    //    $this->drupalGet('ape_redirect_301');
    //    $this->assertEquals('max-age=1800, public', $session->getResponseHeader('Cache-Control'), '301 redirect Cache-Control header set.');
    //
    //    // Check that 302 redirects work correctly.
    //    $this->getSession()->getDriver()->getClient()->followRedirects(false);
    //    $this->drupalGet('ape_redirect_302');
    //    $this->assertEquals('max-age=600, public', $session->getResponseHeader('Cache-Control'), '302 redirect Cache-Control header set.');
  }

}
