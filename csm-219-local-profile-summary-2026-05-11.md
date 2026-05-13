Status: Draft
Jira: CSM-219
Date: 2026-05-11

# CSM-219 Local Profiling Summary

## Branch And Environment

- Branch: `CSM-219-profile-homepage-load-time`
- Base: `dev` fast-forwarded to `origin/dev` at the start of the work.
- Local URL: `https://carlsonschool.ddev.site`
- Drush alias: `@carlsonschool.ddev`
- PHP profiler extensions available locally: none found for XHProf, Tideways,
  Blackfire, or Xdebug. OPcache is enabled.

## Page Set

The profiling set covers the homepage, the two requested paths, major
View-heavy paths, and at least one published example from each node bundle.

| Purpose | Path |
| --- | --- |
| Homepage/front page | `/` |
| Requested executive education listing | `/executive-education/courses` |
| Requested full-time MBA landing page | `/graduate/mba/full-time` |
| View-heavy program finder | `/graduate` |
| View-heavy news landing/search | `/news` |
| View-heavy faculty directory | `/faculty-research/directory-tenured-tenure-track` |
| View-heavy alumni events | `/alumni/events` |
| Executive education landing page | `/executive-education` |
| Executive education program | `/executive-education/courses/emerging-leaders-bootcamp` |
| News node | `/graduate/resources/what-value-mba-or-masters-degree` |
| Page node | `/about` |
| Faculty profile | `/faculty/bella-yeolim-yoon` |
| Blog entry | `/faculty-research/gary-s-holmes-center-entrepreneurship/blog/broshar` |
| Person | `/person/richele-butler` |
| Video | `/node/107586` |
| Event | `/events/20260508-finance-seminar-alexandre-corhay-university-toronto` |
| Education abroad program | `/education-abroad/programs/i-core-gold-block-prague` |
| Webform node | `/faculty-research/institute-research-marketing/newsletter` |
| Conference | `/conferences/hr-tomorrow/speakers` |
| Student | `/node/129326` |
| Email page | `/em/msmk/i-fit` |
| Alumni default page | `/alumni/events/1st-tuesday` |
| Magazine | `/discovery/fall-2021` |
| Curriculum plan | `/node/118271` |
| Alumni landing page | `/give` |
| Quiz | `/graduate/resources/find-degree` |
| Session | `/conferences/convene/convene-conference-schedule/Payment_Reimbursement` |

## Method

- Rebuilt Drupal caches with
  `ddev drush @carlsonschool.ddev cache:rebuild`.
- Captured one `first_after_cr` request for each route.
- Captured five `warm_same_url` requests for each route.
- Captured three `cache_bust` requests for each route with unique
  `_csm219` query strings.
- Saved raw CSV output to
  `csm-219-local-profile-results-2026-05-11.csv`.

## Early Results

Slowest first request after cache rebuild:

| Path | Total seconds |
| --- | ---: |
| `/` | 4.1036 |
| `/news` | 3.0605 |
| `/executive-education/courses` | 3.0592 |
| `/graduate/mba/full-time` | 3.0481 |
| `/graduate` | 2.7649 |
| `/alumni/events/1st-tuesday` | 2.5609 |
| `/executive-education/courses/emerging-leaders-bootcamp` | 2.5588 |
| `/faculty-research/directory-tenured-tenure-track` | 2.5135 |
| `/node/118271` | 2.4913 |
| `/alumni/events` | 2.3961 |

Slowest cache-busted average:

| Path | Average total seconds |
| --- | ---: |
| `/graduate/mba/full-time` | 0.4729 |
| `/` | 0.3798 |
| `/alumni/events` | 0.3031 |
| `/graduate/resources/what-value-mba-or-masters-degree` | 0.2965 |
| `/graduate/resources/find-degree` | 0.2755 |
| `/discovery/fall-2021` | 0.2652 |
| `/graduate` | 0.2570 |
| `/news` | 0.2021 |
| `/faculty-research/institute-research-marketing/newsletter` | 0.1940 |
| `/executive-education/courses` | 0.1436 |

Slowest warm same-URL average:

| Path | Average total seconds |
| --- | ---: |
| `/news` | 0.2177 |
| `/node/118271` | 0.0438 |
| `/graduate/mba/full-time` | 0.0430 |
| `/faculty-research/gary-s-holmes-center-entrepreneurship/blog/broshar` | 0.0405 |
| `/events/20260508-finance-seminar-alexandre-corhay-university-toronto` | 0.0376 |

## Cacheability Signals

| Path | Cacheability | View/cache markers |
| --- | --- | --- |
| `/` | `x-drupal-cache-max-age: 0`; dynamic cache uncacheable | `front_blocks`, `front_deadlines`, `front_stats`, `event_channels-block_3`, `news_channels-block_3`; `node_list:front_page`, `node_list:event`, `node_list:news` |
| `/graduate/mba/full-time` | `x-drupal-cache-max-age: 0`; dynamic cache uncacheable | `news_channels-block_1`, `faq_question_blocks-block_1`, `node_list:news`, `taxonomy_term_list` |
| `/news` | private/no-cache response policy; dynamic cache uncacheable | `news_hub_search_page`, `news_hub_featured`, `magazine_index`, `search_api_list:acquia_search_index` |
| `/executive-education/courses` | permanent max-age; dynamic cache hit | `executive_ed_program_finder-block_1`, `node_list:executive_ed_program` |
| `/faculty-research/directory-tenured-tenure-track` | permanent max-age; dynamic cache hit | `faculty_directory-block_2`, `node_list:faculty_profile` |
| `/alumni/events` | permanent max-age; dynamic cache miss on sampled request | `event_channels-block_14`, `node_list:event` |

## Initial Interpretation

The local results support the Jira hypothesis that slow requests are tied to
origin/render-cache misses rather than warm steady-state rendering. The
homepage, the two requested pages, `/news`, `/graduate`, and several
View-heavy/node pages are slow immediately after cache rebuild. Warm repeated
requests are generally fast, except `/news` remains noticeably slower and is
explicitly private/no-cache.

The strongest immediate candidates for follow-up are:

1. Homepage cacheability: `front_blocks` exports `max-age: 0`, uses random sort,
   and is embedded repeatedly from `node--front-page.html.twig`.
2. Full-time MBA cacheability: the page is uncacheable and includes
   `news_channels` plus FAQ/webform-related markers.
3. News landing/search cacheability: the response policy is private/no-cache
   and includes Search API/View markers.
4. Broad View cache tags: the heavy pages still expose bundle-level tags such
   as `node_list:news`, `node_list:event`, `node_list:front_page`, and
   `node_list:executive_ed_program`.
