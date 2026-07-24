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
      if (window.innerWidth <= 1000) {
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
    }, 3000);
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
        var visibles = 0;
        items.forEach(function (item) {
          var show = filter === 'all' || item.getAttribute('data-category') === filter;
          item.style.display = show ? '' : 'none';
          if (show) visibles++;
        });
        // message quand une categorie n'a pas encore d'article
        var empty = grid.parentNode.querySelector('.filters-empty');
        if (!empty) {
          empty = document.createElement('p');
          empty.className = 'filters-empty';
          grid.parentNode.insertBefore(empty, grid.nextSibling);
        }
        empty.textContent = grid.getAttribute('data-empty') || 'Les premiers articles de cette rubrique arrivent bientot.';
        empty.style.display = visibles ? 'none' : 'block';
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

  // Ateliers : rendu des prochaines dates + modale de reservation
  var ateliersGrid = document.getElementById('ateliers-grid');
  if (ateliersGrid) {
    var lang = ateliersGrid.getAttribute('data-lang') || 'FR';
    var locale = lang === 'EN' ? 'en-GB' : 'fr-FR';
    var labelReserve = ateliersGrid.getAttribute('data-label-reserve') || 'Réserver ce créneau';
    var labelComplet = ateliersGrid.getAttribute('data-label-complet') || 'Complet';
    var labelEmpty = ateliersGrid.getAttribute('data-empty') || '';
    var curLabel = lang === 'EN' ? '' : '';

    function fmtDate(iso) {
      var d = new Date(iso + 'T00:00:00');
      if (isNaN(d)) return iso;
      return d.toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }
    function fmtPrice(n) {
      if (n === null || n === undefined || n === '') return '';
      return lang === 'EN' ? '€' + n : n + ' €';
    }
    function matchesLang(a) {
      var l = a.langue || '';
      return l.indexOf(lang) !== -1; // "FR", "EN" ou "FR + EN"
    }

    function render(list) {
      var today = new Date(); today.setHours(0, 0, 0, 0);
      var items = (list || [])
        .filter(function (a) { return a.statut !== 'Masqué' && matchesLang(a); })
        .filter(function (a) { return a.date && new Date(a.date + 'T00:00:00') >= today; })
        .sort(function (x, y) { return x.date < y.date ? -1 : 1; });

      if (!items.length) {
        ateliersGrid.innerHTML = '<div class="ateliers-empty">' + labelEmpty + '</div>';
        return;
      }
      ateliersGrid.innerHTML = items.map(function (a) {
        var complet = a.statut === 'Complet';
        var tarifs = '';
        if (a.tarif_adulte != null) tarifs += (lang === 'EN' ? 'Adult ' : 'Adulte ') + fmtPrice(a.tarif_adulte);
        if (a.tarif_enfant != null) tarifs += (lang === 'EN' ? ' / Child ' : ' / Enfant ') + fmtPrice(a.tarif_enfant);
        var action = complet
          ? '<span class="atelier-badge-complet">' + labelComplet + '</span>'
          : '<button type="button" class="btn atelier-reserve">' + labelReserve + '</button>';
        return '<div class="atelier-card' + (complet ? ' is-complet' : '') + '" data-theme="' + esc(a.theme) + '" data-date="' + fmtDate(a.date) + '" data-horaire="' + esc(a.horaire || '') + '">' +
          '<span class="atelier-date">' + fmtDate(a.date) + '</span>' +
          '<span class="atelier-theme">' + esc(a.theme) + '</span>' +
          (a.horaire ? '<span class="atelier-meta">' + esc(a.horaire) + '</span>' : '') +
          (a.description ? '<span class="atelier-meta">' + esc(a.description) + '</span>' : '') +
          (tarifs ? '<span class="atelier-tarifs">' + tarifs + '</span>' : '') +
          action +
          '</div>';
      }).join('');
      wireReserveButtons();
    }

    function esc(s) {
      return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
      });
    }

    // On tente le connecteur Notion (OVH), sinon le JSON statique (apercu Vercel)
    function load() {
      fetch('/api/ateliers.php', { cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (d) { render(d.ateliers); })
        .catch(function () {
          fetch('/data/ateliers.json', { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) { render(d.ateliers); })
            .catch(function () { ateliersGrid.innerHTML = '<div class="ateliers-empty">' + labelEmpty + '</div>'; });
        });
    }
    load();

    // Modale
    var modal = document.getElementById('reservation-modal');
    var modalForm = modal ? modal.querySelector('form') : null;
    var modalAtelier = modal ? modal.querySelector('.modal-atelier') : null;
    var modalHidden = modal ? modal.querySelector('input[name="atelier"]') : null;
    var modalStatus = modal ? modal.querySelector('.form-status') : null;

    function openModal(theme, date, horaire) {
      if (!modal) return;
      var label = theme + ', ' + date + (horaire ? ' (' + horaire + ')' : '');
      if (modalAtelier) modalAtelier.innerHTML = (lang === 'EN' ? 'Chosen workshop: ' : 'Atelier choisi : ') + '<b>' + esc(label) + '</b>';
      if (modalHidden) modalHidden.value = label;
      if (modalStatus) { modalStatus.style.display = 'none'; modalStatus.textContent = ''; }
      modal.classList.add('open');
      document.body.style.overflow = 'hidden';
    }
    function closeModal() {
      if (!modal) return;
      modal.classList.remove('open');
      document.body.style.overflow = '';
    }
    function wireReserveButtons() {
      ateliersGrid.querySelectorAll('.atelier-reserve').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var card = btn.closest('.atelier-card');
          openModal(card.getAttribute('data-theme'), card.getAttribute('data-date'), card.getAttribute('data-horaire'));
        });
      });
    }
    if (modal) {
      modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
      var closeBtn = modal.querySelector('.modal-close');
      if (closeBtn) closeBtn.addEventListener('click', closeModal);
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
    }
    if (modalForm) {
      modalForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var submitBtn = modalForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        fetch(modalForm.action, { method: 'POST', body: new FormData(modalForm) })
          .then(function (r) { return r.json().then(function (data) { return { ok: r.ok && data.ok, data: data }; }); })
          .then(function (result) {
            modalStatus.textContent = result.ok ? modalStatus.getAttribute('data-success') : (result.data.message || modalStatus.getAttribute('data-error'));
            modalStatus.style.color = result.ok ? 'var(--green)' : 'var(--rose)';
            modalStatus.style.display = 'block';
            if (result.ok) modalForm.reset();
          })
          .catch(function () {
            modalStatus.textContent = modalStatus.getAttribute('data-error');
            modalStatus.style.color = 'var(--rose)';
            modalStatus.style.display = 'block';
          })
          .finally(function () { submitBtn.disabled = false; });
      });
    }
  }

  // Carrousels photo : defilement par blocs (3 sur ordinateur, 2 puis 1 sur mobile)
  document.querySelectorAll('.carousel').forEach(function (c) {
    var track = c.querySelector('.carousel-track');
    var prev = c.querySelector('.carousel-prev');
    var next = c.querySelector('.carousel-next');
    if (!track || !prev || !next) return;
    function update() {
      prev.disabled = track.scrollLeft <= 4;
      next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
    }
    prev.addEventListener('click', function () { track.scrollBy({ left: -track.clientWidth, behavior: 'smooth' }); });
    next.addEventListener('click', function () { track.scrollBy({ left: track.clientWidth, behavior: 'smooth' }); });
    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    window.addEventListener('load', update);
    // ResizeObserver : l'etat des fleches est recalcule des que la mise en page change
    // (chargement des images, rotation, redimensionnement), sans dependre de l'evenement resize
    if (typeof ResizeObserver !== 'undefined') { new ResizeObserver(update).observe(track); }
    track.querySelectorAll('img').forEach(function (img) {
      if (!img.complete) img.addEventListener('load', update, { once: true });
    });
    update();
  });

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
