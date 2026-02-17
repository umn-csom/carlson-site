# ADR-0001: Agent Documentation Strategy

## Status
Accepted

## Date
2026-02-17

---

## Context

This repository represents a single multisite instance within UMN's larger Drupal multisite ecosystem hosted on Acquia Cloud.

**Multisite Structure**:

- **This repository**: `docroot/sites/carlsonschool.umn.edu/` - site-specific code, configuration, and custom modules/themes
- **Docroot location**: `docroot/` is located several directories above this repository root, in a separate repository
- **Shared resources**: `docroot/modules/custom/` and `docroot/themes/custom/` exist outside this repository
- **DDEV configuration**: Managed externally in a shared repository for UMN multisite instances
- **Project root**: Located several directories above this repository, tracked in separate repositories

**Development Environment**:

- Local development uses DDEV (Docker-based)
- DDEV configuration is managed outside this repository (see https://github.umn.edu/Bluespark/umn-d9-ddev)
- Remote environments (dev, staging, production) have no Drush access; database/config sync requires manual download from https://drupalmanagement.umn.edu

**Agent Tooling**:

Contributors use heterogeneous agent tooling (e.g., Cursor, Claude CLI, Codex).

We require a documentation strategy that:

- Provides consistent agent-facing guidance across tools.
- Focuses on project-specific needs rather than generic upstream content.
- Avoids duplication and drift.
- Scales with multisite growth.
- Keeps architectural decisions durable and discoverable.
- Remains fully version-controlled and in-repo.

The upstream baseline from `amazeeio/drupal-agents-md` (DDEV variant) provides generalized Drupal + DDEV guidance. However, maintaining a pristine upstream copy while customizing for project-specific multisite topology, configuration strategy, and workflow guardrails creates unnecessary maintenance burden.

We will customize `AGENTS.md` directly for this project's needs rather than attempting to preserve upstream structure.

---

## Decision

We will adopt a **Customized Baseline Strategy**.

### 1. Canonical Entry Point

The repository root `AGENTS.md` remains the single canonical entrypoint for all agents.

We will:

- Use the upstream amazeeio content as a starting point and reference.
- Customize `AGENTS.md` for this project's specific needs, removing unnecessary generic content.
- Integrate project-specific guidance directly into `AGENTS.md` rather than maintaining separate override files.
- Focus on maintainability and project-specific accuracy over preserving upstream structure.

The `AGENTS.md` file is customized for this multisite project and includes:

- Project-specific DDEV command patterns (e.g., `ddev drush @carlsonschool.ddev`)
- Multisite topology and configuration management details
- Project structure and workflow guardrails
- References to ADRs and planning documents

We do not maintain a pristine upstream copy or attempt to minimize diff size. The file is maintained for this project's needs.

### 2. Project-Specific Agent Documentation

All agent documentation lives in `AGENTS.md`.

### 2.1. Folder-Specific AGENTS.md Files

Both Cursor and Claude CLI support folder-specific `AGENTS.md` files for domain-specific guidance. These files are discovered by walking up the directory tree from the current file's location.

**Precedence Order** (most specific to least specific):

1. **Folder-specific `AGENTS.md`** (in the same directory as the file being edited)
2. **Parent directory `AGENTS.md`** (walking up the directory tree)
3. **Repository root `AGENTS.md`** (fallback for all files)
4. **ADRs** (take precedence over all AGENTS.md files for architectural decisions)

**Guidelines for folder-specific AGENTS.md files**:

- **Purpose**: Provide domain-specific guidance (e.g., module-specific patterns, theme conventions, library usage)
- **Scope**: Should supplement, not duplicate, the root `AGENTS.md`
- **Reference**: Must reference the root `AGENTS.md` for general project guidance
- **Content**: Focus on folder-specific patterns, conventions, and exceptions
- **Maintenance**: Keep minimal and focused; avoid duplicating general guidance

**Example use cases**:

- `modules/custom/my_module/AGENTS.md` - Module-specific patterns and conventions
- `themes/custom/my_theme/AGENTS.md` - Theme-specific styling and component guidelines
- `libraries/highcharts/AGENTS.md` - Library-specific usage patterns

**When NOT to create folder-specific AGENTS.md**:

- To override general project policies (use ADRs instead)
- To duplicate content from root `AGENTS.md`
- For temporary workarounds (document in code comments or ADRs)

### 3. Architecture Decision Records

All architectural decisions live in `docs/adr/`:

- Format: `docs/adr/0001-<decision-name>.md`, `docs/adr/0002-<decision-name>.md`, etc.
- Each ADR must include: Context, Decision, Consequences, Status

**ADRs take precedence over all other agent documentation.** They prevent re-litigation of architectural boundaries by humans or agents.

### 4. Planning Documents (`docs/plan/`)

The `docs/plan/` folder holds planning documents: proposals, implementation plans, consolidation plans, and migration roadmaps. These differ from ADRs:

| Aspect | `docs/adr/` | `docs/plan/` |
|--------|-------------|--------------|
| Purpose | Record accepted architectural decisions | Proposals, plans, phased rollouts. Staging ground for agentic planning. May be issue- or epic-specific (spanning several Jira work items) |
| Status | Accepted (or superseded) | May be draft, in progress, or completed |
| Precedence | Takes precedence over other docs | Not authoritative; may be superseded by ADRs |

**Use `docs/plan/` for**:

- Multi-phase implementation plans (e.g., consolidation, migration)
- Proposals under discussion before an ADR is written
- Roadmaps that span multiple tasks or tickets
- Non-architectural planning (tooling, CI, content migration)

**Do not put in `docs/plan/`**: Architectural decisions that affect code or workflow—those belong in `docs/adr/`. When a plan is adopted, extract the decision into an ADR.

### 5. Optional Tool-Specific Files

Tool-specific files (e.g. `CLAUDE.md`, `.claude/rules/*`, `.cursor/rules/*`) are allowed but must remain **thin shims**:

- They must reference `AGENTS.md`
- No substantial content; redirects only

**Governance model**:
- Agent rules describe how this repo works, not how a specific tool works; leverage *skills* (external and/or custom) for tooling, slash commands, scripts, etc.
- Policy lives in `AGENTS.md` and `docs/adr/`
- Tool-specific files (`CLAUDE.md`, `.cursor/rules/` and `.claude/rules/`) MUST contain only pointers to canonical docs.
