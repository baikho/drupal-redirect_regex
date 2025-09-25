<?php

namespace Drupal\redirect_regex;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Language\Language;
use Drupal\redirect\RedirectRepository;

/**
 * Extended redirect repository that supports regex pattern matching using redirect entities.
 */
class RedirectRegexRepository extends RedirectRepository {

  /**
   * {@inheritdoc}
   */
  public function findMatchingRedirect($source_path, array $query = [], $language = Language::LANGCODE_NOT_SPECIFIED, ?CacheableMetadata $cacheable_metadata = NULL) {
    // First check for regular redirects using parent logic.
    $redirect = parent::findMatchingRedirect($source_path, $query, $language, $cacheable_metadata);
    if ($redirect) {
      return $redirect;
    }
    // If no regular redirect found, check for regex redirects in redirect entities.
    return $this->findMatchingRegexRedirect($source_path, $language);
  }

  /**
   * Compatibility with Redirect patch.
   *
   * @see https://dgo.to/2879648
   */
  protected function findRedirectByHashes(array $hashes, $source_path, $language, array $query = []) {
    $redirect = parent::findRedirectByHashes($hashes, $source_path, $language, $query);
    if ($redirect) {
      return $redirect;
    }
    return $this->findMatchingRegexRedirect($source_path, $language);
  }

  /**
   * Finds a regex redirect for a given path and language using redirect entities.
   *
   * Regex redirects are identified by having a source path that starts with 'regex:'.
   * The actual regex pattern follows the 'regex:' prefix.
   *
   * @param string $source_path
   *   The redirect source path.
   * @param string $language
   *   The language for which the redirect is.
   *
   * @return \Drupal\redirect\Entity\Redirect|null
   *   The matched redirect entity or NULL if no redirect was found.
   */
  protected function findMatchingRegexRedirect($source_path, $language = Language::LANGCODE_NOT_SPECIFIED) {
    try {
      // Query for redirects that have source paths starting with 'regex:'.
      // This is more efficient than loading all redirects and filtering in PHP.
      $query = $this->manager->getStorage('redirect')->getQuery()
        ->condition('redirect_source.path', 'regex:', 'STARTS_WITH')
        ->condition('language', [$language, Language::LANGCODE_NOT_SPECIFIED], 'IN')
        ->condition('enabled', TRUE)
        ->accessCheck(FALSE);

      $redirect_ids = $query->execute();
      $redirects = $this->manager->getStorage('redirect')->loadMultiple($redirect_ids);

      // Get current language prefix for testing paths with language prefix.
      $current_language = \Drupal::languageManager()->getCurrentLanguage();
      $language_prefix = '';
      if ($current_language->getId() !== Language::LANGCODE_NOT_SPECIFIED && $current_language->getId() !== \Drupal::config('system.site')->get('langcode')) {
        $language_prefix = $current_language->getId() . '/';
      }

      // Check each regex redirect to see if it matches.
      foreach ($redirects as $redirect) {
        /** @var \Drupal\redirect\Entity\Redirect $redirect */
        // Get the raw source path from the field, not the processed URL.
        $source_field = $redirect->get('redirect_source');
        if ($source_field && !$source_field->isEmpty()) {
          $source_value = $source_field->get(0)->getValue()['path'];
        }
        else {
          continue;
        }

        // Remove 'regex:' prefix to get the actual regex pattern.
        $pattern = substr($source_value, 6);

        // Skip empty patterns.
        if (empty($pattern)) {
          continue;
        }

        // URL decode the pattern, but be careful with regex chars
        // Only decode if the pattern actually contains URL-encoded chars.
        if (str_contains($pattern, '%')) {
          $decoded_pattern = urldecode($pattern);
        }
        else {
          $decoded_pattern = $pattern;
        }

        // The pattern extracted from 'regex:...' should be treated as a raw regex pattern
        // Remove leading slashes to match the normalized paths (which don't have leading slashes)
        $normalized_pattern = ltrim($decoded_pattern, '/');

        // Add regex delimiters and case-insensitive flag.
        $final_pattern = '/' . $normalized_pattern . '/i';

        // Test if the source path matches the regex pattern.
        // Also test with language prefix for multilingual support.
        $test_paths = [$source_path];
        if (!empty($language_prefix) && !str_starts_with($source_path, $language_prefix)) {
          $test_paths[] = $language_prefix . $source_path;
        }

        foreach ($test_paths as $test_path) {
          if (@preg_match($final_pattern, $test_path)) {
            return $redirect;
          }
        }
      }
    }
    catch (\Exception $e) {
      // If there's any error (e.g., invalid regex),
      // return null to avoid breaking the redirect system.
      return NULL;
    }

    return NULL;
  }

}
