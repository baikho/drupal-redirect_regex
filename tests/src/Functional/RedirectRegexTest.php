<?php

declare(strict_types=1);

namespace Drupal\Tests\redirect_regex\Functional;

use Drupal\Core\Url;
use Drupal\redirect\Entity\Redirect;
use Drupal\Tests\BrowserTestBase;
use GuzzleHttp\Exception\ClientException;

/**
 * Tests the redirect regex functionality.
 *
 * @group redirect_regex
 */
class RedirectRegexTest extends BrowserTestBase {

  /**
   * The Sql content entity storage.
   *
   * @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['redirect_regex'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->storage = \Drupal::entityTypeManager()->getStorage('redirect');
  }

  /**
   * Asserts the redirect from a given path to the expected destination path.
   *
   * @param string $path
   *   The request path.
   * @param string|null $expected_ending_url
   *   The path where we expect it to redirect. If NULL value provided, no
   *   redirect is expected.
   * @param int $expected_ending_status
   *   The status we expect to get with the first request.
   * @param string $method
   *   The HTTP METHOD to use.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP response.
   */
  protected function assertRedirect($path, $expected_ending_url, $expected_ending_status = 301, $method = 'GET') {
    $client = $this->getHttpClient();
    $url = $this->getAbsoluteUrl($path);
    try {
      $response = $client->request($method, $url, ['allow_redirects' => FALSE]);
    }
    catch (ClientException $e) {
      $this->assertEquals($expected_ending_status, $e->getResponse()->getStatusCode());
      return $e->getResponse();
    }

    $actual_status = $response->getStatusCode();
    $this->assertEquals($expected_ending_status, $actual_status, "Expected status $expected_ending_status for path '$path' but got $actual_status");

    $ending_url = $response->getHeader('location');
    $ending_url = $ending_url ? $ending_url[0] : NULL;
    $message = "Testing redirect from $path to $expected_ending_url. Ending url: $ending_url";

    if ($expected_ending_url == '<front>') {
      $expected_ending_url = Url::fromUri('base:')->setAbsolute()->toString();
    }
    elseif (!empty($expected_ending_url)) {
      // Check for absolute/external urls.
      if (!parse_url($expected_ending_url, PHP_URL_SCHEME)) {
        $expected_ending_url = Url::fromUri('base:' . $expected_ending_url)->setAbsolute()->toString();
      }
    }
    else {
      $expected_ending_url = NULL;
    }

    $this->assertEquals($ending_url, $expected_ending_url, $message);
    return $response;
  }

  /**
   * Tests that the redirect_regex service override is working.
   */
  public function testServiceOverride() {
    // Check that the redirect repository service is using our class.
    $repository = \Drupal::service('redirect.repository');
    $this->assertInstanceOf('Drupal\redirect_regex\RedirectRegexRepository', $repository);
  }

  /**
   * Tests what happens for a non-existent path with no redirects.
   */
  public function testNonExistentPath() {
    // Test a path that definitely doesn't exist and has no redirects.
    $this->assertRedirect('this/path/definitely/does/not/exist', NULL, 404);
  }

  /**
   * Tests the three example regex redirects from the README.
   */
  public function testRegexRedirectExamples() {

    // Example 1: blog/\d+/.* -> /blog/archive
    /** @var \Drupal\redirect\Entity\Redirect $redirect1 */
    $redirect1 = $this->storage->create();
    $redirect1->setSource('regex:blog\/\d+\/.*');
    $redirect1->setRedirect('test-page');
    $redirect1->setStatusCode(301);
    $redirect1->save();

    // Example 2: user/\d+/profile -> /user/profile
    /** @var \Drupal\redirect\Entity\Redirect $redirect2 */
    $redirect2 = $this->storage->create();
    $redirect2->setSource('regex:user\/\d+\/profile');
    $redirect2->setRedirect('test-page');
    $redirect2->setStatusCode(301);
    $redirect2->save();

    // Example 3: page/old/([0-9a-z]+) -> /page/new-page
    /** @var \Drupal\redirect\Entity\Redirect $redirect3 */
    $redirect3 = $this->storage->create();
    $redirect3->setSource('regex:page\/old\/([0-9a-z]+)');
    $redirect3->setRedirect('test-page');
    $redirect3->setStatusCode(301);
    $redirect3->save();

    // Test Example 1: blog URLs should redirect.
    $this->assertRedirect('blog/123/old-post', 'test-page', 301);
    $this->assertRedirect('blog/456/another-post', 'test-page', 301);

    // Test that non-matching blog URLs don't redirect.
    $this->assertRedirect('completely/different/path', NULL, 404);

    // Test Example 2: user profile URLs should redirect.
    $this->assertRedirect('user/123/profile', 'test-page', 301);
    $this->assertRedirect('user/999/profile', 'test-page', 301);

    // Test that non-matching user URLs don't redirect.
    $this->assertRedirect('user/abc/edit', NULL, 404);

    // Test Example 3: old page URLs with alphanumeric IDs should redirect.
    $this->assertRedirect('page/old/abc123', 'test-page', 301);
    $this->assertRedirect('page/old/def456', 'test-page', 301);

    // Test that URLs without the required structure don't match.
    $this->assertRedirect('page/old', NULL, 404);
    $this->assertRedirect('page/new/abc123', NULL, 404);
  }

  /**
   * Tests that invalid regex patterns don't break the redirect system.
   */
  public function testInvalidRegexHandling() {
    // Create a redirect with invalid regex pattern.
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->storage->create();
    $redirect->setSource('regex:[invalid');
    $redirect->setRedirect('test-page');
    $redirect->setStatusCode(301);
    $redirect->save();

    // The invalid regex should not break the system - it should just not match.
    $this->assertRedirect('some/invalid/path', NULL, 404);
  }

}
