/* =============================================================
   EduBoard — Main JavaScript
   ============================================================= */

document.addEventListener('DOMContentLoaded', () => {

  // ── Auto-dismiss flash alerts ───────────────────────────────
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .4s ease';
      el.style.opacity    = '0';
      setTimeout(() => el.remove(), 420);
    }, 5000);
  });

  // ── Confirm dialogs via data-confirm attr ───────────────────
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function (e) {
      const msg = this.dataset.confirm || 'Are you sure?';
      if (!confirm(msg)) e.preventDefault();
    });
  });

  // ── Password visibility toggle (generic) ───────────────────
  document.querySelectorAll('[data-toggle-pw]').forEach(btn => {
    btn.addEventListener('click', function () {
      const target = document.getElementById(this.dataset.togglePw);
      const icon   = this.querySelector('i');
      if (!target) return;
      if (target.type === 'password') {
        target.type = 'text';
        icon?.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        target.type = 'password';
        icon?.classList.replace('fa-eye-slash', 'fa-eye');
      }
    });
  });

  // ── OTP box auto-advance ────────────────────────────────────
  const otpBoxes = document.querySelectorAll('.otp-box');
  otpBoxes.forEach((box, i) => {
    box.addEventListener('input', () => {
      box.value = box.value.replace(/\D/g, '').slice(-1);
      if (box.value && otpBoxes[i + 1]) otpBoxes[i + 1].focus();
    });
    box.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !box.value && otpBoxes[i - 1]) {
        otpBoxes[i - 1].focus();
      }
    });
    box.addEventListener('paste', e => {
      const raw = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
      raw.split('').forEach((ch, j) => { if (otpBoxes[i + j]) otpBoxes[i + j].value = ch; });
      e.preventDefault();
    });
  });

  // ── Filter form auto-submit on select change ────────────────
  document.querySelectorAll('.filter-bar select').forEach(sel => {
    sel.addEventListener('change', () => sel.closest('form')?.submit());
  });

  // ── Scope select conditional fields ────────────────────────
  const scopeSelect = document.getElementById('scopeSelect');
  if (scopeSelect) {
    function updateScopeFields() {
      const name = scopeSelect.options[scopeSelect.selectedIndex]?.dataset.name || '';
      const roleG = document.getElementById('roleTargetGroup');
      const userG = document.getElementById('userTargetGroup');
      if (roleG) roleG.style.display = (name === 'Role Based') ? '' : 'none';
      if (userG) userG.style.display = (name === 'Individual')  ? '' : 'none';
    }
    scopeSelect.addEventListener('change', updateScopeFields);
    updateScopeFields();
  }

  // ── File drop zone ──────────────────────────────────────────
  const dropZone = document.getElementById('dropZone');
  const fileInput = document.getElementById('attachFile');
  const fileDisplay = document.getElementById('fileNameDisplay');

  if (dropZone && fileInput) {
    fileInput.addEventListener('change', () => {
      if (fileInput.files[0] && fileDisplay) {
        fileDisplay.textContent = '📎 ' + fileInput.files[0].name;
      }
    });
    dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', ()  => dropZone.classList.remove('drag-over'));
    dropZone.addEventListener('drop', e => {
      e.preventDefault();
      dropZone.classList.remove('drag-over');
      const f = e.dataTransfer.files[0];
      if (f) {
        const dt = new DataTransfer();
        dt.items.add(f);
        fileInput.files = dt.files;
        if (fileDisplay) fileDisplay.textContent = '📎 ' + f.name;
      }
    });
  }

  // ── Mobile sidebar toggle ───────────────────────────────────
  const sidebar   = document.getElementById('sidebar');
  const togBtn    = document.getElementById('sidebarToggle');
  const mq        = window.matchMedia('(max-width:900px)');

  function handleMQ(e) {
    if (togBtn) togBtn.style.display = e.matches ? 'flex' : 'none';
  }
  mq.addEventListener('change', handleMQ);
  handleMQ(mq);

  document.addEventListener('click', e => {
    if (mq.matches && sidebar?.classList.contains('open') &&
        !sidebar.contains(e.target) && e.target !== togBtn) {
      sidebar.classList.remove('open');
    }
  });

  // ── Animated number counters for stat cards ─────────────────
  const statNums = document.querySelectorAll('.stat-num');
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el  = entry.target;
      const end = parseInt(el.textContent, 10);
      if (isNaN(end) || end === 0) return;
      let current = 0;
      const step  = Math.ceil(end / 30);
      const timer = setInterval(() => {
        current = Math.min(current + step, end);
        el.textContent = current;
        if (current >= end) clearInterval(timer);
      }, 24);
      observer.unobserve(el);
    });
  }, { threshold: 0.5 });
  statNums.forEach(el => observer.observe(el));

  // ── Staggered card animation ────────────────────────────────
  document.querySelectorAll('.notice-card, .stat-card').forEach((card, i) => {
    card.style.animationDelay = (i * 0.04) + 's';
  });

  // ── Modal close on Escape ───────────────────────────────────
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    }
  });

  // ── Sticky filter bar behavior ──────────────────────────────
  const filterBar = document.querySelector('.filter-bar');
  if (filterBar) {
    const sentinel = document.createElement('div');
    filterBar.parentNode.insertBefore(sentinel, filterBar);
    new IntersectionObserver(([entry]) => {
      filterBar.classList.toggle('stuck', !entry.isIntersecting);
    }).observe(sentinel);
  }

  // ── Active nav link highlight ───────────────────────────────
  const currentPath = window.location.pathname.split('/').pop();
  document.querySelectorAll('.sidebar-link').forEach(link => {
    const href = link.getAttribute('href')?.split('/').pop()?.split('?')[0];
    if (href === currentPath) link.classList.add('active');
  });

});
