document.addEventListener('DOMContentLoaded', function () {
  var siteHeader = document.querySelector('header');
  if (siteHeader) {
    var onScroll = function () {
      siteHeader.classList.toggle('scrolled', window.scrollY > 4);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  var burger = document.querySelector('.burger');
  var navLinks = document.querySelector('.nav-links');
  if (burger && navLinks) {
    burger.addEventListener('click', function () {
      var open = navLinks.classList.toggle('open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }
  var dropdownToggle = document.querySelector('.has-dropdown > a');
  var hasDropdown = document.querySelector('.has-dropdown');
  if (dropdownToggle && hasDropdown) {
    dropdownToggle.addEventListener('click', function (e) {
      if (window.innerWidth <= 720) {
        e.preventDefault();
        var open = hasDropdown.classList.toggle('open');
        dropdownToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      }
    });
  }

  document.querySelectorAll('.faq-item .faq-q').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var open = btn.closest('.faq-item').classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  var heroImages = document.querySelectorAll('.hero-image img');
  if (heroImages.length > 1) {
    var heroIndex = 0;
    setInterval(function () {
      heroImages[heroIndex].classList.remove('active');
      heroIndex = (heroIndex + 1) % heroImages.length;
      heroImages[heroIndex].classList.add('active');
    }, 4500);
  }

  function wireFilter(filtersId, gridId, itemSelector) {
    var filters = document.getElementById(filtersId);
    var grid = document.getElementById(gridId);
    if (!filters || !grid) return;
    var items = grid.querySelectorAll(itemSelector);
    filters.querySelectorAll('button').forEach(function (tab) {
      tab.addEventListener('click', function () {
        filters.querySelectorAll('button').forEach(function (b) { b.classList.remove('active'); });
        tab.classList.add('active');
        var filter = tab.getAttribute('data-filter');
        items.forEach(function (item) {
          var show = filter === 'all' || item.getAttribute('data-category') === filter;
          item.style.display = show ? '' : 'none';
        });
      });
    });
  }
  wireFilter('blog-filters', 'blog-grid', '.service-card');
  wireFilter('gallery-filters', 'gallery-grid', '.gallery-item');

  var contactForm = document.getElementById('contact-form');
  if (contactForm) {
    var status = document.getElementById('contact-form-status');
    contactForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var submitBtn = contactForm.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      fetch(contactForm.action, { method: 'POST', body: new FormData(contactForm) })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok && data.ok, data: data }; }); })
        .then(function (result) {
          status.textContent = result.ok ? status.getAttribute('data-success') : (result.data.message || status.getAttribute('data-error'));
          status.style.color = result.ok ? 'var(--green)' : 'var(--rose)';
          status.style.display = 'block';
          if (result.ok) contactForm.reset();
        })
        .catch(function () {
          status.textContent = status.getAttribute('data-error');
          status.style.color = 'var(--rose)';
          status.style.display = 'block';
        })
        .finally(function () { submitBtn.disabled = false; });
    });
  }

  var cookieBanner = document.getElementById('cookie-banner');
  if (cookieBanner) {
    var consent = null;
    try { consent = localStorage.getItem('afleuressences-cookie-consent'); } catch (e) {}
    if (!consent) cookieBanner.hidden = false;
    var accept = document.getElementById('cookie-accept');
    var decline = document.getElementById('cookie-decline');
    if (accept) accept.addEventListener('click', function () {
      try { localStorage.setItem('afleuressences-cookie-consent', 'accepted'); } catch (e) {}
      cookieBanner.hidden = true;
    });
    if (decline) decline.addEventListener('click', function () {
      try { localStorage.setItem('afleuressences-cookie-consent', 'declined'); } catch (e) {}
      cookieBanner.hidden = true;
    });
  }
});
