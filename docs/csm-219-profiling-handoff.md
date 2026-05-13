# CSM-219 Profiling Handoff

This branch contains the local profiling setup used for CSM-219.

## Repositories

- Parent DDEV/multisite checkout: `/Users/marthinal/projects/umn-d9`
- Parent remote: `git@github-umn-correct:drupalplatform/d8-composer.git`
- Parent branch: `11.x-prod`
- Site checkout: `/Users/marthinal/projects/umn-d9/docroot/sites/carlsonschool.umn.edu`
- Site remote: `git@github.umn.edu:CarlsonSchool-Web/drupal-8.git`
- Site branch: `CSM-219-profile-homepage-load-time`

## Setup on Another Machine

```bash
ssh -T git@github.umn.edu

cd /Users/marthinal/projects/umn-d9
git switch 11.x-prod
git fetch origin
git merge --ff-only origin/11.x-prod

cd /Users/marthinal/projects/umn-d9/docroot/sites/carlsonschool.umn.edu
git remote set-url origin git@github.umn.edu:CarlsonSchool-Web/drupal-8.git
git fetch origin
git switch CSM-219-profile-homepage-load-time
```

Start and verify DDEV:

```bash
PATH=/opt/homebrew/bin:/usr/local/bin:$PATH ddev start
PATH=/opt/homebrew/bin:/usr/local/bin:$PATH ddev drush @carlsonschool.ddev status
PATH=/opt/homebrew/bin:/usr/local/bin:$PATH ddev drush @carlsonschool.ddev cache:rebuild
```

If the Carlson database is missing, import the local/prod dump before profiling:

```bash
PATH=/opt/homebrew/bin:/usr/local/bin:$PATH ddev import-db --database carlsonschool --file=/path/to/carlsonschool.sql.gz
PATH=/opt/homebrew/bin:/usr/local/bin:$PATH ddev drush @carlsonschool.ddev cache:rebuild
```

## Run Profiling

Use the reusable runner:

```bash
cd /Users/marthinal/projects/umn-d9/docroot/sites/carlsonschool.umn.edu
PATH=/opt/homebrew/bin:/usr/local/bin:$PATH python3 scripts/csm219_profile_pages.py --mode both --page-set full
```

Useful shorter runs:

```bash
PATH=/opt/homebrew/bin:/usr/local/bin:$PATH python3 scripts/csm219_profile_pages.py --mode xhprof --page-set priority
PATH=/opt/homebrew/bin:/usr/local/bin:$PATH python3 scripts/csm219_profile_pages.py --mode sql --page-set priority
```

The script writes timestamped files in the repo root:

- `csm-219-xhprof-profile-results-*.csv`
- `csm-219-xhprof-profile-summary-*.md`
- `csm-219-sql-profile-results-*.csv`
- `csm-219-sql-profile-summary-*.md`

## Existing Artifacts on This Branch

These files capture the first local profiling pass from May 11, 2026:

- `csm-219-local-profile-summary-2026-05-11.md`
- `csm-219-local-profile-results-2026-05-11.csv`
- `csm-219-xhprof-profile-summary-2026-05-11-181311.md`
- `csm-219-xhprof-profile-results-2026-05-11-181311.csv`
- `csm-219-sql-profile-summary-2026-05-11-182838.md`
- `csm-219-sql-profile-results-2026-05-11-182838.csv`

## Notes

- Always run Drush through `ddev drush @carlsonschool.ddev`.
- XHProf adds substantial overhead; use it for call attribution, not production wall time.
- SQL profiling disables XHProf and uses MariaDB's file slow log with `long_query_time=0`.
- The known likely contributors are global nav/simple megamenu rendering, repeated Group access checks, path alias/menu tree queries, and an Acquia Search/Guzzle call on `/news`.
- If the branch is not on `origin`, push it from the source machine before continuing elsewhere:

```bash
git push -u origin CSM-219-profile-homepage-load-time
```
