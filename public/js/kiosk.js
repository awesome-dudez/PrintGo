/**
 * Student Kiosk Flow Controller
 * Handles document upload, configuration, authoritative pricing, and payment simulation.
 */
document.addEventListener('DOMContentLoaded', () => {
  // Elements
  const fileInput = document.getElementById('document-file');
  const fileDropZone = document.getElementById('file-drop-zone');
  const fileName = document.getElementById('file-name');
  const form = document.getElementById('print-form');
  const validation = document.getElementById('validation-message');
  const colour = document.getElementById('colour-mode');
  const paper = document.getElementById('paper-size');
  const copies = document.getElementById('copies');
  const pageRange = document.getElementById('page-range');
  const summaryFile = document.getElementById('summary-file');
  const summarySpecs = document.getElementById('summary-specs');
  const estimate = document.getElementById('estimate');
  const confirmation = document.getElementById('student-confirmation');
  const confirmationText = document.getElementById('student-confirmation-text');
  const submitButton = document.getElementById('submit-button');
  const submitButtonText = document.getElementById('submit-button-text');
  const maintenanceBanner = document.getElementById('maintenance-banner');
  const announcementContainer = document.getElementById('announcements-banner');

  // Modals
  const paymentModal = document.getElementById('payment-modal');
  const paymentRequired = document.getElementById('payment-required');
  const paymentInserted = document.getElementById('payment-inserted');
  const paymentRemaining = document.getElementById('payment-remaining');
  const paymentStatusText = document.getElementById('payment-status-text');
  const paymentProgress = document.getElementById('payment-progress-bar');
  const printProgressSection = document.getElementById('print-progress-section');
  const printProgressBar = document.getElementById('print-progress-bar');
  const printProgressLabel = document.getElementById('print-progress-label');

  // State
  let currentFile = null;
  let uploadedFileRecord = null;
  let currentSessionId = null;
  let currentJobRecord = null;
  let calculatedPrice = null;
  let paymentPollInterval = null;
  let jobPollInterval = null;

  // Initialize System Health & Announcements
  async function initSystem() {
    try {
      const statusRes = await PrintGoAPI.getSystemStatus();
      if (statusRes && statusRes.data) {
        const { is_operational, maintenance_enabled, maintenance_message, printer } = statusRes.data;

        if (maintenance_enabled || !is_operational) {
          if (maintenanceBanner) {
            maintenanceBanner.classList.remove('hidden');
            const msgEl = maintenanceBanner.querySelector('.maintenance-msg');
            if (msgEl) {
              msgEl.textContent = maintenance_message || (printer && !printer.is_ready ? 'Printer is currently offline.' : 'Kiosk is currently undergoing maintenance.');
            }
          }
          if (submitButton) {
            submitButton.disabled = true;
          }
        }
      }

      // Announcements
      const annRes = await PrintGoAPI.getAnnouncements();
      if (annRes && annRes.data && annRes.data.length > 0 && announcementContainer) {
        announcementContainer.classList.remove('hidden');
        announcementContainer.innerHTML = annRes.data.map(a => `
          <div class="flex items-center gap-2 text-xs font-semibold py-1">
            <span class="inline-block w-2 h-2 rounded-full bg-[#e56b4f]"></span>
            <span><strong>${a.title}:</strong> ${a.content}</span>
          </div>
        `).join('');
      }
    } catch (e) {
      console.warn('Could not fetch system status:', e);
    }
  }

  initSystem();

  // Settings helper
  function getPrintSettings() {
    const count = Math.max(1, Math.min(50, Number(copies.value) || 1));
    copies.value = count;
    const sidesEl = document.querySelector('input[name="sides"]:checked');
    const sides = sidesEl ? sidesEl.value : 'single';
    const rawPaper = (paper.value || 'a4').toLowerCase();
    const paperSizeMap = {
      'a4': 'A4',
      'letter': 'Letter',
      'legal': 'Legal',
    };
    const paperSize = paperSizeMap[rawPaper] || 'A4';
    const range = (pageRange && pageRange.value.trim()) ? pageRange.value.trim() : 'All';

    return {
      copies: count,
      color_mode: colour.value === 'colour' ? 'color' : 'bw',
      paper_size: paperSize,
      paper_type: 'bond',
      orientation: 'portrait',
      duplex: sides === 'double',
      page_range: range,
    };
  }

  // Authoritative server-side price update
  async function updatePrice() {
    const s = getPrintSettings();
    const pageInfo = uploadedFileRecord 
      ? ` · ${uploadedFileRecord.page_count} ${uploadedFileRecord.page_count === 1 ? 'page' : 'pages'}${s.page_range !== 'All' ? ` (Range: ${s.page_range})` : ''}` 
      : '';
    const setupStr = `${s.color_mode === 'color' ? 'Colour' : 'B&W'} · ${s.paper_size} · ${s.copies} ${s.copies === 1 ? 'copy' : 'copies'}${s.duplex ? ' (Duplex)' : ''}${pageInfo}`;
    summarySpecs.textContent = setupStr;

    if (!uploadedFileRecord) {
      const basePerPg = s.color_mode === 'color' ? 5 : 2;
      const sizeExtra = s.paper_size === 'Legal' ? 1 : 0;
      estimate.textContent = `₱${((basePerPg + sizeExtra) * s.copies).toFixed(2)}`;
      return;
    }

    try {
      const priceRes = await PrintGoAPI.calculatePrice({
        file_id: uploadedFileRecord.id,
        copies: s.copies,
        paper_size: s.paper_size,
        paper_type: s.paper_type,
        orientation: s.orientation,
        color_mode: s.color_mode,
        duplex: s.duplex,
        adjustments: {
          page_range: s.page_range,
        },
      });

      if (priceRes && priceRes.data) {
        calculatedPrice = priceRes.data;
        const total = calculatedPrice.total ?? calculatedPrice.total_amount ?? 0;
        estimate.textContent = `₱${Number(total).toFixed(2)}`;
      }
    } catch (err) {
      console.error('Pricing error:', err);
    }
  }

  // Handle file selection and upload to backend
  async function handleFileSelected(file) {
    if (!file) return;

    currentFile = file;
    fileName.textContent = `Uploading ${file.name}...`;
    fileName.classList.remove('hidden');
    fileDropZone.classList.add('is-ready');
    validation.classList.add('hidden');
    summaryFile.textContent = file.name;

    try {
      const uploadRes = await PrintGoAPI.uploadFile(file, currentSessionId);
      if (uploadRes && uploadRes.data) {
        uploadedFileRecord = uploadRes.data;
        currentSessionId = uploadedFileRecord.session_id;

        fileName.textContent = `✓ ${file.name} (${uploadedFileRecord.page_count} ${uploadedFileRecord.page_count === 1 ? 'page' : 'pages'})`;
        fileName.classList.remove('text-[#b23b27]');
        fileName.classList.add('text-[#1f6f5e]');

        await updatePrice();
      }
    } catch (err) {
      console.error('File upload error:', err);
      fileName.textContent = `Upload failed: ${err.message || 'Unsupported file or size exceeded.'}`;
      fileName.classList.remove('text-[#1f6f5e]');
      fileName.classList.add('text-[#b23b27]');
      validation.textContent = err.message || 'Error uploading file.';
      validation.classList.remove('hidden');
      uploadedFileRecord = null;
    }
  }

  fileInput.addEventListener('change', () => {
    if (fileInput.files && fileInput.files[0]) {
      handleFileSelected(fileInput.files[0]);
    }
  });

  // Drop zone click triggers file picker
  fileDropZone.addEventListener('click', (e) => {
    if (e.target !== fileInput) {
      fileInput.click();
    }
  });

  // Drag & drop support
  ['dragenter', 'dragover'].forEach(eventName => {
    fileDropZone.addEventListener(eventName, (e) => {
      e.preventDefault();
      fileDropZone.classList.add('dragover');
    });
  });

  ['dragleave', 'drop'].forEach(eventName => {
    fileDropZone.addEventListener(eventName, (e) => {
      e.preventDefault();
      fileDropZone.classList.remove('dragover');
    });
  });

  fileDropZone.addEventListener('drop', (e) => {
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
      fileInput.files = e.dataTransfer.files;
      handleFileSelected(e.dataTransfer.files[0]);
    }
  });

  // Option listeners
  [colour, paper, copies].forEach(el => {
    if (el) {
      el.addEventListener('input', updatePrice);
      el.addEventListener('change', updatePrice);
    }
  });
  if (pageRange) {
    pageRange.addEventListener('input', updatePrice);
    pageRange.addEventListener('change', updatePrice);
  }
  document.querySelectorAll('input[name="sides"]').forEach(el => {
    el.addEventListener('change', updatePrice);
  });

  // Submit print job & open payment screen
  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!uploadedFileRecord) {
      validation.textContent = 'Please choose a document before submitting your print job.';
      validation.classList.remove('hidden');
      fileInput.focus();
      return;
    }

    const s = getPrintSettings();
    submitButton.disabled = true;
    submitButtonText.textContent = 'Queuing Job...';

    try {
      const idempotencyKey = 'kiosk-job-' + Date.now() + '-' + Math.random().toString(36).substring(2, 9);
      const jobRes = await PrintGoAPI.submitPrintJob({
        session_id: currentSessionId,
        file_id: uploadedFileRecord.id,
        copies: s.copies,
        paper_size: s.paper_size,
        paper_type: s.paper_type,
        orientation: s.orientation,
        color_mode: s.color_mode,
        duplex: s.duplex,
        adjustments: {
          page_range: s.page_range,
        },
        idempotency_key: idempotencyKey,
      });

      if (jobRes && jobRes.data) {
        currentJobRecord = jobRes.data;

        // Show confirmation
        confirmationText.textContent = `Queue reference: ${currentJobRecord.id.substring(0, 8)}. Please insert coins to start printing.`;
        confirmation.classList.remove('hidden');
        validation.classList.add('hidden');

        // Open Payment Modal
        openPaymentModal();
      }
    } catch (err) {
      console.error('Job submission error:', err);
      validation.textContent = err.message || 'Failed to submit print job.';
      validation.classList.remove('hidden');
    } finally {
      submitButton.disabled = false;
      submitButtonText.textContent = 'Send Print Job';
    }
  });

  // Payment Modal Operations
  function openPaymentModal() {
    if (!paymentModal) return;
    paymentModal.classList.remove('hidden');

    // Reset progress section
    if (printProgressSection) printProgressSection.classList.add('hidden');

    pollPaymentStatus();
    if (paymentPollInterval) clearInterval(paymentPollInterval);
    paymentPollInterval = setInterval(pollPaymentStatus, 2000);
  }

  async function pollPaymentStatus() {
    if (!currentSessionId) return;

    try {
      const payRes = await PrintGoAPI.getPaymentStatus(currentSessionId);
      if (payRes && payRes.data) {
        const { required_amount, inserted_amount, remaining_amount, is_paid, status } = payRes.data;

        paymentRequired.textContent = `₱${Number(required_amount).toFixed(2)}`;
        paymentInserted.textContent = `₱${Number(inserted_amount).toFixed(2)}`;
        paymentRemaining.textContent = `₱${Number(remaining_amount).toFixed(2)}`;

        const pct = required_amount > 0 ? Math.min(100, Math.round((inserted_amount / required_amount) * 100)) : 0;
        paymentProgress.style.width = `${pct}%`;

        if (is_paid || status === 'paid') {
          paymentStatusText.textContent = '✓ Payment Complete! Printing your document...';
          paymentStatusText.className = 'text-sm font-bold text-[#1f6f5e]';
          clearInterval(paymentPollInterval);

          // Start watching printing progress
          startJobWatcher();
        } else if (inserted_amount > 0) {
          paymentStatusText.textContent = `Partial payment received (₱${inserted_amount}). Please insert remaining ₱${remaining_amount}.`;
          paymentStatusText.className = 'text-sm font-semibold text-[#8a5a00]';
        } else {
          paymentStatusText.textContent = 'Waiting for coin insertion...';
          paymentStatusText.className = 'text-sm text-[#627d98]';
        }
      }
    } catch (err) {
      console.error('Error polling payment status:', err);
    }
  }

  // Simulated Coin Insertion Buttons
  document.querySelectorAll('.btn-insert-coin').forEach(btn => {
    btn.addEventListener('click', async () => {
      const amt = btn.dataset.amount;
      if (!currentSessionId || !amt) return;

      btn.disabled = true;
      try {
        await PrintGoAPI.insertCoin(currentSessionId, Number(amt));
        await pollPaymentStatus();
      } catch (e) {
        alert('Failed to register coin: ' + (e.message || 'Error'));
      } finally {
        btn.disabled = false;
      }
    });
  });

  // Watch Print Job Completion
  function startJobWatcher() {
    if (!currentJobRecord) return;

    if (printProgressSection) printProgressSection.classList.remove('hidden');

    if (jobPollInterval) clearInterval(jobPollInterval);
    jobPollInterval = setInterval(async () => {
      try {
        const res = await PrintGoAPI.getJobStatus(currentJobRecord.id);
        if (res && res.data) {
          const job = res.data;
          const progress = job.progress || 0;
          if (printProgressBar) printProgressBar.style.width = `${progress}%`;
          if (printProgressLabel) printProgressLabel.textContent = `Printing: ${job.pages_completed || 0} of ${job.total_pages || job.page_count} pages printed (${progress}%)`;

          if (job.status === 'completed') {
            clearInterval(jobPollInterval);
            if (printProgressLabel) {
              printProgressLabel.textContent = '✓ Printing Completed! Please collect your document from the tray.';
              printProgressLabel.className = 'text-sm font-bold text-[#1f6f5e] mt-2';
            }
          }
        }
      } catch (e) {
        console.error('Error polling job status:', e);
      }
    }, 2000);
  }

  // Close payment modal
  const closePaymentModalBtn = document.getElementById('btn-close-payment-modal');
  if (closePaymentModalBtn) {
    closePaymentModalBtn.addEventListener('click', () => {
      if (paymentModal) paymentModal.classList.add('hidden');
      if (paymentPollInterval) clearInterval(paymentPollInterval);
      if (jobPollInterval) clearInterval(jobPollInterval);
    });
  }

  // Initial pricing preview
  updatePrice();
});
