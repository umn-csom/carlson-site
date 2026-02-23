# AGENTS.md: AI Agent Guide for Carlson School Drupal Multisite

**AI Agent Instructions**: This guide provides instructions for AI coding agents working on the Carlson School of Management Drupal multisite. Human contributors should use README.md instead.

## Project Context & Structure

### Project Overview

- **Platform**: Drupal running inside UMN's customized multisite hosting platform on Acquia Cloud
- **Docroot**: `docroot/` is located several folders above the current repository
- **Sites folder**: This repository represents a single multisite instance at `docroot/sites/carlsonschool.umn.edu/`
- **Core Technology**: Drupal 10.x+
- **Development Environment**: DDEV (Docker-based) - configuration managed outside this repository at [Bluespark UMN DDEV config][]
- **Development Tools**: Composer, Drush 12+, Git, DDEV CLI

### Multisite Execution Context

This project is part of a larger multisite instance hosted on Acquia. The DDEV project root and docroot folders are located several directories above the git repository root, tracked in separate repositories that we do not have control over. Each developer has control over the top-level `.ddev/` folder, but we recommend using the shared repository at [Bluespark UMN DDEV config][] which comes preconfigured to support several UMN Drupal projects.

**Command Pattern**: All drush commands must be prefixed with `ddev drush @carlsonschool.ddev` (not bare `drush`). Commands must target the specific local development site alias.

**Note**: In the documentation below, drush commands are shown without the prefix for brevity. Always prepend `ddev drush @carlsonschool.ddev` when executing.

### Project Structure

- **Shared modules/themes**: `docroot/modules/custom/`, `docroot/themes/custom/` - outside this repo, controlled by OIT in [composer upstream][] defined in project root `composer.json` one level above `docroot/`.  UMN Provides a default Folwell theme and supporting paragraphs based component system, but this site does not use that theme or paragraph types.
- **Modules**: `modules/custom/`, `modules/contrib/` - site-specific, inside this repo; we must commit versioned snapshots of contrib modules that are not provided by the upstream OIT composer.json
- **Themes**: `themes/custom/`, `themes/contrib/` (site-specific, inside this repo). The Carlson School has a custom-built theme based on the bootstrap_barrio contrib theme.
- **Config**: `config/sync/` – each site-specific repo has unique and separate config; DO NOT use environment-specific config_split
- **Site-specific location**: `docroot/sites/carlsonschool.umn.edu/` (location of current multisite repository instance within larger UMN multisite ecosystem)
- **Drush aliases**: `drush/sites/*.site.yml` (outside this repo)

### Environments

| Drush Alias         | URL                       | Description             | Pinned branch      | Upstream branch ([composer upstream][]) |
|---------------------|---------------------------|-------------------------|--------------------|-----------------------------------------|
| @carlsonschool.ddev | carlsonschool.ddev.site   | Local development       | `dev` or *feature* | `11.x-prod` or `11.x-dev`               |
| (no drush access)   | carlsonschool.dev.umn.edu | Development environment | `dev` or *feature* | `11.x-dev`                              |
| (no drush access)   | carlsonschool.stg.umn.edu | Staging environment     | `master`           | `11.x-build`                            |
| (no drush access)   | carlsonschool.umn.edu     | Production environment  | `master`           | `11.x-prod`                             |

**Remote Environment Constraints**: See [ADR-0002](docs/adr/0002-remote-environment-management.md) for complete details on remote environment management, deployment workflows, and constraints. Key points: no CLI access to remote environments, config sync via admin UI only, all database changes via `hook_update_N` functions.

### ADRs and Planning

- **ADRs** (`docs/adr/`):
  - Architecture Decision Records define durable architectural constraints, in Markdown.
  - ADRs take precedence over all other documentation.
  - Review ADRs before introducing structural changes.

