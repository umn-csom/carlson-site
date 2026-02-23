# ADR-0002: Remote Environment Management and Deployment Workflows

## Status
Accepted

## Date
2026-02-17

---

## Context

This project is hosted on UMN's Acquia Cloud multisite platform. Unlike typical Drupal hosting environments, developers and release managers, do not have command-line access to remote environments (dev, staging, production). Only UMN Organizational IT (OIT) has server access.

This constraint fundamentally changes how we:
- Access databases from remote environments
- Deploy code and configuration changes
- Run database updates and migrations
- Execute administrative tasks

Without CLI access, standard Drupal deployment workflows (drush commands, config:import via CLI, deploy hooks) are unavailable on remote environments.

---

## Decision

We will adopt a **UI-Based Remote Management Strategy** with the following constraints and workflows:

### Remote Environment Access

**Note**: These constraints apply only to remote environments (DEV/STG/PROD). Local DDEV environment supports full drush commands (`drush updb`, `drush cim`, `drush cex`, etc.).

**No CLI Access**: Developers cannot SSH into remote environments or execute drush commands directly.

**Database Access**: Database downloads from DEV, STG, and PROD environments are available through the Drupal Management Portal at https://drupalmanagement.umn.edu

**Configuration Management**: Configuration synchronization must be performed through the Drupal admin UI (`/admin/config/development/configuration`), not via CLI commands.

### Deployment Workflows

#### DEV and STG Environments

Deployments to DEV and STG environments are managed through the Drupal Management Portal at https://drupalmanagement.umn.edu

**Process**:
1. Code changes are committed and pushed to the repository
2. Deployments are triggered via the portal interface
3. Database updates run automatically during deployment via `hook_update_N` hooks
4. Configuration changes must be imported manually through the Drupal admin UI after deployment

#### PROD Environment

Deployments to PROD require filing a support ticket with OIT.

**Process**:
1. Code changes are committed and pushed to the repository
2. A support ticket must be filed via:
   - Email: ucm@umn.edu
   - Portal: https://tdx.umn.edu/TDClient/31/Portal/Requests/TicketRequests/NewForm?ID=2%7ewafhsAc-4_&RequestorType=Service
3. OIT performs the deployment
4. Database updates run automatically during deployment via `hook_update_N` hooks
5. Configuration changes must be imported manually through the Drupal admin UI after deployment

### Code and Configuration Constraints (Remote Environments Only)

**Note**: These constraints apply only to remote environments. Local DDEV supports standard drush workflows.

**Update Hooks**: All database schema changes, data migrations, and programmatic updates must be implemented in `hook_update_N` functions. Deploy hooks (`hook_deploy_NAME`) are not available since `drush deploy` cannot be executed on remote environments.

**Configuration Sync**: Configuration must be exported locally using `drush cex`, committed to the repository, and then imported manually through the Drupal admin UI (`/admin/config/development/configuration`) on remote environments.

**Custom Drush Commands**: Custom drush commands cannot be executed on remote environments. All administrative tasks must be:
- Built into `hook_update_N` functions for one-time migrations
- Implemented as custom modules with admin UI forms/controllers for reusable tasks
- Accessible through the Drupal admin interface

**Reusable Scripts**: For tasks that need to be run multiple times or on-demand, create custom modules with admin UI controllers that trigger the scripts. See `modules/custom/carlson_general/carlson_general.install` for examples of script execution patterns.

---

## Consequences

### Positive

- **Security**: No CLI access reduces attack surface
- **Consistency**: All deployments go through controlled processes
- **Auditability**: Portal-based deployments provide audit trails

### Negative

- **Slower Deployments**: PROD deployments require ticket filing and OIT coordination
- **Manual Configuration**: Configuration sync cannot be automated
- **Limited Tooling**: Standard Drupal deployment tools (drush deploy, config:import via CLI) are unavailable
- **Development Overhead**: Must build admin UI for reusable tasks instead of using drush commands

### Required Patterns (Remote Environments Only)

1. **All database changes** must be in `hook_update_N` functions (on remote; local can use `drush updb`)
2. **All configuration changes** must be manually imported via admin UI after deployment (on remote; local can use `drush cim`)
3. **Reusable administrative tasks** must have admin UI interfaces (on remote; local can use drush commands)
4. **Update hook docblocks** should not include `@param` or `@return` annotations (see AGENTS.md coding guardrails)

### Workflow Implications

- Developers must test configuration imports locally before committing
- Update hooks must be idempotent and safe to run multiple times
- Custom modules for administrative tasks must include proper access control
- Deployment coordination with OIT is required for PROD changes

---

## References

- Drupal Management Portal: https://drupalmanagement.umn.edu
- OIT Support Portal: https://tdx.umn.edu/TDClient/31/Portal/Requests/TicketRequests/NewForm?ID=2%7ewafhsAc-4_&RequestorType=Service
- OIT Support Email: ucm@umn.edu
