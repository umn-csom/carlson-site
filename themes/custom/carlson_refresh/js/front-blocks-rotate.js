/**
 * @file
 * Client-side rotation for front_blocks view pools on the homepage.
 */
(function () {
  'use strict';

  var BUCKET_MS = 300000;
  var POOL_SALTS = {
    top_feature: 'top_feature',
    feature_news: 'feature_news',
    feature_events: 'feature_events',
    feature_discover: 'feature_discover',
  };

  /**
   * Deterministic index from time bucket seed and pool salt.
   */
  function seededIndex(seed, salt, length) {
    var hash = 0;
    var str = seed + ':' + salt;
    var i;

    for (i = 0; i < str.length; i++) {
      hash = ((hash << 5) - hash) + str.charCodeAt(i);
      hash |= 0;
    }

    return Math.abs(hash) % length;
  }

  /**
   * Resolve a linked node ID for top-feature deduplication.
   */
  function getLinkNid(item) {
    var nid = item.getAttribute('data-link-nid');
    if (nid) {
      return nid;
    }

    var link = item.querySelector('a[href*="/node/"]');
    if (!link) {
      return null;
    }

    var match = link.getAttribute('href').match(/\/node\/(\d+)/);
    return match ? match[1] : null;
  }

  /**
   * Activate one item in a pool.
   */
  function activateItem(items, index) {
    if (!items.length) {
      return null;
    }

    var safeIndex = index % items.length;
    items[safeIndex].classList.add('is-active');
    items[safeIndex].setAttribute('aria-hidden', 'false');
    return items[safeIndex];
  }

  var HERO_LOAD_TIMEOUT_MS = 4000;

  /**
   * Mark a pool ready and hide inactive items.
   */
  function markPoolReady(pool) {
    pool.classList.add('is-ready');
    pool.querySelectorAll('.front-blocks-item:not(.is-active)').forEach(function (item) {
      item.setAttribute('aria-hidden', 'true');
    });
  }

  /**
   * Wait for images in the active hero item, then reveal content.
   */
  function revealHeroWhenReady(pool) {
    var active = pool.querySelector('.front-blocks-item.is-active');
    var finished = false;

    function finish() {
      if (finished) {
        return;
      }
      finished = true;
      pool.classList.add('is-loaded');
      var skeleton = pool.querySelector('.front-top-features-skeleton');
      if (skeleton) {
        skeleton.setAttribute('aria-hidden', 'true');
      }
    }

    var timeoutId = window.setTimeout(finish, HERO_LOAD_TIMEOUT_MS);

    if (!active) {
      window.clearTimeout(timeoutId);
      finish();
      return;
    }

    var images = active.querySelectorAll('img');
    if (!images.length) {
      window.clearTimeout(timeoutId);
      finish();
      return;
    }

    var pending = 0;
    images.forEach(function (image) {
      if (image.complete && image.naturalWidth > 0) {
        return;
      }
      pending++;
      function done() {
        image.removeEventListener('load', done);
        image.removeEventListener('error', done);
        pending--;
        if (pending === 0) {
          window.clearTimeout(timeoutId);
          finish();
        }
      }
      image.addEventListener('load', done);
      image.addEventListener('error', done);
    });

    if (pending === 0) {
      window.clearTimeout(timeoutId);
      finish();
    }
  }

  /**
   * Current 5-minute rotation seed.
   */
  function rotationSeed() {
    return Math.floor(Date.now() / BUCKET_MS);
  }

  /**
   * Initialize the above-the-fold top feature pool synchronously.
   *
   * Called from an inline script immediately after the hero markup is parsed,
   * so the selected item is shown before first paint (no default-then-swap).
   */
  function initTopFeaturePool() {
    var pool = document.querySelector('[data-front-blocks-pool="top_feature"]');
    if (!pool || pool.classList.contains('is-ready')) {
      return;
    }

    var items = pool.querySelectorAll('.front-blocks-item');
    if (!items.length) {
      return;
    }

    var topItem = activateItem(items, seededIndex(rotationSeed(), POOL_SALTS.top_feature, items.length));
    var linkNid = getLinkNid(topItem);
    window.carlsonFrontBlocksExcludedNids = linkNid ? [linkNid] : [];
    markPoolReady(pool);
    revealHeroWhenReady(pool);
  }

  /**
   * Initialize below-the-fold feature pools.
   */
  function initBelowFoldPools() {
    var seed = rotationSeed();
    var excludedNids = new Set(window.carlsonFrontBlocksExcludedNids || []);

    ['feature_news', 'feature_events', 'feature_discover'].forEach(function (poolId) {
      var pool = document.querySelector('[data-front-blocks-pool="' + poolId + '"]');
      if (!pool || pool.classList.contains('is-ready')) {
        return;
      }

      var items = Array.prototype.slice.call(pool.querySelectorAll('.front-blocks-item'));
      var eligible = items.filter(function (item) {
        var nid = item.getAttribute('data-nid');
        return !nid || !excludedNids.has(nid);
      });

      if (!eligible.length) {
        eligible = items;
      }

      activateItem(eligible, seededIndex(seed, POOL_SALTS[poolId], eligible.length));
      markPoolReady(pool);
    });
  }

  window.carlsonFrontBlocksInitTop = initTopFeaturePool;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBelowFoldPools);
  }
  else {
    initBelowFoldPools();
  }

})();
