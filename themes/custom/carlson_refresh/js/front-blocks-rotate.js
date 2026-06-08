/**
 * @file
 * Client-side rotation for below-the-fold front_blocks view pools.
 */
(function (Drupal, drupalSettings) {
  'use strict';

  var settings = drupalSettings.carlsonFrontBlocks || {};
  // TEMP default: 15s (restore 300000 for 5-minute buckets).
  var BUCKET_MS = settings.bucketMs || 15000;
  var POOL_SALTS = {
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
   * Current 5-minute rotation seed.
   */
  function rotationSeed() {
    return Math.floor(Date.now() / BUCKET_MS);
  }

  /**
   * Initialize below-the-fold feature pools.
   */
  function initBelowFoldPools() {
    var seed = rotationSeed();
    var settings = drupalSettings.carlsonFrontBlocks || {};
    var excludedNids = new Set(settings.excludedNids || []);

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

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBelowFoldPools);
  }
  else {
    initBelowFoldPools();
  }

})(Drupal, drupalSettings);
