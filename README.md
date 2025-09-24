# Redirect Regex Module

This module extends Drupal's core redirect functionality to support regex pattern matching in addition to exact path matching using the core redirect entities.

## Installation

Add to `composer.json`:
```
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/baikho/drupal-redirect_regex"
    }
  ],
  "require": {
    "drupal/redirect_regex": "1.0.0-alpha1"
  }
}
```
 And run:
```
composer require drupal/redirect_regex:1.0.0-alpha1
```

## Architecture

This module follows the same architectural patterns as the core redirect module:

- **Extends RedirectRepository**: The `RedirectRegexRepository` extends the core `RedirectRepository` class
- **Service Override**: Uses a service provider to override the `redirect.repository` service
- **Convention-Based**: Regex redirects are identified by source paths starting with `regex:`
- **Backward Compatibility**: All existing redirect functionality continues to work unchanged

## How It Works

1. **Service Override**: The module overrides the `redirect.repository` service to use `RedirectRegexRepository`
2. **Fallback Logic**: `RedirectRegexRepository::findMatchingRedirect()` first checks for regular redirects, then falls back to regex redirects
3. **Efficient Querying**: Only regex redirects (those with `regex:` prefix) are loaded from the database, not all redirects
4. **Multilingual Support**: Patterns are tested against paths both with and without language prefixes
5. **Regex Convention**: Regex redirects are stored as normal redirect entities but with source paths prefixed with `regex:`
6. **Pattern Extraction**: The actual regex pattern is extracted by removing the `regex:` prefix

## Creating Regex Redirects

To create a regex redirect:

1. Go to `/admin/config/search/redirect/add`
2. Set the **From** field to: `regex:your-pattern`
   - Example: `regex:user\/\d+\/profile`
3. Set the **To** field to the redirect target
4. Save the redirect

**Important Notes:**
- **Do NOT include a leading slash** in the regex pattern (e.g., use `user\/\d+\/profile`, not `\/user\/\d+\/profile`)
- The redirect system automatically strips leading slashes before matching
- Remember to escape backslashes in the admin interface (type `\/` for literal `/` in regex)
- For redirects from existing routes, enable "Allow redirects from aliases" in redirect settings
- **Multilingual Support**: Patterns are tested against both the current path and the path with language prefix (e.g., `user/123/profile` and `en/user/123/profile`)

## Integration Points

This module seamlessly integrates with:
- **GraphQL**: Route queries automatically check both redirect types
- **HTTP Redirects**: Request subscribers use the unified repository
- **Admin Interface**: Uses existing redirect admin pages
- **API**: Any code using `redirect.repository` gets regex support automatically

## Dependencies

- `redirect:redirect` (core) - provides the redirect entities and admin interface

## Usage

1. Enable the module: `drush en redirect_regex`
2. Create regex redirects using the standard redirect admin interface with the `regex:` prefix
3. All existing redirect functionality continues to work
4. GraphQL route queries now support regex redirects automatically

## Examples

| Source Path | Redirect Target | Description |
|-------------|----------------|-------------|
| `regex:blog\/\d+\/.*` | `/blog/archive` | Redirect old blog URLs to archive |
| `regex:user\/\d+\/profile` | `/user/profile` | Redirect user profile URLs |
| `regex:page\/old\/([0-9a-z]+)` | `/page/new-page` | Redirect old page URLs with alphanumeric IDs |