- **Planning docs** (`docs/plan/`):
  - Planning docs define explicit tasks to complete a new feature, tied to one or more Jira work items.
  - Planning docs are ignored (via docs/plan/.gitignore) by default. Use forced commit when work is expected to be long-lived, affects multiple sites, modules, environments, or spans multiple pull requests. Confirm with human when being asked to commit changes whether to commit plan documents.
  - Planning docs must include context, scope, rollout strategy, and risks.
  - Use date-based prefixes (YYYY-MM or YYYY-MM-DD) in plan filename; optionally, include Jira ticket number in filename.
  - Include front-matter: `Status` (Draft|Active|Completed|Superseded|Abandoned) and `Supersedes|Superseded-by` if applicable.
  - During development, promote durable decisions into an ADR.
  - Once complex work is near completion (eg. final PR / ask human if unsure) planning docs may be removed from git (and left in developer's local working tree).

## Git Workflow

- **Format**: `CSM-XXX: Brief descriptive title` (Jira ticket prefix)
- **Remote**: Push to `origin` at `github.umn.edu`
- **No AI attribution**: Do not add Agent signatures or co-authorship to commits. Responsibility for review lies on the human before commit

## Development Environment (DDEV + Drush)

**Note**: DDEV configuration is managed several levels above this repository in the .ddev/ folder which is tracked in a shared repository at [Bluespark UMN DDEV config][]. These commands work with the existing DDEV setup.

### DDEV Commands

```bash
# Environment management
ddev start                # Start development environment
ddev stop                 # Stop environment
ddev restart              # Restart environment
ddev delete               # Delete environment (careful!)

# Database operations
ddev snapshot --name $(date '+%Y-%m-%d-%H-%M-%S')--CSM-XXX-brief-description # Save database snapshot (WARNING: includes all multisite projects, use with caution)
ddev restore-snapshot YYYY-MM-DD--CSM-XX-brief-descriptio # Restore from a snapshot (WARNING: overwrites ALL multisite projects, use with caution)
ddev import-db --database carlsonschool --file=path/to/database.sql.gz # Import database from file
ddev export-db --database carlsonschool --file=path/to/CSM-XXX-$(date '+%Y-%m-%d-%H-%M-%S')--brief-description.sql --gzip # Export database to file

# Development tools
ddev exec <command>       # Execute command in container
ddev ssh                  # SSH into web container
ddev logs                 # View container logs
ddev logs -f web          # Follow web container logs
ddev logs -f db           # Follow database container logs
ddev describe             # Show environment details and status
ddev launch               # Open site in browser

# Container debugging
ddev exec tail -f /var/log/apache2/error.log # Access PHP error logs
ddev exec php -r "kint(\Drupal::config('system.site')->get());" # Debugging functions (use with devel module)
```

### Essential Drush Commands

**Note**: All drush commands must be prefixed with `ddev drush @carlsonschool.ddev`. Commands below are shown without the prefix for brevity.

```bash
# Status and information
status                    # Show Drupal root, site path, database connection, Drush version
core-status               # More detailed status information

# Cache management
cache:rebuild             # Rebuild all caches (equivalent to "drush cc all" in D7)

# Configuration management
config:export             # Export active config to sync directory
config:import -y          # Import configuration from sync directory
config:get <name>         # Show a single configuration value (e.g., config:get system.site)
config:set <name> <key> <value> # Temporarily change a config value without using the UI
config:delete <name>      # Remove a config object

# Database operations
updatedb                  # Run database updates (hook_update_N functions)

# Module management
pm:list --type=module --status=enabled # List enabled modules
pm:enable <module>       # Enable a module
pm:uninstall <module>    # Fully uninstall a module (removes config and data)
```

### Drush Debugging Commands

Debugging commands that require additional context or are less commonly used:

#### Logging & Watchdog

| Command | Purpose |
|----------------------------------|-------------------------------------------------------------------------|
| `watchdog:show` | Lists recent log messages (dblog entries). Supports filters: `--severity=Error`, `--type=php`, etc. |
| `watchdog:delete all` | Clears the watchdog log. Useful when logs become huge and slow down watchdog operations. |
| `sql:query "SELECT * FROM watchdog ORDER BY wid DESC LIMIT 50"` | Direct SQL access to logs when the database is very large. Faster than watchdog:show on sites with millions of log entries. |

#### Advanced Cache Debugging

| Command | Purpose |
|----------------------------------|-------------------------------------------------------------------------|
| `cache:get <bin>:<cid>` | Retrieve a specific cache item (e.g., `cache:get config:core.extension`). |
| `cache:clear <bin>` | Clear only one cache bin (render, config, discovery, etc.). |

#### Database & Entity Debugging

| Command | Purpose |
|----------------------------------------------|-------------------------------------------------------------------------|
| `sql:connect` | Outputs the CLI command to connect to the DB (useful for manual queries). |
| `sql:query` | Run arbitrary SQL. |
| `sql:query --db-prefix` | See queries with table prefixes expanded (helps reading raw SQL). |
| `entity:info` | Show entity type definitions (useful when entity schema errors occur). |
| `php` | Opens an interactive PHP shell with Drupal bootstrapped. |
| `php:eval "code"` | Execute arbitrary PHP code in Drupal context. Example: `php:eval "dpm(\Drupal::state()->get('system.cron_last'));"` (with Devel) |

#### Development & Error Reproduction

| Command | Purpose |
|----------------------------------------|-------------------------------------------------------------------------|
| `php:eval "var_dump(function_exists('my_problematic_function'));"` | Quick test if a function exists or what it returns. |
| `state:edit` / `state:get/set/delete` | Inspect or override Drupal state values (often used by broken modules). |
| `variable:get/set/delete` | Legacy equivalent of state commands (D7 only). |
| `twig:debug` | Turn Twig debugging on/off and verify template suggestions. |
| `eval` | Alias of php:eval. |
| `site:status` | System status check. |
| `theme:debug` | Lists all theme suggestions for a given route or render array (Drupal 9.4+). |
| `pm:uninstall` | Without arguments → interactive mode for disabling suspected problematic modules quickly. |

#### Performance Profiling

| Command | Purpose |
|----------------------------------------|-------------------------------------------------------------------------|
| `sql:query "EXPLAIN ANALYZE SELECT ..."` | Query analysis for performance debugging. |
| Enable Devel + `kint` or `dpm()` in code → instant output in terminal. |
| Use Webprofiler module for detailed profiling at `/admin/config/development/devel/webprofiler` |


## Drupal Development

## Site-specific Development Patterns

- **Custom module prefix**:  `carlson_*`
- Do NOT use *features*; this is legacy functionality.

### Drupal Development Patterns

#### Services & Dependency Injection
- **Create services** in `modulename.services.yml` file for reusable logic
- **Use dependency injection** to inject services into controllers, forms, and plugins
- **Core services** like `@current_user`, `@entity_type.manager`, `@database` are available
- **Best practice**: Avoid static `\Drupal::` calls in favor of dependency injection
- **Service discovery**: Use `php:eval "print_r(\Drupal::getContainer()->getServiceIds());"` to see available services
- **Location**: Place service classes in `src/` directory with proper namespace

#### Entity API & Queries
- **Entity loading**: Use `Entity::load($id)` for single entities or `entityTypeManager()->getStorage()` for multiple
- **Entity queries**: Use `\Drupal::entityQuery()` for database operations instead of raw SQL
- **Query conditions**: Chain multiple conditions with `->condition()`, `->sort()`, `->range()`
- **Entity creation**: Create entities with `Entity::create(['type' => 'bundle_name'])`
- **Field access**: Use entity field API instead of direct property access
- **Performance**: Use entity query cache tags and contexts for optimal caching

#### Plugin System
- **Plugin types**: Blocks, field formatters, field widgets, menu links, and more
- **Plugin discovery**: Use annotation-based discovery in docblocks
- **Plugin configuration**: Define plugin ID, label, and other metadata in annotations
- **Plugin base classes**: Extend appropriate base classes (BlockBase, FormatterBase, etc.)
- **Plugin placement**: Place plugins in `src/Plugin/Type/` directory structure
- **Derivative plugins**: Use for creating multiple plugins from one definition

#### Hooks
- **Hook implementation**: Implement hooks in `modulename.module` file
- **Hook naming**: Follow pattern `hook_modulename_action()` for custom hooks
- **Hook parameters**: Use type hints and proper parameter documentation
- **Core hooks**: Common hooks include `hook_form_alter()`, `hook_theme()`, `hook_menu_links_discovered_alter()`
- **Hook order**: Hooks fire in module weight order (lowest first)
- **Best practice**: Keep hook implementations focused and use services for complex logic

#### Forms API
- **Form classes**: Extend `FormBase` for simple forms or `ConfigFormBase` for configuration forms
- **Form structure**: Use render array structure with `#type`, `#title`, `#description` properties
- **Form validation**: Implement `validateForm()` method for custom validation
- **Form submission**: Implement `submitForm()` method for processing form data
- **Form elements**: Use proper form element types (textfield, select, checkbox, etc.)
- **AJAX forms**: Add `#ajax` property to form elements for dynamic behavior

#### Routes & Controllers
- **Routing file**: Define routes in `modulename.routing.yml` with path, defaults, and requirements
- **Controllers**: Create controller classes extending `ControllerBase` in `src/Controller/`
- **Route parameters**: Use `{parameter}` placeholders in paths and inject into controller methods
- **Access control**: Implement `_permission`, `_role`, or custom access callbacks
- **Route naming**: Use `modulename.action` naming convention for clarity
- **Controller injection**: Use constructor injection for dependencies
- **Return values**: Return render arrays or Symfony Response objects

### Code Style and Standards

Adhere to Drupal coding standards (PSR-12 with Drupal extensions). Use Coder and PHPCS for enforcement.

- **PHP**:
  - Indentation: 2 spaces (no tabs)
  - Line length: ≤ 80 characters
  - Naming: CamelCase classes/methods, snake_case variables/functions
  - Always use braces; prefer early returns
  - Full PHPDoc blocks with `@param`, `@return`, `@throws`

- **YAML**: 2-space indentation, lowercase keys
- **Twig**: `{{ }}` for output, `{% %}` for logic; always escape with `|e`

**Reject any code that fails Drupal Coder sniffs.**

### Coding Guardrails

- Focus changes only on code relevant to the task
- Avoid unrelated refactors
- Write thorough automated tests for significant functionality
- Prefer simple solutions
- Avoid duplication; search for existing implementations first
- Keep files under ~300 lines; refactor when exceeded
- Do not introduce new architectural patterns unless explicitly instructed
- Never introduce mock/stub logic into dev or prod runtime code
- Never overwrite `.env`, `settings.local.php` or other untracked files without confirmation
- Never delete untracked files or files with unstaged changes without confirmation
- **Update hooks**: Do not add `@param` or `@return` to docblocks for update hooks (`hook_update_N`). See [ADR-0002](docs/adr/0002-remote-environment-management.md) for remote environment constraints.

### Security & Performance Guidelines

#### Security Requirements
- **Always sanitize user input**: Use `#plain_text` for untrusted content
- **CSRF protection**: Include `#token` for forms with side effects
- **Permissions**: Implement proper access checks and route requirements
- **SQL Injection**: Use Entity Query or proper parameter binding
- **XSS Prevention**: Always use `|e` filter in Twig, `#markup` for trusted HTML only

#### Performance Best Practices
- **Render caching**: Always add `#cache` array to render arrays with appropriate `tags` and `contexts`
- **Cache tags**: Use entity-based tags like `['node:123']` or list-based tags like `['node_list']`
- **Cache contexts**: Apply user-specific contexts like `['user.roles']` for personalized content
- **Lazy loading**: Use `#lazy_builder` for expensive operations that can be loaded separately
- **Placeholder strategy**: Set `#create_placeholder: TRUE` for lazy builders to improve initial page load
- **Cache max-age**: Set appropriate `max-age` values based on content freshness requirements
- **Avoid premature optimization**: Profile first, then optimize based on actual bottlenecks
- **Database queries**: Use entity queries instead of raw SQL for better caching and security

#### Caching Strategies
- **Render cache**: Cache complex markup with proper tags/contexts
- **Dynamic page cache**: Configure for anonymous users
- **Internal page cache**: Enable for authenticated users
- **Entity cache**: Leverage core entity caching
- **Redis/Memcache**: Configure for distributed caching

## Testing

### PHPUnit Testing Framework
Aim for ≥ 80% code coverage. Drupal provides multiple test types:

```bash
# Run all tests
ddev exec vendor/bin/phpunit

# Run specific test suites
ddev exec vendor/bin/phpunit --testsuite unit
ddev exec vendor/bin/phpunit --testsuite kernel
ddev exec vendor/bin/phpunit --testsuite functional

# Run specific tests
ddev exec vendor/bin/phpunit --filter MyModuleUnitTest
```

### Test Types
- **Unit Tests**: Test individual classes and methods in isolation (fastest)
- **Kernel Tests**: Test Drupal interactions with minimal Drupal environment
- **Functional Tests**: Test complete user interactions through browser simulation (slowest)

## Additional Resources

### DDEV Documentation
- **DDEV Official Docs**: https://ddev.readthedocs.io
- **DDEV Quick Start**: https://ddev.readthedocs.io/en/stable/users/quickstart/
- **DDEV Drupal Guide**: https://ddev.readthedocs.io/en/stable/users/topics/drupal/

### Drupal Documentation
- **Drupal API**: https://api.drupal.org
- **Developer Guide**: https://www.drupal.org/docs/develop
- **Coding Standards**: https://www.drupal.org/docs/develop/standards
- **Security Best Practices**: https://www.drupal.org/docs/develop/security

### Community Resources
- **DrupalAtYourFingertips**: https://www.drupalatyourfingertips.com
- **Drupal Answers**: https://drupal.stackexchange.com
- **Drupal.org**: https://www.drupal.org
- **Drupal Slack**: https://drupal.slack.com

[Bluespark UMN DDEV config]: https://github.umn.edu/Bluespark/umn-d9-ddev
[OIT Drupal Management Portal]: https://drupalmanagement.umn.edu
[composer upstream]: https://github.umn.edu/drupalplatform/d8-composer
