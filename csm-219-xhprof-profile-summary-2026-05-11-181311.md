# CSM-219 XHProf profiling summary (2026-05-11-181311)

## Method

- `cache_bust`: Drupal/framework caches were warmed, each page was requested once with a unique `csm219_profile` query parameter plus no-cache headers, and XHProf collected the request.
- `cold_cr`: XHProf was disabled during `drush cache:rebuild`, then enabled for one profiled page request. This is a worst-case cold Drupal/render-cache run.
- XHProf UI base: https://umn-d9.ddev.site/xhprof

## Slowest cache-busted pages

| Total s | XHProf ms | HTTP | Cache | Dynamic | Label | Path | Run |
|---:|---:|---:|---|---|---|---|---|
| 5.962487 | 5991.5 | 200 | MISS | MISS | graduate landing page | `/graduate` | [6a02003d21d4a](https://umn-d9.ddev.site/xhprof/index.php?run=6a02003d21d4a&source=ddev) |
| 5.896197 | 5927.1 | 200 | UNCACHEABLE (response policy) | UNCACHEABLE (poor cacheability) | news hub view | `/news` | [6a020043775ff](https://umn-d9.ddev.site/xhprof/index.php?run=6a020043775ff&source=ddev) |
| 5.625628 | 5654.2 | 200 | MISS | MISS | executive education landing | `/executive-education` | [6a0200555cdec](https://umn-d9.ddev.site/xhprof/index.php?run=6a0200555cdec&source=ddev) |
| 5.482544 | 5502.8 | 200 | MISS | MISS | alumni events view | `/alumni/events` | [6a02004f537a4](https://umn-d9.ddev.site/xhprof/index.php?run=6a02004f537a4&source=ddev) |
| 5.481639 | 5511.8 | 200 | MISS | MISS | faculty directory view | `/faculty-research/directory-tenured-tenure-track` | [6a02004965da5](https://umn-d9.ddev.site/xhprof/index.php?run=6a02004965da5&source=ddev) |
| 5.469856 | 5500 | 200 | MISS | MISS | executive ed program node | `/executive-education/courses/emerging-leaders-bootcamp` | [6a02005b43814](https://umn-d9.ddev.site/xhprof/index.php?run=6a02005b43814&source=ddev) |
| 5.409883 | 5431.2 | 200 | MISS | MISS | about page | `/about` | [6a020062dc43a](https://umn-d9.ddev.site/xhprof/index.php?run=6a020062dc43a&source=ddev) |
| 5.260897 | 5296 | 200 | MISS | MISS | give page | `/give` | [6a02007d0830c](https://umn-d9.ddev.site/xhprof/index.php?run=6a02007d0830c&source=ddev) |
| 5.226211 | 5245.2 | 200 | MISS | MISS | alumni default page | `/alumni/events/1st-tuesday` | [6a0200754fefd](https://umn-d9.ddev.site/xhprof/index.php?run=6a0200754fefd&source=ddev) |
| 5.058715 | 5130.7 | 200 | MISS | UNCACHEABLE (poor cacheability) | degree finder view | `/graduate/resources/find-degree` | [6a0200827f925](https://umn-d9.ddev.site/xhprof/index.php?run=6a0200827f925&source=ddev) |
| 1.863147 | 1900.5 | 200 | MISS | UNCACHEABLE (poor cacheability) | homepage | `/` | [6a020033ee513](https://umn-d9.ddev.site/xhprof/index.php?run=6a020033ee513&source=ddev) |
| 1.532068 | 1504.6 | 200 | MISS | UNCACHEABLE (poor cacheability) | requested full-time mba | `/graduate/mba/full-time` | [6a020036b6309](https://umn-d9.ddev.site/xhprof/index.php?run=6a020036b6309&source=ddev) |

## Full-cold priority pages

| Total s | XHProf ms | HTTP | Cache | Dynamic | Label | Path | Run |
|---:|---:|---:|---|---|---|---|---|
| 11.961799 | 12012.2 | 200 | MISS | UNCACHEABLE (poor cacheability) | requested full-time mba | `/graduate/mba/full-time` | [6a0200d6519dd](https://umn-d9.ddev.site/xhprof/index.php?run=6a0200d6519dd&source=ddev) |
| 11.500536 | 11595.2 | 200 | MISS | UNCACHEABLE (poor cacheability) | homepage | `/` | [6a02009f29ef3](https://umn-d9.ddev.site/xhprof/index.php?run=6a02009f29ef3&source=ddev) |
| 11.157507 | 10945.8 | 200 | MISS | MISS | graduate landing page | `/graduate` | [6a02010878a8d](https://umn-d9.ddev.site/xhprof/index.php?run=6a02010878a8d&source=ddev) |
| 10.894045 | 10975.6 | 200 | MISS | MISS | requested executive education courses | `/executive-education/courses` | [6a0200baaa89d](https://umn-d9.ddev.site/xhprof/index.php?run=6a0200baaa89d&source=ddev) |
| 9.998928 | 9848.2 | 200 | UNCACHEABLE (response policy) | UNCACHEABLE (poor cacheability) | news hub view | `/news` | [6a0200ef74e43](https://umn-d9.ddev.site/xhprof/index.php?run=6a0200ef74e43&source=ddev) |

## Top exclusive hotspots by selected slow pages

### cache_bust - graduate landing page (`/graduate`)
Run: [6a02003d21d4a](https://umn-d9.ddev.site/xhprof/index.php?run=6a02003d21d4a&source=ddev)

- 1081.8ms PDOStatement::execute
- 467.2ms usleep
- 170.6ms Drupal\Component\Assertion\Inspector::assertAll
- 160.2ms Drupal\Component\DependencyInjection\Container::get
- 98.9ms Drupal\Core\Cache\Context\CacheContextsManager::optimizeTokens

### cache_bust - news hub view (`/news`)
Run: [6a020043775ff](https://umn-d9.ddev.site/xhprof/index.php?run=6a020043775ff&source=ddev)

- 1054.7ms PDOStatement::execute
- 572.4ms curl_exec
- 486.4ms usleep
- 162.1ms Drupal\Component\Assertion\Inspector::assertAll
- 127.6ms Drupal\Component\DependencyInjection\Container::get

### cache_bust - executive education landing (`/executive-education`)
Run: [6a0200555cdec](https://umn-d9.ddev.site/xhprof/index.php?run=6a0200555cdec&source=ddev)

- 1172.1ms PDOStatement::execute
- 481.7ms usleep
- 168.4ms Drupal\Component\Assertion\Inspector::assertAll
- 139.5ms Drupal\Component\DependencyInjection\Container::get
- 95.7ms Drupal\Core\Cache\Context\CacheContextsManager::optimizeTokens

### cache_bust - alumni events view (`/alumni/events`)
Run: [6a02004f537a4](https://umn-d9.ddev.site/xhprof/index.php?run=6a02004f537a4&source=ddev)

- 1151.9ms PDOStatement::execute
- 476.6ms usleep
- 165ms Drupal\Component\Assertion\Inspector::assertAll
- 133.1ms Drupal\Component\DependencyInjection\Container::get
- 93.5ms Drupal\Core\Cache\Context\CacheContextsManager::optimizeTokens

### cache_bust - faculty directory view (`/faculty-research/directory-tenured-tenure-track`)
Run: [6a02004965da5](https://umn-d9.ddev.site/xhprof/index.php?run=6a02004965da5&source=ddev)

- 1115.4ms PDOStatement::execute
- 543.8ms usleep
- 166.3ms Drupal\Component\Assertion\Inspector::assertAll
- 139ms Drupal\Component\DependencyInjection\Container::get
- 97.1ms Drupal\Core\Cache\Context\CacheContextsManager::optimizeTokens

### cache_bust - executive ed program node (`/executive-education/courses/emerging-leaders-bootcamp`)
Run: [6a02005b43814](https://umn-d9.ddev.site/xhprof/index.php?run=6a02005b43814&source=ddev)

- 1029.3ms PDOStatement::execute
- 493.6ms usleep
- 166ms Drupal\Component\Assertion\Inspector::assertAll
- 142.6ms Drupal\Component\DependencyInjection\Container::get
- 95.2ms Drupal\Core\Cache\Context\CacheContextsManager::optimizeTokens

### cache_bust - about page (`/about`)
Run: [6a020062dc43a](https://umn-d9.ddev.site/xhprof/index.php?run=6a020062dc43a&source=ddev)

- 1107.4ms PDOStatement::execute
- 485.7ms usleep
- 171.2ms Drupal\Component\Assertion\Inspector::assertAll
- 141ms Drupal\Component\DependencyInjection\Container::get
- 97.5ms Drupal\Core\Cache\Context\CacheContextsManager::optimizeTokens

### cache_bust - give page (`/give`)
Run: [6a02007d0830c](https://umn-d9.ddev.site/xhprof/index.php?run=6a02007d0830c&source=ddev)

- 1090.5ms PDOStatement::execute
- 480.7ms usleep
- 164.6ms Drupal\Component\Assertion\Inspector::assertAll
- 131.9ms Drupal\Component\DependencyInjection\Container::get
- 90.8ms Drupal\Core\Cache\Context\CacheContextsManager::optimizeTokens

### cold_cr - requested full-time mba (`/graduate/mba/full-time`)
Run: [6a0200d6519dd](https://umn-d9.ddev.site/xhprof/index.php?run=6a0200d6519dd&source=ddev)

- 2115.9ms PDOStatement::execute
- 538.3ms usleep
- 385.3ms gc_collect_cycles
- 294.6ms Composer\Autoload\{closure}
- 206.2ms Drupal\Component\DependencyInjection\Container::get

### cold_cr - homepage (`/`)
Run: [6a02009f29ef3](https://umn-d9.ddev.site/xhprof/index.php?run=6a02009f29ef3&source=ddev)

- 2333.4ms PDOStatement::execute
- 502.2ms usleep
- 384.9ms gc_collect_cycles
- 295.6ms Composer\Autoload\{closure}
- 196.8ms Drupal\Component\DependencyInjection\Container::get

### cold_cr - graduate landing page (`/graduate`)
Run: [6a02010878a8d](https://umn-d9.ddev.site/xhprof/index.php?run=6a02010878a8d&source=ddev)

- 2036ms PDOStatement::execute
- 477.1ms usleep
- 280.6ms Composer\Autoload\{closure}
- 198.1ms Drupal\Component\DependencyInjection\Container::get
- 177.2ms Drupal\Component\Assertion\Inspector::assertAll

### cold_cr - requested executive education courses (`/executive-education/courses`)
Run: [6a0200baaa89d](https://umn-d9.ddev.site/xhprof/index.php?run=6a0200baaa89d&source=ddev)

- 2011.3ms PDOStatement::execute
- 513.8ms usleep
- 399.1ms gc_collect_cycles
- 284.3ms Composer\Autoload\{closure}
- 197.7ms Drupal\Component\Assertion\Inspector::assertAll

### cold_cr - news hub view (`/news`)
Run: [6a0200ef74e43](https://umn-d9.ddev.site/xhprof/index.php?run=6a0200ef74e43&source=ddev)

- 1386.9ms PDOStatement::execute
- 515.9ms curl_exec
- 479.3ms usleep
- 306.4ms Composer\Autoload\{closure}
- 283.2ms gc_collect_cycles

Raw CSV: `csm-219-xhprof-profile-results-2026-05-11-181311.csv`
