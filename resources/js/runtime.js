/* laravel-swiper runtime: starts every <x-swiper> and keeps it alive through Livewire updates and wire:navigate. */
(function () {
  var me = document.currentScript;

  // Asset tags printed inside the page body (by the first <x-swiper>) would be removed by the next
  // Livewire morph or repeated by wire:navigate: keep one copy of each, in <head>.
  function assetKey(node) {
    return node.tagName + '|' + (node.getAttribute('href') || node.getAttribute('data-swiper-src') || node.textContent.length + ':' + node.textContent.slice(0, 80));
  }
  var inHead = {};
  document.head.querySelectorAll('[data-laravel-swiper-asset]').forEach(function (node) { inHead[assetKey(node)] = true; });
  document.querySelectorAll('body [data-laravel-swiper-asset]').forEach(function (node) {
    var key = assetKey(node);
    if (inHead[key]) {
      node.remove();
    } else {
      inHead[key] = true;
      document.head.appendChild(node);
    }
  });

  if (window.LaravelSwiper) {
    return;
  }

  var SELECTOR = '[data-laravel-swiper]';
  var reducedMotion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
  var navigating = false;

  function warn(message) {
    if (window.console) console.warn('[laravel-swiper] ' + message);
  }

  function state(el) {
    try {
      return JSON.parse(el.getAttribute('data-laravel-swiper') || '{}');
    } catch (e) {
      warn('#' + el.id + ': invalid data-laravel-swiper JSON.');
      return {};
    }
  }

  function child(el, selector) {
    for (var i = 0; i < el.children.length; i++) {
      if (el.children[i].matches(selector)) return el.children[i];
    }
    return null;
  }

  function emit(el, name, swiper) {
    el.dispatchEvent(new CustomEvent('swiper:' + name, {
      bubbles: true,
      detail: { id: el.id, index: swiper.realIndex, activeIndex: swiper.activeIndex, total: swiper.slides.length, swiper: swiper }
    }));
  }

  function setupToggle(el, swiper, labels, signal) {
    var toggle = child(el, '.laravel-swiper-toggle');
    if (!toggle || !swiper.autoplay) return;

    // Stopped only because keyboard focus is inside: the button still offers "Pause".
    var focusStopped = false;

    function userPaused() {
      return el.hasAttribute('data-swiper-user-paused');
    }

    function render() {
      var paused = userPaused() || (!swiper.autoplay.running && !focusStopped);
      toggle.toggleAttribute('data-paused', paused);
      toggle.setAttribute('aria-label', paused ? labels.play : labels.pause);
    }

    toggle.addEventListener('click', function () {
      focusStopped = false;
      if (userPaused()) {
        el.removeAttribute('data-swiper-user-paused');
        swiper.autoplay.start();
      } else {
        el.setAttribute('data-swiper-user-paused', '');
        swiper.autoplay.stop();
      }
      render();
    }, { signal: signal });

    // WCAG 2.2.2: moving content stops while keyboard focus is inside it. stop()/start(), not
    // pause()/resume(): Swiper 14's resume() can leave autoplay "running" without ever advancing.
    el.addEventListener('focusin', function (event) {
      if (event.target !== toggle && swiper.autoplay.running && !userPaused()) {
        focusStopped = true;
        swiper.autoplay.stop();
      }
    }, { signal: signal });
    el.addEventListener('focusout', function (event) {
      if (focusStopped && !el.contains(event.relatedTarget)) {
        focusStopped = false;
        if (!userPaused() && !swiper.destroyed) swiper.autoplay.start();
      }
    }, { signal: signal });

    swiper.on('autoplayStart autoplayStop', render);
    render();
  }

  function create(el, initialIndex) {
    if (el.swiper && !el.swiper.destroyed) return el.swiper;
    if (typeof window.Swiper !== 'function') {
      warn('Swiper is not loaded (window.Swiper is missing). Check config/swiper.php "assets".');
      return null;
    }

    var data = state(el);
    var config = Object.assign({}, data.config || {});
    var navPrev = child(el, '.swiper-button-prev');
    var navNext = child(el, '.swiper-button-next');
    var pagination = child(el, '.swiper-pagination');
    var scrollbar = child(el, '.swiper-scrollbar');

    config.navigation = navPrev && navNext ? { prevEl: navPrev, nextEl: navNext } : false;
    config.pagination = pagination ? Object.assign({}, config.pagination || {}, { el: pagination }) : false;
    config.scrollbar = scrollbar ? Object.assign({}, config.scrollbar || {}, { el: scrollbar }) : false;

    if (data.thumbs) {
      var thumbsEl = document.getElementById(data.thumbs);
      var thumbs = thumbsEl && thumbsEl.matches(SELECTOR) ? create(thumbsEl) : null;
      if (thumbs) {
        config.thumbs = Object.assign({}, config.thumbs || {}, { swiper: thumbs });
      } else {
        warn('#' + el.id + ': thumbs carousel #' + data.thumbs + ' was not found.');
      }
    }

    if (data.reducedMotion && reducedMotion.matches) {
      config.autoplay = false;
      config.speed = 0;
    }

    if (typeof initialIndex === 'number' && initialIndex > 0) {
      config.initialSlide = initialIndex;
    }

    // A visitor who pressed pause stays paused, also across Livewire rebuilds.
    if (config.autoplay && el.hasAttribute('data-swiper-user-paused')) {
      config.autoplay = Object.assign({}, config.autoplay, { enabled: false });
    }

    var swiper = new window.Swiper(el, config);

    // Listeners on the carousel's own DOM go away with the instance (Livewire keeps the node and rebuilds).
    var listeners = new AbortController();
    swiper.on('destroy', function () { listeners.abort(); });

    swiper.on('slideChange', function () { emit(el, 'slide-change', swiper); });
    setupToggle(el, swiper, data.labels || { pause: 'Pause', play: 'Play' }, listeners.signal);
    if (!config.autoplay) {
      var toggle = child(el, '.laravel-swiper-toggle');
      if (toggle) toggle.hidden = true;
    }

    emit(el, 'init', swiper);
    return swiper;
  }

  function destroy(el) {
    if (el.swiper && !el.swiper.destroyed) {
      el.swiper.destroy(true, true);
    }
  }

  function init(root) {
    withSwiper(function () { initNow(root); });
  }

  function initNow(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var list = Array.prototype.slice.call(scope.querySelectorAll(SELECTOR));
    if (scope !== document && scope.matches && scope.matches(SELECTOR)) list.unshift(scope);
    // Thumbs strips first, so the carousel they belong to can link to them.
    list.sort(function (a, b) { return Number(!!state(b).isThumbs) - Number(!!state(a).isThumbs); });
    list.forEach(function (el) {
      var keep = el.__laravelSwiperIndex;
      var paused = el.__laravelSwiperPaused;
      delete el.__laravelSwiperIndex;
      delete el.__laravelSwiperPaused;
      if (paused) el.setAttribute('data-swiper-user-paused', '');
      create(el, keep);
    });
  }

  // root itself may be a carousel, or contain some.
  function destroyAll(root) {
    var scope = root && root.querySelectorAll ? root : document;
    if (scope !== document && scope.matches && scope.matches(SELECTOR)) destroy(scope);
    scope.querySelectorAll(SELECTOR).forEach(destroy);
  }

  // Arrow keys move only the carousel holding focus, or the one last clicked or touched while it
  // is still on screen. Swiper's own keyboard module moves every visible carousel at once.
  var lastUsed = null;

  document.addEventListener('pointerdown', function (event) {
    var el = event.target && event.target.closest && event.target.closest(SELECTOR);
    lastUsed = el && !el.matches('[aria-hidden="true"]') ? el : null;
  }, true);

  function onScreen(el) {
    var rect = el.getBoundingClientRect();
    return rect.bottom > 0 && rect.top < window.innerHeight && rect.right > 0 && rect.left < window.innerWidth;
  }

  document.addEventListener('keydown', function (event) {
    if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
    var target = event.target;
    if (target && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName))) return;

    var focused = document.activeElement && document.activeElement.closest && document.activeElement.closest(SELECTOR);
    var el = focused || (lastUsed && lastUsed.isConnected && onScreen(lastUsed) ? lastUsed : null);
    var swiper = el && el.swiper;
    if (!swiper || swiper.destroyed || !state(el).keyboard) return;

    var vertical = swiper.isHorizontal ? !swiper.isHorizontal() : swiper.params.direction === 'vertical';
    var rtl = swiper.rtlTranslate;
    var key = event.key;
    var back = vertical ? key === 'ArrowUp' : key === (rtl ? 'ArrowRight' : 'ArrowLeft');
    var forward = vertical ? key === 'ArrowDown' : key === (rtl ? 'ArrowLeft' : 'ArrowRight');

    if (back) swiper.slidePrev();
    else if (forward) swiper.slideNext();
    else if (key === 'Home') swiper.params.loop ? swiper.slideToLoop(0) : swiper.slideTo(0);
    else if (key === 'End') swiper.params.loop ? swiper.slideToLoop(swiper.slides.length - 1) : swiper.slideTo(swiper.slides.length - 1);
    else return;

    event.preventDefault();
  });

  function find(id) {
    var el = document.getElementById(id);
    return el && el.swiper && !el.swiper.destroyed ? el.swiper : null;
  }

  // Carousels rendered by this component, not by a nested child component (Livewire leaves those alone).
  function ownCarousels(root) {
    var owner = root.getAttribute && root.getAttribute('wire:id');
    return Array.prototype.filter.call(root.querySelectorAll(SELECTOR), function (node) {
      var component = node.closest('[wire\\:id]');
      return !owner || !component || component === root || component.getAttribute('wire:id') === owner;
    });
  }

  // Livewire morph: an unchanged carousel (same data-swiper-hash) is kept as it is, slide
  // position and autoplay included. A changed one is torn down before the morph and rebuilt
  // after it, staying on the same slide where that slide still exists.
  function hookLivewire() {
    if (!window.Livewire || typeof window.Livewire.hook !== 'function' || window.Livewire.__laravelSwiperHooked) return;
    window.Livewire.__laravelSwiperHooked = true;

    window.Livewire.hook('morph', function (payload) {
      var el = payload.el;
      var toEl = payload.toEl;
      if (!el || !toEl) return;

      var incoming = {};
      ownCarousels(toEl).forEach(function (node, i) {
        incoming[node.id || ('#' + i)] = node;
      });

      ownCarousels(el).forEach(function (current, i) {
        var next = incoming[current.id] || incoming['#' + i] || null;
        var unchanged = next && current.swiper && !current.swiper.destroyed &&
          next.getAttribute('data-swiper-hash') === current.getAttribute('data-swiper-hash');

        if (unchanged) {
          next.replaceWith(current.cloneNode(true));
          return;
        }

        // Morph patches the current node in place, so the slide position is kept on it.
        current.__laravelSwiperIndex = current.swiper && !current.swiper.destroyed ? current.swiper.realIndex : 0;
        current.__laravelSwiperPaused = current.hasAttribute('data-swiper-user-paused');
        destroy(current);
      });
    });

    window.Livewire.hook('morphed', function (payload) {
      var el = payload.el;
      if (el) init(el);
    });
  }

  // Swiper itself: already on the page (Vite bundle, your own tag), or loaded once from the configured URL.
  var swiperLoading = null;

  function withSwiper(callback) {
    if (typeof window.Swiper === 'function') return callback();
    var src = me && me.getAttribute('data-swiper-src');
    if (!src) {
      // mode "none": a Vite bundle may still be running; try again once the page has loaded.
      if (document.readyState !== 'complete') {
        window.addEventListener('load', function () { withSwiper(callback); }, { once: true });
      } else {
        warn('Swiper is not loaded (window.Swiper is missing). Check config/swiper.php "assets".');
      }
      return;
    }
    if (!swiperLoading) {
      swiperLoading = new Promise(function (resolve, reject) {
        var tag = document.createElement('script');
        tag.src = src;
        var integrity = me.getAttribute('data-swiper-integrity');
        if (integrity) {
          tag.integrity = integrity;
          tag.crossOrigin = 'anonymous';
        }
        if (me.nonce) tag.nonce = me.nonce;
        tag.setAttribute('data-laravel-swiper-asset', '');
        tag.onload = resolve;
        tag.onerror = function () { warn('could not load Swiper from ' + src); reject(); };
        document.head.appendChild(tag);
      });
    }
    swiperLoading.then(callback, function () {});
  }

  function whenReady(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
      setTimeout(callback, 0);
    }
  }

  // Livewire.navigate swaps the page without DOMContentLoaded. Destroying first keeps the
  // HTML Livewire saves for the back button clean (no Swiper-generated markup in it).
  document.addEventListener('livewire:navigating', function () {
    navigating = true;
    destroyAll(document);
  });
  document.addEventListener('livewire:navigated', function () {
    navigating = false;
    hookLivewire();
    init(document);
  });
  document.addEventListener('livewire:init', hookLivewire);

  // $this->dispatch('swiper:go', id: 'gallery', index: 2) from Livewire, or
  // $dispatch('swiper:go', { id: 'gallery', index: 2 }) from Alpine.
  window.addEventListener('swiper:go', function (event) {
    var detail = event.detail || {};
    if (Array.isArray(detail)) detail = detail[0] || {};
    var swiper = find(detail.id);
    if (!swiper) return warn('swiper:go: no carousel with id "' + detail.id + '".');
    var index = parseInt(detail.index, 10) || 0;
    if (swiper.params.loop) swiper.slideToLoop(index); else swiper.slideTo(index);
  });

  window.LaravelSwiper = {
    init: init,
    destroy: destroyAll,
    get: find
  };

  hookLivewire();
  whenReady(function () {
    if (!navigating) init(document);
  });
})();
