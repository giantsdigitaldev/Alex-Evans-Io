/**
 * alexevans.io — consolidated site scripts
 * - Hero: matrix-style code reveal (cursor-following mask)
 * - Work: "View Project" cursor label + GSAP scroll reveal
 */
(function () {
  'use strict';

  /* ==========================================================================
     HELPERS
     ========================================================================== */

  /** Linear interpolation: (1-t)*a + t*b */
  function lerp(a, b, t) {
    return (1 - t) * a + t * b;
  }

  function normalizeText(value) {
    return String(value || '').replace(/\s+/g, ' ').trim().slice(0, 120);
  }

  function getAnalyticsConfig() {
    return window.ALEX_ANALYTICS_CONFIG || {};
  }

  function canTrack() {
    var config = getAnalyticsConfig();
    return !!config.enableGa4 && typeof window.gtag === 'function';
  }

  function trackEvent(eventName, params) {
    if (!canTrack()) return;
    window.gtag('event', eventName, params || {});
  }

  function trackEventOnce(storageKey, eventName, params) {
    try {
      if (window.sessionStorage.getItem(storageKey)) return;
      window.sessionStorage.setItem(storageKey, '1');
    } catch (err) {
      /* Ignore storage issues and still try to send the event once per page load. */
    }
    trackEvent(eventName, params);
  }

  window.alexTrack = trackEvent;

  /* ==========================================================================
     HERO — CODE REVEAL
     Matrix-style code layer that follows the cursor in a radial mask.
     Elements with [data-code-reveal] must contain .hero-splash__code (or .hero-code__code).
     ========================================================================== */

  var CODE_CHARS = '01{}[]();<>/:=+-*&|!?@#$%constvarletfunctionreturnimportexport';
  var CODE_LENGTH = 18000;

  var mousepos = { x: 0, y: 0 };
  window.addEventListener('mousemove', function (ev) {
    mousepos.x = ev.clientX;
    mousepos.y = ev.clientY;
  });

  function getRandomCodeString(length) {
    var out = '';
    for (var i = 0; i < length; i++) {
      out += CODE_CHARS.charAt(Math.floor(Math.random() * CODE_CHARS.length));
    }
    return out;
  }

  function CodeReveal(el) {
    this.el = el;
    this.deco = el.querySelector('.hero-splash__code') || el.querySelector('.hero-code__code');
    if (!this.deco) return;

    this.renderedStyles = {
      x: { previous: 0, current: 0, amt: 0.12 },
      y: { previous: 0, current: 0, amt: 0.12 }
    };
    this.randomString = getRandomCodeString(CODE_LENGTH);
    this.scrollVal = { x: window.scrollX, y: window.scrollY };
    this.rect = null;
    this.requestId = null;

    this.calculateSizePosition();
    this.initCodeRevealEvents();
  }

  CodeReveal.prototype.calculateSizePosition = function () {
    this.scrollVal.x = window.scrollX;
    this.scrollVal.y = window.scrollY;
    this.rect = this.el.getBoundingClientRect();
  };

  CodeReveal.prototype.initCodeRevealEvents = function () {
    var self = this;
    window.addEventListener('resize', function () {
      self.calculateSizePosition();
    });
    this.el.addEventListener('mousemove', function () {
      self.randomString = getRandomCodeString(CODE_LENGTH);
    });
    this.el.addEventListener('mouseenter', function () {
      self.loopRender(true);
    });
    this.el.addEventListener('mouseleave', function () {
      self.stopRendering();
    });
  };

  CodeReveal.prototype.loopRender = function (isFirstTick) {
    if (!this.requestId) {
      this.requestId = requestAnimationFrame(function () {
        this.render(isFirstTick);
      }.bind(this));
    }
  };

  CodeReveal.prototype.stopRendering = function () {
    if (this.requestId) {
      cancelAnimationFrame(this.requestId);
      this.requestId = undefined;
    }
  };

  CodeReveal.prototype.render = function (isFirstTick) {
    this.requestId = undefined;

    var scrollDiffX = this.scrollVal.x - window.scrollX;
    var scrollDiffY = this.scrollVal.y - window.scrollY;
    this.renderedStyles.x.current = mousepos.x - (scrollDiffX + this.rect.left);
    this.renderedStyles.y.current = mousepos.y - (scrollDiffY + this.rect.top);

    if (isFirstTick) {
      this.renderedStyles.x.previous = this.renderedStyles.x.current;
      this.renderedStyles.y.previous = this.renderedStyles.y.current;
    }

    this.renderedStyles.x.previous = lerp(
      this.renderedStyles.x.previous,
      this.renderedStyles.x.current,
      this.renderedStyles.x.amt
    );
    this.renderedStyles.y.previous = lerp(
      this.renderedStyles.y.previous,
      this.renderedStyles.y.current,
      this.renderedStyles.y.amt
    );

    this.el.style.setProperty('--code-x', this.renderedStyles.x.previous + 'px');
    this.el.style.setProperty('--code-y', this.renderedStyles.y.previous + 'px');
    this.deco.textContent = this.randomString;

    this.loopRender();
  };

  var HERO_BG_IMAGE_URL = '/img/alex_evans_hero.webp';

  /**
   * Resolves when the hero background <img> has loaded (and decoded when supported).
   * Used so secondary hero assets (desktop robot, mobile glitch) never run before the main photo.
   */
  function waitForHeroBgImage() {
    var img = document.querySelector('.hero-bg-image__img');
    return new Promise(function (resolve) {
      if (!img) {
        if (HERO_BG_IMAGE_URL) {
          var fallbackImg = new Image();
          fallbackImg.src = HERO_BG_IMAGE_URL;
        }
        resolve();
        return;
      }

      var done = false;
      var cleanup = function () {
        img.removeEventListener('load', onLoad);
        img.removeEventListener('error', onError);
      };
      var finish = function () {
        if (done) return;
        done = true;
        cleanup();
        resolve();
      };
      var onLoad = function () {
        if (typeof img.decode === 'function') {
          img.decode().catch(function () {
            /* Ignore decode failures; the loaded image can still be painted. */
          }).finally(finish);
          return;
        }
        finish();
      };
      var onError = function () {
        finish();
      };

      if (img.complete) {
        if (img.naturalWidth > 0) {
          onLoad();
          return;
        }
        finish();
        return;
      }

      img.addEventListener('load', onLoad);
      img.addEventListener('error', onError);
    });
  }

  /** Preload an image; resolves to true if loaded, false on error. */
  function preloadImage(url, fetchPriority) {
    return new Promise(function (resolve) {
      var image = new Image();
      var done = false;
      var cleanup = function () {
        image.removeEventListener('load', onLoad);
        image.removeEventListener('error', onError);
      };
      var finish = function (wasLoaded) {
        if (done) return;
        done = true;
        cleanup();
        resolve(wasLoaded);
      };
      var onLoad = function () {
        if (typeof image.decode === 'function') {
          image.decode().catch(function () {
            /* Ignore decode failures; the loaded image can still be painted. */
          }).finally(function () {
            finish(true);
          });
          return;
        }
        finish(true);
      };
      var onError = function () {
        finish(false);
      };

      if ('fetchPriority' in image && fetchPriority) {
        image.fetchPriority = fetchPriority;
      }

      image.addEventListener('load', onLoad);
      image.addEventListener('error', onError);
      image.src = url;

      if (image.complete) {
        if (image.naturalWidth > 0) {
          onLoad();
          return;
        }
        onError();
      }
    });
  }

  function initCodeReveal() {
    /* Skip on touch/mobile — spotlight and matrix code are desktop-only */
    if (window.matchMedia('(pointer: coarse)').matches) return;
    var spotlight = document.querySelector('.hero-spotlight');
    var codeReveal = document.querySelector('[data-code-reveal]');
    if (spotlight && codeReveal) {
      var robotUrl = '/img/alex_evans_robot.webp';
      /* Blocks :hover spotlight until real pointer movement (avoids "stuck hover" when cursor rests on hero on load). */
      codeReveal.classList.add('hero-hover-suppressed');
      var userHasMovedPointer = false;
      var robotLoadDone = false;
      var robotLoadOk = false;

      function tryUnlockHeroHover() {
        if (!userHasMovedPointer || !robotLoadDone) return;
        if (robotLoadOk) {
          spotlight.style.backgroundImage = "url('" + robotUrl + "')";
          codeReveal.classList.add('is-hover-ready');
        }
        codeReveal.classList.remove('hero-hover-suppressed');
      }

      function unlockHeroHoverFromPointer() {
        codeReveal.removeEventListener('pointermove', unlockHeroHoverFromPointer);
        codeReveal.removeEventListener('pointerdown', unlockHeroHoverFromPointer);
        userHasMovedPointer = true;
        tryUnlockHeroHover();
      }
      codeReveal.addEventListener('pointermove', unlockHeroHoverFromPointer);
      codeReveal.addEventListener('pointerdown', unlockHeroHoverFromPointer);

      waitForHeroBgImage()
        .then(function () {
          return preloadImage(robotUrl, 'low');
        })
        .then(function (robotLoaded) {
          robotLoadDone = true;
          robotLoadOk = !!robotLoaded;
          tryUnlockHeroHover();
        });
    }
    document.querySelectorAll('[data-code-reveal]').forEach(function (el) {
      new CodeReveal(el);
    });
  }

  /* ==========================================================================
     HERO — MOBILE GLITCH FLICKER
     Randomly flickers a robot image overlay over the hero on touch devices.
     Runs on touch/pointer-coarse devices. Starts only after the main hero image
     has loaded and the mob-robot asset is ready; then fires every 1–4 s (initial 1–2 s).
     ========================================================================== */

  function initMobileGlitch() {
    var isTouchDevice = window.matchMedia('(hover: none)').matches || window.matchMedia('(pointer: coarse)').matches;
    if (!isTouchDevice) return;

    var hero = document.getElementById('hero');
    if (!hero) return;

    var glitchEl = hero.querySelector('.hero-mobile-glitch');
    if (!glitchEl) return;

    var mobRobotUrl = '/img/alex_evans_mob-robot.webp';

    waitForHeroBgImage().then(function () {
      return preloadImage(mobRobotUrl, 'low');
    }).then(function (mobLoaded) {
      if (!mobLoaded) return;

      function triggerGlitch() {
        if (!glitchEl.dataset.loaded) {
          glitchEl.style.backgroundImage = "url('" + mobRobotUrl + "')";
          glitchEl.dataset.loaded = '1';
        }
        glitchEl.classList.remove('is-glitching');
        /* Force reflow so removing + re-adding the class restarts the animation */
        void glitchEl.offsetWidth;
        glitchEl.classList.add('is-glitching');
        var hasEnded = false;

        function finishGlitch() {
          if (hasEnded) return;
          hasEnded = true;
          glitchEl.removeEventListener('animationend', onAnimationEnd);
          glitchEl.classList.remove('is-glitching');
          scheduleNextGlitch();
        }

        var fallback = setTimeout(finishGlitch, 5200);
        var onAnimationEnd = function () {
          clearTimeout(fallback);
          finishGlitch();
        };
        glitchEl.addEventListener('animationend', onAnimationEnd);
      }

      function scheduleNextGlitch() {
        var delay = 1000 + Math.random() * 3000; /* 1–4 s */
        setTimeout(triggerGlitch, delay);
      }

      /* Initial delay: 1–2 s (after hero + mob asset are ready) */
      setTimeout(triggerGlitch, 1000 + Math.random() * 1000);
    });
  }

  /* ==========================================================================
     WORK SECTION — VIEW PROJECT LABEL
     Cursor-following label: code-scan animation (random symbols) then "View" / "Project" (2 lines).
     Same animation as barcode code-flipper: ~20 symbols, each char varies, then lands on final letter.
     Targets .work-card-slide a .work-card-frame.
     ========================================================================== */

  var VIEW_PROJECT_SYMBOL_DURATION_MS = 80;
  var VIEW_PROJECT_NUM_STEPS = 14;
  var VIEW_PROJECT_MORPH_SYMBOLS = ['>', '&', '_', '[', ']', '#', '@', '%', '*', '+', '=', '~', '|', '/', '\\', ':', ';', '<', '?', '!', '^', '(', ')', '.', ',', '-', '{', '}'];

  function makeCharSpan(char) {
    var span = document.createElement('span');
    span.className = 'title-char-morph work-view-case-char';
    span.setAttribute('data-morph-char', char);
    span.textContent = char;
    return span;
  }

  function buildViewProjectLabel() {
    var label = document.createElement('div');
    label.className = 'work-view-case';
    label.setAttribute('aria-hidden', 'true');
    var line1 = document.createElement('span');
    line1.className = 'work-view-case-line';
    'View'.split('').forEach(function (c) {
      line1.appendChild(makeCharSpan(c));
    });
    var br = document.createElement('br');
    var line2 = document.createElement('span');
    line2.className = 'work-view-case-line';
    'Project'.split('').forEach(function (c) {
      line2.appendChild(makeCharSpan(c));
    });
    label.appendChild(line1);
    label.appendChild(br);
    label.appendChild(line2);
    return label;
  }

  function pickRandomViewProjectSymbol() {
    return VIEW_PROJECT_MORPH_SYMBOLS[Math.floor(Math.random() * VIEW_PROJECT_MORPH_SYMBOLS.length)];
  }

  function runCodeScanMorph(span) {
    var char = span.getAttribute('data-morph-char');
    if (!char) return;
    var step = 0;
    function showNext() {
      if (step < VIEW_PROJECT_NUM_STEPS) {
        span.textContent = pickRandomViewProjectSymbol();
        step++;
        setTimeout(showNext, VIEW_PROJECT_SYMBOL_DURATION_MS);
      } else {
        span.textContent = char;
      }
    }
    showNext();
  }

  function runCodeScanOnLabel(label) {
    var spans = label.querySelectorAll('.work-view-case-char[data-morph-char]');
    spans.forEach(function (span, i) {
      var delay = Math.floor(Math.random() * 50);
      setTimeout(function () { runCodeScanMorph(span); }, delay);
    });
  }

  function resetLabel(label) {
    var spans = label.querySelectorAll('.work-view-case-char[data-morph-char]');
    spans.forEach(function (span) {
      span.textContent = span.getAttribute('data-morph-char');
    });
  }

  function initViewCaseLabels() {
    var frames = document.querySelectorAll('.work-card-slide a .work-card-frame');
    frames.forEach(function (frame) {
      var label = buildViewProjectLabel();
      frame.appendChild(label);

      var mouse = { x: 0, y: 0 };
      var lerped = { x: 0, y: 0 };
      var scaleVal = { current: 0, target: 0 };
      var raf = null;
      var active = false;

      function tick() {
        lerped.x = lerp(lerped.x, mouse.x, 0.10);
        lerped.y = lerp(lerped.y, mouse.y, 0.10);
        scaleVal.current = lerp(scaleVal.current, scaleVal.target, 0.13);

        label.style.transform =
          'translate(' + lerped.x + 'px, ' + lerped.y + 'px) ' +
          'translate(-50%, -50%) scale(' + scaleVal.current + ')';
        label.style.opacity = Math.min(1, scaleVal.current * 2);

        var stillMoving =
          Math.abs(lerped.x - mouse.x) > 0.05 ||
          Math.abs(lerped.y - mouse.y) > 0.05 ||
          Math.abs(scaleVal.current - scaleVal.target) > 0.003;

        if (active || stillMoving) {
          raf = requestAnimationFrame(tick);
        } else {
          raf = null;
        }
      }

      frame.addEventListener('mouseenter', function (e) {
        var rect = frame.getBoundingClientRect();
        mouse.x = e.clientX - rect.left;
        mouse.y = e.clientY - rect.top;
        lerped.x = mouse.x;
        lerped.y = mouse.y;
        active = true;
        scaleVal.target = 1;
        if (!raf) raf = requestAnimationFrame(tick);

        runCodeScanOnLabel(label);
      });

      frame.addEventListener('mousemove', function (e) {
        var rect = frame.getBoundingClientRect();
        mouse.x = e.clientX - rect.left;
        mouse.y = e.clientY - rect.top;
        if (!raf) raf = requestAnimationFrame(tick);
      });

      frame.addEventListener('mouseleave', function () {
        active = false;
        scaleVal.target = 0;
        resetLabel(label);
        if (!raf) raf = requestAnimationFrame(tick);
      });
    });
  }

  /* ==========================================================================
     WORK SECTION — SCROLL REVEAL (GSAP)
     Overlay wipe, image scale, text slide-up for .work-card-slide[data-gsap="item"].
     ========================================================================== */

  function initWorkCardReveal() {
    var cards = document.querySelectorAll('.work-card-slide[data-gsap="item"]');
    if (!cards.length) return;

    if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
      cards.forEach(function (card) {
        var overlay = card.querySelector('[data-gsap="image-overlay"]');
        if (overlay) {
          overlay.style.transform = 'scaleX(0)';
          overlay.style.transition = 'none';
        }
      });
      return;
    }

    gsap.registerPlugin(ScrollTrigger);

    cards.forEach(function (card) {
      var overlay = card.querySelector('[data-gsap="image-overlay"]');
      var image = card.querySelector('[data-gsap="image"]');
      var content = card.querySelector('[data-gsap="content"]');
      var title = card.querySelector('[data-gsap="title"]');
      var excerpt = card.querySelector('[data-gsap="excerpt"]');

      var tl = gsap.timeline({
        scrollTrigger: {
          trigger: card,
          start: 'top 88%',
          once: true,
        },
      });

      if (overlay) {
        tl.to(overlay, {
          scaleX: 0,
          duration: 0.9,
          ease: 'power2.inOut',
        }, 0);
      }

      if (image) {
        tl.fromTo(image,
          { scale: 1.06 },
          { scale: 1, duration: 1.2, ease: 'power2.out' },
          0
        );
      }

      var textEls = [content, title, excerpt].filter(Boolean);
      if (textEls.length) {
        tl.from(textEls, {
          y: '110%',
          duration: 0.6,
          stagger: 0.08,
          ease: 'power3.out',
        }, 0.35);
      }
    });
  }

  /* ==========================================================================
     TECH STACK — Scroll reveal: section line + staggered list items (fade/slide up)
     ========================================================================== */
  function initTechStackReveal() {
    var section = document.getElementById('tech-stack');
    if (!section) return;
    var list = section.querySelector('[data-gsap="tech-stack-list"]');
    var items = section.querySelectorAll('[data-gsap="tech-stack-item"]');
    var hr = section.querySelector('hr');
    if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
      if (items.length) items.forEach(function (el) { el.style.opacity = '1'; });
      if (hr) hr.style.transform = 'scaleX(1)';
      return;
    }
    gsap.registerPlugin(ScrollTrigger);
    var tl = gsap.timeline({
      scrollTrigger: {
        trigger: section,
        start: 'top 82%',
        once: true,
      },
    });
    if (hr) {
      tl.fromTo(hr, { scaleX: 0 }, { scaleX: 1, duration: 1, ease: 'power2.inOut' }, 0);
    }
    if (items.length) {
      tl.fromTo(items, { opacity: 0, y: 14 }, { opacity: 1, y: 0, duration: 0.5, stagger: 0.04, ease: 'power2.out' }, 0.15);
    }
  }

  /* ==========================================================================
     CONTACT — CODE SCRAMBLER FOR CORNER LETTERS (A + E)
     Cycles through 0/1 and code-like symbols then reveals the letter.
     ========================================================================== */

  var CODE_SCRAMBLER_SYMBOLS = ['0', '1', '/', '\\', '[', ']', '>', '<', '_', '|'];
  var CODE_SCRAMBLER_DURATION_MS = 80;
  var CODE_SCRAMBLER_STEPS = 12;

  function runContactLetterMorph(el) {
    var char = el.getAttribute('data-morph-char');
    if (!char) return;
    el.classList.add('char-morph-active');
    var step = 0;
    function showNext() {
      if (step < CODE_SCRAMBLER_STEPS) {
        el.textContent = CODE_SCRAMBLER_SYMBOLS[Math.floor(Math.random() * CODE_SCRAMBLER_SYMBOLS.length)];
        step++;
        setTimeout(showNext, CODE_SCRAMBLER_DURATION_MS);
      } else {
        el.classList.remove('char-morph-active');
        el.textContent = char;
      }
    }
    showNext();
  }

  function initContactLetterMorphs() {
    var ids = ['cta-letter-a', 'cta-letter-e'];
    ids.forEach(function (id) {
      var el = document.getElementById(id);
      if (!el || !el.classList.contains('code-scrambler-letter')) return;
      var baseDelay = id === 'cta-letter-a' ? 2000 : 3400;
      function scheduleMorph() {
        var jitter = Math.random() * 2500;
        setTimeout(function () {
          runContactLetterMorph(el);
          scheduleMorph();
        }, baseDelay + jitter);
      }
      scheduleMorph();
    });
  }

  /* ==========================================================================
     ANALYTICS — CTA, outbound links, and lead attribution
     ========================================================================== */

  function persistAttributionData() {
    var keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
    var params = new URLSearchParams(window.location.search);
    var stored = {};
    var current = {};

    try {
      stored = JSON.parse(window.sessionStorage.getItem('alexAttribution') || '{}') || {};
    } catch (err) {
      stored = {};
    }

    keys.forEach(function (key) {
      var value = normalizeText(params.get(key));
      if (value) current[key] = value;
    });

    var data = {};
    keys.concat(['landing_page', 'referrer']).forEach(function (key) {
      data[key] = '';
    });

    keys.forEach(function (key) {
      data[key] = current[key] || stored[key] || '';
    });

    data.landing_page = stored.landing_page || window.location.href;
    data.referrer = stored.referrer || normalizeText(document.referrer);

    try {
      window.sessionStorage.setItem('alexAttribution', JSON.stringify(data));
    } catch (err) {
      /* Ignore storage issues and continue without persisted attribution. */
    }

    return data;
  }

  function populateContactAttribution(form, attribution) {
    if (!form || !attribution) return;
    Object.keys(attribution).forEach(function (key) {
      var input = form.querySelector('input[name="' + key + '"]');
      if (input && attribution[key]) input.value = attribution[key];
    });
  }

  function initAnalytics() {
    var attribution = persistAttributionData();
    var contactForm = document.querySelector('.contact-form');
    var pagePath = window.location.pathname;
    var params = new URLSearchParams(window.location.search);

    if (contactForm) {
      populateContactAttribution(contactForm, attribution);
      contactForm.addEventListener('submit', function () {
        populateContactAttribution(contactForm, attribution);
        trackEvent('contact_form_submit', {
          form_name: 'contact_form',
          page_path: pagePath
        });
      });
    }

    document.addEventListener('click', function (event) {
      var link = event.target.closest('a');
      var href;
      var url;
      var label;

      if (!link) return;
      href = link.getAttribute('href') || '';
      label = normalizeText(link.getAttribute('aria-label') || link.textContent || href);

      try {
        url = new URL(link.href, window.location.origin);
      } catch (err) {
        return;
      }

      if (href.indexOf('#contact') !== -1 || link.closest('[data-gsap="navigation-schedule-call"]')) {
        trackEvent('cta_click', {
          cta_name: label || 'contact_cta',
          cta_target: 'contact',
          page_path: pagePath
        });
      }

      if (link.closest('.work-card-slide')) {
        trackEvent('case_study_click', {
          item_name: label,
          page_path: pagePath
        });
      }

      if (link.closest('.work-timeline-list')) {
        trackEvent('portfolio_timeline_click', {
          item_name: label,
          page_path: pagePath
        });
      }

      if (link.closest('.contact-section-nav')) {
        trackEvent('section_nav_click', {
          item_name: label,
          page_path: pagePath
        });
      }

      if (url.origin !== window.location.origin && /^https?:$/.test(url.protocol)) {
        trackEvent('outbound_click', {
          link_url: url.href,
          link_domain: url.hostname,
          link_text: label,
          page_path: pagePath
        });
      }
    });

    if (params.get('contact') === 'sent') {
      trackEventOnce('alex-contact-sent:' + pagePath + ':' + window.location.search, 'generate_lead', {
        form_name: 'contact_form',
        lead_type: 'contact',
        page_path: pagePath
      });
    }

    if (params.get('contact') === 'error') {
      trackEventOnce('alex-contact-error:' + pagePath + ':' + window.location.search, 'contact_form_error', {
        form_name: 'contact_form',
        page_path: pagePath
      });
    }
  }

  /* ==========================================================================
     LANGUAGE SWITCHER
     Preserve the current hash when switching languages and close menus cleanly.
     ========================================================================== */

  function initLanguageSwitcher() {
    var switchers = document.querySelectorAll('[data-lang-switcher]');
    if (!switchers.length) return;

    var canonicalOrigin = 'https://alexevans.io';
    var hash = window.location.hash || '';
    document.querySelectorAll('[data-lang-link]').forEach(function (link) {
      var href = link.getAttribute('href');
      if (!href) return;
      try {
        var url = new URL(href, window.location.href);
        url.hash = hash;
        var onCanonical = window.location.origin === canonicalOrigin;
        var targetIsCanonical = url.origin === canonicalOrigin;
        if (!onCanonical && targetIsCanonical) {
          link.setAttribute('href', url.origin + url.pathname + url.search + url.hash);
        } else {
          link.setAttribute('href', url.pathname + url.search + url.hash);
        }
      } catch (err) {}
    });

    /* Hover opens dropdown; click on summary (EN/DE) switches language without opening dropdown */
    switchers.forEach(function (switcher) {
      var summary = switcher.querySelector('.site-language-switcher__summary');
      var menu = switcher.querySelector('.site-language-switcher__menu');
      var leaveTimer = null;

      function openMenu() {
        leaveTimer && clearTimeout(leaveTimer);
        leaveTimer = null;
        switcher.open = true;
      }
      function closeMenu() {
        if (leaveTimer) clearTimeout(leaveTimer);
        leaveTimer = setTimeout(function () {
          switcher.open = false;
          leaveTimer = null;
        }, 120);
      }

      switcher.addEventListener('mouseenter', openMenu);
      switcher.addEventListener('mouseleave', closeMenu);

      if (summary) {
        summary.addEventListener('click', function (e) {
          e.preventDefault();
          var link = menu ? menu.querySelector('.site-language-switcher__link') : null;
          if (link && link.getAttribute('href')) {
            var url = link.getAttribute('href');
            try {
              var u = new URL(url, window.location.href);
              u.hash = hash;
              var onCanonical = window.location.origin === canonicalOrigin;
              var targetIsCanonical = u.origin === canonicalOrigin;
              if (!onCanonical && targetIsCanonical) {
                window.location.assign(u.origin + u.pathname + u.search + u.hash);
              } else {
                window.location.assign(u.pathname + u.search + u.hash);
              }
            } catch (err) {
              window.location.assign(url);
            }
          }
        });
      }
    });

    document.addEventListener('click', function (event) {
      switchers.forEach(function (switcher) {
        if (!switcher.contains(event.target)) {
          switcher.open = false;
        }
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') return;
      switchers.forEach(function (switcher) {
        switcher.open = false;
      });
    });
  }

  /* ==========================================================================
     INIT
     ========================================================================== */

  function init() {
    initAnalytics();
    initCodeReveal();
    initMobileGlitch();
    initViewCaseLabels();
    initWorkCardReveal();
    initTechStackReveal();
    initContactLetterMorphs();
    initLanguageSwitcher();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
