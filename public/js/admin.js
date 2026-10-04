/**
 * Admin Dashboard Controller
 * Handles Sanctum authentication, live metrics, queue processing, maintenance mode, and pricing updates.
 */
document.addEventListener('DOMContentLoaded', () => {
  const studentTab = document.getElementById('student-tab');
  const adminTab = document.getElementById('admin-tab');
  const studentView = document.getElementById('student-view');
  const adminView = document.getElementById('admin-view');
  const adminGate = document.getElementById('admin-gate');
  const adminLoginForm = document.getElementById('admin-login-form');
  const adminLoginError = document.getElementById('admin-login-error');
  const adminLogoutBtn = document.getElementById('admin-logout-btn');

  // Metrics
  const queueMetric = document.getElementById('queue-metric');
  const activeCount = document.getElementById('active-count');
  const revenueMetric = document.getElementById('revenue-metric');
  const printerMetric = document.getElementById('printer-metric');
  const printerStatus = document.getElementById('printer-one-status');
  const printerMaintenance = document.getElementById('printer-maintenance');

  // Pricing & Table
  const bwPrice = document.getElementById('admin-bw-price');
  const colourPrice = document.getElementById('admin-colour-price');
  const savePricingBtn = document.getElementById('btn-save-pricing');
  const adminList = document.getElementById('admin-job-list');
  const jobTemplate = document.getElementById('admin-job-template');

  let adminUnlocked = !!PrintGoAPI.getAuthToken();
  let pollInterval = null;

  const adminCancelBtn = document.getElementById('admin-cancel-btn');
  const studentKioskIndicator = document.getElementById('student-kiosk-indicator');
  const kioskLogo = document.getElementById('kiosk-logo');

  // Rolling tap detector: 5 taps to enter admin, 3 taps to return to student desk
  // Silent and discreet: no badges or popups displayed to users
  let tapTimestamps = [];

  function handleLogoTap(e) {
    if (e && e.type === 'click') {
      e.stopPropagation();
    }

    const isInAdmin = adminView && !adminView.classList.contains('hidden');
    const requiredTaps = isInAdmin ? 3 : 5;

    const now = Date.now();
    // Keep taps that happened within the last 3.5 seconds
    tapTimestamps = tapTimestamps.filter(t => now - t < 3500);
    tapTimestamps.push(now);

    const count = tapTimestamps.length;

    // Trigger action when required taps reached
    if (count >= requiredTaps) {
      tapTimestamps = [];
      if (isInAdmin) {
        showView('student');
        if (window.location.hash === '#admin') {
          history.pushState(null, null, window.location.pathname);
        }
      } else {
        window.location.hash = 'admin';
        showView('admin');
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => {
          const loginField = document.getElementById('admin-login') || document.getElementById('admin-email');
          if (loginField) loginField.focus();
        }, 150);
      }
    }
  }

  if (kioskLogo) {
    kioskLogo.addEventListener('click', handleLogoTap);
    kioskLogo.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        handleLogoTap(e);
      }
    });
  }

  // Tab switching
  function showView(view) {
    tapTimestamps = [];
    const isStudent = view === 'student';
    studentView.classList.toggle('hidden', !isStudent);
    adminView.classList.toggle('hidden', isStudent);

    if (studentKioskIndicator) {
      if (isStudent) {
        studentKioskIndicator.textContent = 'Student Kiosk Mode';
        studentKioskIndicator.className = 'text-xs font-semibold text-[#9fb3c8] hidden sm:inline-block';
      } else {
        studentKioskIndicator.textContent = 'Admin Mode';
        studentKioskIndicator.className = 'text-xs font-bold text-[#e56b4f] bg-[#e56b4f]/15 px-2.5 py-1 rounded-full hidden sm:inline-block tracking-wide';
      }
    }

    if (kioskLogo) {
      kioskLogo.setAttribute('title', 'PrintGO');
    }

    if (studentTab) {
      studentTab.classList.toggle('active', isStudent);
      studentTab.setAttribute('aria-selected', String(isStudent));
    }
    if (adminTab) {
      adminTab.classList.toggle('active', !isStudent);
      adminTab.setAttribute('aria-selected', String(!isStudent));
    }

    if (!isStudent) {
      if (adminUnlocked) {
        adminGate.classList.add('hidden');
        [...adminView.children].filter(child => child !== adminGate).forEach(child => child.classList.remove('hidden'));
        loadAdminData();
        if (!pollInterval) {
          pollInterval = setInterval(loadAdminData, 4000);
        }
      } else {
        adminGate.classList.remove('hidden');
        [...adminView.children].filter(child => child !== adminGate).forEach(child => child.classList.add('hidden'));
        if (pollInterval) {
          clearInterval(pollInterval);
          pollInterval = null;
        }
        setTimeout(() => {
          const loginField = document.getElementById('admin-login') || document.getElementById('admin-email');
          if (loginField) loginField.focus();
        }, 100);
      }
    } else {
      if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
      }
    }
  }

  // Handle URL hash and route initialization
  const startView = document.body?.dataset?.startView;
  if (startView === 'admin' || window.location.hash === '#admin' || window.location.pathname === '/admin') {
    showView('admin');
  }

  window.addEventListener('hashchange', () => {
    if (window.location.hash === '#admin') {
      showView('admin');
    } else if (window.location.hash === '' || window.location.hash === '#kiosk' || window.location.hash === '#student') {
      showView('student');
    }
  });

  // Return to student view button listeners
  const returnButtons = [adminCancelBtn];
  returnButtons.forEach(btn => {
    if (btn) {
      btn.addEventListener('click', () => {
        showView('student');
        if (window.location.hash === '#admin') {
          history.pushState(null, null, window.location.pathname);
        }
      });
    }
  });

  if (studentTab) {
    studentTab.addEventListener('click', () => {
      window.location.hash = '';
      showView('student');
    });
  }
  if (adminTab) {
    adminTab.addEventListener('click', () => {
      window.location.hash = 'admin';
      showView('admin');
    });
  }

  // Admin Login
  if (adminLoginForm) {
    adminLoginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const loginInput = document.getElementById('admin-login') || document.getElementById('admin-email');
      const passwordInput = document.getElementById('admin-password');
      const login = loginInput ? loginInput.value.trim() : '';
      const password = passwordInput ? passwordInput.value : '';
      adminLoginError.classList.add('hidden');

      try {
        await PrintGoAPI.adminLogin(login, password);
        adminUnlocked = true;
        showView('admin');
      } catch (err) {
        adminLoginError.textContent = err.message || 'Invalid email/username or password.';
        adminLoginError.classList.remove('hidden');
      }
    });
  }

  // Admin Logout
  if (adminLogoutBtn) {
    adminLogoutBtn.addEventListener('click', async () => {
      await PrintGoAPI.adminLogout();
      adminUnlocked = false;
      showView('student');
    });
  }

  // Load Admin Data
  async function loadAdminData() {
    if (!adminUnlocked) return;
    loadPricing();

    try {
      // 1. Dashboard Metrics
      const metricsRes = await PrintGoAPI.getDashboardMetrics();
      if (metricsRes && metricsRes.data) {
        const d = metricsRes.data;
        if (revenueMetric) revenueMetric.textContent = `₱${Number(d.earnings.daily || 0).toFixed(2)}`;
        if (queueMetric) queueMetric.textContent = String(d.active_jobs || 0);
        if (activeCount) activeCount.textContent = `${d.active_jobs || 0} active`;

        if (printerMetric) {
          const isReady = d.printer && d.printer.is_ready;
          printerMetric.textContent = isReady ? '1/1' : '0/1';
        }

        // Maintenance state
        if (d.maintenance && printerMaintenance && printerStatus) {
          const isMaint = d.maintenance.enabled;
          printerStatus.textContent = isMaint ? 'Maintenance' : 'Online & Ready';
          printerStatus.className = isMaint
            ? 'canva-tag rounded-full bg-[#b23b27] text-white px-2 py-1 text-xs font-bold'
            : 'canva-tag rounded-full bg-[#1f6f5e] text-white px-2 py-1 text-xs font-bold';
          printerMaintenance.textContent = isMaint ? 'Disable Maintenance (Go Online)' : 'Set Maintenance Mode';
          printerMaintenance.dataset.enabled = isMaint ? 'true' : 'false';
        }
      }

      // 2. Pending & Active Jobs
      const pendingRes = await PrintGoAPI.getPendingJobs();
      const txRes = await PrintGoAPI.getTransactions({ per_page: 8 });

      renderAdminJobs(pendingRes?.data || [], txRes?.data?.transactions || []);
    } catch (err) {
      if (err.code === 'UNAUTHORIZED' || err.status === 401) {
        adminUnlocked = false;
        PrintGoAPI.setAuthToken(null);
        showView('admin');
      }
      console.error('Error loading admin dashboard:', err);
    }
  }

  // Render Jobs in Table
  function renderAdminJobs(pendingJobs, recentTransactions) {
    if (!adminList || !jobTemplate) return;

    adminList.innerHTML = '';

    const allRows = [];

    // Map pending jobs
    pendingJobs.forEach(job => {
      allRows.push({
        id: job.id.substring(0, 8).toUpperCase(),
        fullId: job.id,
        isJob: true,
        document: job.file_name || 'Document.pdf',
        setup: `${job.color_mode === 'color' ? 'Colour' : 'B&W'} · ${job.paper_size} · ${job.copies} copy`,
        status: job.status.charAt(0).toUpperCase() + job.status.slice(1),
        statusClass: `status-${job.status}`,
        actionText: job.status === 'queued' ? 'Start Print' : 'Mark Completed',
        nextStatus: job.status === 'queued' ? 'printing' : 'completed',
      });
    });

    // Map completed transactions
    recentTransactions.forEach(tx => {
      allRows.push({
        id: tx.id.substring(0, 8).toUpperCase(),
        fullId: tx.id,
        isJob: false,
        document: tx.file_name || `${tx.file_type.toUpperCase()} File`,
        setup: `${tx.color_mode === 'color' ? 'Colour' : 'B&W'} · ${tx.paper_size} · ${tx.copies} copy`,
        status: 'Completed',
        statusClass: 'status-ready',
        actionText: 'Archived',
        nextStatus: null,
      });
    });

    if (allRows.length === 0) {
      adminList.innerHTML = '<tr><td colspan="5" class="px-5 py-6 text-center text-[#627d98]">No active or recent jobs found.</td></tr>';
      return;
    }

    allRows.slice(0, 10).forEach(item => {
      const fragment = jobTemplate.content.cloneNode(true);
      const row = fragment.querySelector('tr');
      fragment.querySelector('.job-id').textContent = item.id;
      fragment.querySelector('.job-document').textContent = item.document;
      fragment.querySelector('.job-setup').textContent = item.setup;

      const badge = fragment.querySelector('.job-status');
      badge.textContent = item.status;
      badge.className = `job-status rounded-full px-2.5 py-1 text-xs font-bold ${item.statusClass}`;

      const actionBtn = fragment.querySelector('.job-action');
      actionBtn.textContent = item.actionText;

      if (!item.nextStatus) {
        actionBtn.disabled = true;
        actionBtn.className = 'job-action rounded-lg bg-gray-200 px-3 py-2 text-xs font-bold text-gray-500 cursor-not-allowed';
      } else {
        actionBtn.addEventListener('click', async () => {
          actionBtn.disabled = true;
          actionBtn.textContent = 'Updating...';
          try {
            await PrintGoAPI.updateJobStatus(item.fullId, item.nextStatus);
            await loadAdminData();
          } catch (e) {
            alert('Failed to update job status: ' + (e.message || 'Error'));
            actionBtn.disabled = false;
          }
        });
      }

      adminList.appendChild(fragment);
    });
  }

  // Toggle Maintenance Mode
  if (printerMaintenance) {
    printerMaintenance.addEventListener('click', async () => {
      const isCurrentlyEnabled = printerMaintenance.dataset.enabled === 'true';
      const newEnabled = !isCurrentlyEnabled;

      printerMaintenance.disabled = true;
      try {
        await PrintGoAPI.updateMaintenance(
          newEnabled,
          newEnabled ? 'Printer is currently undergoing maintenance. Please check back shortly.' : null
        );
        await loadAdminData();
      } catch (err) {
        alert('Failed to toggle maintenance mode: ' + (err.message || 'Error'));
      } finally {
        printerMaintenance.disabled = false;
      }
    });
  }

  // Load & Save Pricing Matrix
  async function loadPricing() {
    try {
      const res = await PrintGoAPI.getPricingMatrix();
      if (res && res.data && res.data.pricing) {
        const p = res.data.pricing;
        if (bwPrice && p.bw_a4) bwPrice.value = p.bw_a4;
        if (colourPrice && p.color_a4) colourPrice.value = p.color_a4;
      }
    } catch (e) {
      console.warn('Could not load pricing matrix:', e);
    }
  }

  if (savePricingBtn) {
    savePricingBtn.addEventListener('click', async () => {
      savePricingBtn.disabled = true;
      savePricingBtn.textContent = 'Saving...';

      try {
        await PrintGoAPI.updatePricingMatrix({
          bw_a4: Number(bwPrice.value) || 2.00,
          color_a4: Number(colourPrice.value) || 5.00,
        });
        savePricingBtn.textContent = '✓ Saved!';
        setTimeout(() => {
          savePricingBtn.textContent = 'Update Pricing';
          savePricingBtn.disabled = false;
        }, 1500);
      } catch (err) {
        alert('Failed to update pricing: ' + (err.message || 'Error'));
        savePricingBtn.disabled = false;
        savePricingBtn.textContent = 'Update Pricing';
      }
    });
  }
});
