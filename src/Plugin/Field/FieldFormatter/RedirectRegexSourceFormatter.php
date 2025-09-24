<?php

namespace Drupal\redirect_regex\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Implementation of the 'redirect_regex_source' formatter.
 *
 * @FieldFormatter(
 *   id = "redirect_regex_source",
 *   label = @Translation("Redirect Regex Source"),
 *   field_types = {
 *     "redirect_source",
 *   }
 * )
 */
class RedirectRegexSourceFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $uri = urldecode($item->getUrl()->toString());
      $find = '/regex:';
      if (str_starts_with($uri, $find)) {
        $uri = '[RegEx] '. substr($uri, strlen($find));
      }
      $elements[$delta] = [
        '#markup' => $uri,
      ];
    }

    return $elements;
  }

}
