/**
 * ReviewIA — app.js
 * Arquivo único de JavaScript para todo o site.
 * Detecta a página atual pelo DOM e inicializa apenas o necessário.
 */

document.addEventListener('DOMContentLoaded', () => {
  initScrollReveal();
  initReadProgress();
  initProductHeroAnimations();
  initFAQ();
  initFilterButtons();
  initCatChips();
  initShareButtons();
  initTOCHighlight();
});

/* ── Scroll reveal ───────────────────────────────────────── */
function initScrollReveal() {
  const els = document.querySelectorAll('.reveal');
  if (!els.length) return;

  const obs = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        obs.unobserve(e.target);
      }
    });
  }, { threshold: 0.08 });

  els.forEach(el => obs.observe(el));
}

/* ── Barra de progresso de leitura ───────────────────────── */
function initReadProgress() {
  const fill = document.getElementById('readProgress');
  if (!fill) return;

  window.addEventListener('scroll', () => {
    const h = document.documentElement;
    const pct = h.scrollTop / (h.scrollHeight - h.clientHeight) * 100;
    fill.style.width = Math.min(pct, 100) + '%';
  }, { passive: true });
}

/* ── Animações de score (ring e gauge) ───────────────────── */
function initProductHeroAnimations() {
  // Score ring (hero do produto)
  const ring = document.getElementById('ringFill');
  if (ring) {
    const target = parseFloat(ring.dataset.offset || '0');
    setTimeout(() => { ring.style.strokeDashoffset = target; }, 350);
  }

  // Score gauge (sidebar)
  const gauge = document.getElementById('gaugeFill');
  if (gauge) {
    const target = parseFloat(gauge.dataset.target || '0');
    setTimeout(() => { gauge.style.strokeDashoffset = target; }, 450);
  }
}

/* ── FAQ accordion ───────────────────────────────────────── */
function initFAQ() {
  document.querySelectorAll('.faq-q').forEach(q => {
    q.addEventListener('click', function () {
      const item = this.closest('.faq-item');
      const isOpen = item.classList.contains('open');
      // Fecha todos
      document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
      // Abre o clicado (se estava fechado)
      if (!isOpen) item.classList.add('open');
    });
  });
}

/* ── Botões de filtro da index ───────────────────────────── */
function initFilterButtons() {
  document.querySelectorAll('.f-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.f-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      // TODO: integrar com o back-end via AJAX ou link de ordenação
    });
  });
}

/* ── Chips de categoria ──────────────────────────────────── */
function initCatChips() {
  document.querySelectorAll('.cat-chip[data-url]').forEach(chip => {
    chip.addEventListener('click', function (e) {
      if (this.dataset.url) return; // deixa o link agir normalmente
      e.preventDefault();
      document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
    });
  });
}

/* ── Compartilhar — copiar link ──────────────────────────── */
function initShareButtons() {
  const copyBtn = document.getElementById('copyBtn');
  if (!copyBtn) return;

  copyBtn.addEventListener('click', () => {
    navigator.clipboard.writeText(location.href).then(() => {
      const icon = copyBtn.querySelector('i');
      if (icon) { icon.className = 'fas fa-check'; }
      setTimeout(() => {
        if (icon) { icon.className = 'fas fa-link'; }
      }, 2000);
    }).catch(() => {
      // Fallback para navegadores sem clipboard API
      const ta = document.createElement('textarea');
      ta.value = location.href;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    });
  });
}

/* ── Destaque ativo no TOC durante scroll ────────────────── */
function initTOCHighlight() {
  const links = document.querySelectorAll('.toc a[href^="#"]');
  if (!links.length) return;

  const sections = Array.from(links).map(l => {
    const id = l.getAttribute('href').slice(1);
    return document.getElementById(id);
  }).filter(Boolean);

  const obs = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        const id = e.target.id;
        links.forEach(l => {
          l.style.color = l.getAttribute('href') === '#' + id
            ? 'var(--accent)'
            : '';
        });
      }
    });
  }, { rootMargin: '-20% 0px -70% 0px' });

  sections.forEach(s => obs.observe(s));
}
