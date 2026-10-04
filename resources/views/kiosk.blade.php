<!doctype html>
<html lang="en" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>PrintGO — Self-Service Printing Kiosk</title>
  <script>
    // Immediately apply saved or default dark theme to prevent FOUC
    (function() {
      var saved = localStorage.getItem('printgo_theme');
      if (saved === 'light') {
        document.documentElement.classList.remove('dark');
      } else {
        document.documentElement.classList.add('dark');
      }
    })();
  </script>
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
  <script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&amp;family=Fraunces:opsz,wght@9..144,600;9..144,700&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/kiosk.css?v={{ time() }}">
</head>
<body data-start-view="{{ $startView ?? '' }}">
  <div class="app-shell">
    <!-- Top Sticky Header Navigation -->
    <header class="sticky top-0 z-40 w-full bg-[#102a43]/95 backdrop-blur-md px-5 py-3.5 sm:px-8 shadow-md border-b border-white/10 transition-all">
      <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4">
        <div id="kiosk-logo" role="button" tabindex="0" class="flex items-center gap-3 cursor-pointer select-none transition-all duration-150 active:scale-95 hover:opacity-95" title="PrintGO">
          <img src="{{ asset('images/logo.jpg') }}" alt="PrintGO Logo" class="h-12 w-12 rounded-full object-cover shadow-md pointer-events-none ring-2 ring-white/20">
          <div class="pointer-events-none">
            <p class="brand-type text-2xl font-bold leading-none text-white">PrintGO</p>
            <p class="mt-1 text-xs tracking-wide text-[#9fb3c8]">High School Self-Service Print Desk</p>
          </div>
        </div>
        <nav aria-label="Workspace views" class="flex items-center gap-3 self-end sm:self-auto">
          <span id="student-kiosk-indicator" class="text-xs font-semibold text-[#9fb3c8] hidden sm:inline-block">Student Kiosk Mode</span>
          <button id="theme-toggle-btn" class="flex h-9 w-9 items-center justify-center rounded-xl border border-[#334e68] bg-[#1a3048] text-[#9fb3c8] hover:text-white hover:bg-[#243b53] transition" type="button" title="Toggle Dark/Light Mode" aria-label="Toggle theme">
            <i id="theme-icon" data-lucide="sun" width="18" height="18"></i>
          </button>
          <button id="admin-tab" class="hidden" type="button" aria-hidden="true"></button>
        </nav>
      </div>
    </header>

    <!-- Global Announcements & Maintenance Banners -->
    <div class="mx-auto max-w-6xl px-5 pt-6 sm:px-8">
      <div id="maintenance-banner" class="hidden mb-4 flex items-center gap-3 rounded-2xl border border-[#b23b27] bg-[#fdf2f2] p-4 text-[#b23b27]">
        <i data-lucide="alert-triangle" width="24" height="24"></i>
        <div>
          <h4 class="font-bold text-sm">System Notice: Maintenance Mode Active</h4>
          <p class="maintenance-msg text-xs mt-0.5">Printing is currently suspended for routine maintenance.</p>
        </div>
      </div>
      <div id="announcements-banner" class="hidden mb-4 rounded-2xl border border-[#dcefe6] bg-[#f4faf7] p-3 text-[#1f6f5e]"></div>
    </div>

    <!-- Main Workspace Container -->
    <main class="mx-auto w-full max-w-6xl px-5 py-6 sm:px-8 lg:py-8">

      <!-- ========================================== -->
      <!-- Student View: File Upload & Print Setup   -->
      <!-- ========================================== -->
      <section id="student-view" class="view-panel">
        <div class="mb-8 max-w-2xl">
          <p class="mb-2 text-xs font-bold uppercase tracking-[.18em] text-[#e56b4f]">Instant Self-Service</p>
          <h1 class="brand-type text-4xl sm:text-5xl font-bold leading-[.98] text-[#102a43]">Print Your School Documents in Seconds</h1>
          <p class="mt-3 text-base text-[#486581] leading-relaxed">
            Upload your homework, projects, or handouts, configure your print options, and insert coins into the kiosk coin slot.
          </p>
        </div>

        <div class="grid items-start gap-7 lg:grid-cols-[1.25fr_.75fr]">
          <!-- Left: Document Upload & Settings Form -->
          <section class="paper-panel rounded-[1.5rem] p-6 sm:p-8">
            <div class="mb-6 flex items-start justify-between gap-4">
              <div>
                <h2 class="brand-type text-2xl font-bold text-[#102a43]">1. Select Document</h2>
                <p class="mt-1 text-sm text-[#627d98]">Supports PDF, Word (DOCX), PowerPoint (PPTX), and Images (PNG, JPG).</p>
              </div>
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#dcefe6] text-[#1f6f5e]">
                <i data-lucide="file-up" width="22" height="22"></i>
              </div>
            </div>

            <form id="print-form" novalidate>
              <div id="file-drop-zone" class="dash-drop relative rounded-2xl px-5 py-8 text-center cursor-pointer">
                <input id="document-file" class="visually-hidden-input" type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.webp,.heic">
                <i data-lucide="upload-cloud" class="mx-auto mb-3 text-[#e56b4f]" width="36" height="36"></i>
                <label for="document-file" class="block cursor-pointer text-base font-semibold text-[#102a43]">
                  Click to browse or drag &amp; drop file here
                </label>
                <p class="mt-1 text-xs text-[#627d98]">Maximum file size: 50MB</p>
                <p id="file-name" class="mt-4 hidden text-sm font-bold text-[#1f6f5e]" aria-live="polite"></p>
              </div>

              <p id="validation-message" class="mt-3 hidden text-sm font-medium text-[#b23b27]" role="alert"></p>

              <fieldset class="mt-8 border-t border-[#ece5d6] pt-6">
                <legend class="brand-type text-2xl font-bold text-[#102a43] mb-4">2. Print Preferences</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                  <div>
                    <label class="mb-2 block text-sm font-semibold text-[#243b53]" for="colour-mode">Color Mode</label>
                    <select id="colour-mode" class="field-control">
                      <option value="bw">Black &amp; White (Standard)</option>
                      <option value="colour">Full Color</option>
                    </select>
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-semibold text-[#243b53]" for="paper-size">Paper Size</label>
                    <select id="paper-size" class="field-control">
                      <option value="a4">A4 (Standard)</option>
                      <option value="letter">Letter (Short)</option>
                      <option value="legal">Legal (Long)</option>
                    </select>
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-semibold text-[#243b53]" for="copies">Number of Copies</label>
                    <input id="copies" class="field-control" type="number" min="1" max="50" value="1">
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-semibold text-[#243b53]" for="page-range">Page Range</label>
                    <input id="page-range" class="field-control" type="text" placeholder="e.g. All or 1-5" value="All">
                  </div>
                </div>

                <p class="mb-2 mt-5 text-sm font-semibold text-[#243b53]">Layout Sides</p>
                <div class="grid gap-3 sm:grid-cols-2">
                  <label class="cursor-pointer">
                    <input class="peer sr-only" type="radio" name="sides" value="single" checked>
                    <span class="block rounded-xl border border-[#c9c0b0] bg-[#fffdf8] px-4 py-3 text-sm font-medium transition peer-checked:border-[#e56b4f] peer-checked:bg-[#fff0eb] text-[#243b53]">
                      Single-sided (1 page per sheet)
                    </span>
                  </label>
                  <label class="cursor-pointer">
                    <input class="peer sr-only" type="radio" name="sides" value="double">
                    <span class="block rounded-xl border border-[#c9c0b0] bg-[#fffdf8] px-4 py-3 text-sm font-medium transition peer-checked:border-[#e56b4f] peer-checked:bg-[#fff0eb] text-[#243b53]">
                      Double-sided / Duplex (Saves paper)
                    </span>
                  </label>
                </div>
              </fieldset>

              <button id="submit-button" class="mt-8 flex w-full items-center justify-center gap-2 rounded-xl bg-[#e56b4f] hover:bg-[#c9553b] text-white px-5 py-4 font-bold text-base shadow-lg shadow-[#e56b4f]/25 transition hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-[#e56b4f]/30 disabled:opacity-60 disabled:hover:translate-y-0" type="submit">
                <i data-lucide="send" width="18" height="18"></i>
                <span id="submit-button-text">Send Print Job</span>
              </button>
            </form>
          </section>

          <!-- Right: Authoritative Summary & Status -->
          <aside class="space-y-5">
            <section class="paper-panel rounded-[1.5rem] p-6 sm:p-7">
              <div class="flex items-center justify-between">
                <h2 class="brand-type text-2xl font-bold text-[#102a43]">Summary</h2>
                <span class="rounded-full bg-[#dcefe6] px-3 py-1 text-xs font-bold text-[#1f6f5e]">Authoritative Pricing</span>
              </div>
              <dl class="mt-5 space-y-3 text-sm">
                <div class="flex justify-between border-b border-[#ece5d6] pb-3">
                  <dt class="text-[#627d98]">Document:</dt>
                  <dd id="summary-file" class="max-w-[55%] truncate text-right font-semibold text-[#102a43]">
                    No document selected
                  </dd>
                </div>
                <div class="flex justify-between border-b border-[#ece5d6] pb-3">
                  <dt class="text-[#627d98]">Specifications:</dt>
                  <dd id="summary-specs" class="text-right font-semibold text-[#102a43]">
                    B&amp;W · A4 · 1 copy
                  </dd>
                </div>
                <div class="flex justify-between items-baseline pt-2">
                  <dt class="font-semibold text-base text-[#102a43]">Total Amount:</dt>
                  <dd id="estimate" class="brand-type text-3xl font-bold text-[#e56b4f]">
                    ₱2.00
                  </dd>
                </div>
              </dl>
              <p class="mt-5 rounded-xl bg-[#faf7f0] p-3 text-xs text-[#627d98] leading-relaxed">
                Prices are calculated server-side based on the current school kiosk matrix. Insert coins after submitting.
              </p>
            </section>

            <section id="student-confirmation" class="hidden rounded-[1.5rem] bg-[#1f6f5e] p-5 text-white shadow-lg">
              <div class="flex gap-3 items-start">
                <i data-lucide="check-circle-2" class="mt-0.5 text-white" width="22" height="22"></i>
                <div>
                  <h3 class="font-bold text-base">Print Job Queued!</h3>
                  <p id="student-confirmation-text" class="mt-1 text-sm text-[#e1f5ec]" aria-live="polite"></p>
                </div>
              </div>
            </section>
          </aside>
        </div>
      </section>

      <!-- ========================================== -->
      <!-- Admin View: Management & Monitoring        -->
      <!-- ========================================== -->
      <section id="admin-view" class="view-panel hidden">
        <!-- Admin Gate / Login Card -->
        <section id="admin-gate" class="paper-panel mb-8 rounded-[1.5rem] bg-[#102a43] p-8 text-center sm:p-11 text-white">
          <div class="mx-auto max-w-md">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#e56b4f] text-white shadow-lg">
              <i data-lucide="shield-check" width="28" height="28"></i>
            </div>
            <h2 class="brand-type text-3xl font-bold">Admin Portal Login</h2>
            <p class="mt-2 text-sm text-[#9fb3c8] leading-relaxed">
              Authenticate to manage transactions, oversee real-time printer status, toggle maintenance mode, and adjust pricing.
            </p>
            
            <form id="admin-login-form" class="mt-6 space-y-4 text-left">
              <div>
                <label class="block text-xs font-semibold text-[#9fb3c8] mb-1">Email or Username</label>
                <input id="admin-login" class="w-full rounded-xl bg-[#243b53] border border-[#334e68] text-white px-4 py-3 text-sm focus:outline-none focus:border-[#e56b4f]" type="text" placeholder="Enter your Email or Username" required autocomplete="username">
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#9fb3c8] mb-1">Password</label>
                <input id="admin-password" class="w-full rounded-xl bg-[#243b53] border border-[#334e68] text-white px-4 py-3 text-sm focus:outline-none focus:border-[#e56b4f]" type="password" placeholder="Enter password" required autocomplete="current-password">
              </div>
              <p id="admin-login-error" class="hidden text-xs text-[#ff8080] font-semibold"></p>
              <div class="flex gap-3 pt-2">
                <button id="admin-cancel-btn" class="flex-1 inline-flex items-center justify-center rounded-xl border border-[#334e68] hover:bg-[#243b53] text-[#9fb3c8] hover:text-white px-4 py-3 text-sm font-semibold transition" type="button">
                  Back to Kiosk
                </button>
                <button id="admin-enter-button" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-[#e56b4f] hover:bg-[#c9553b] text-white px-5 py-3 font-bold shadow-lg transition hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-[#e56b4f]/30" type="submit">
                  <span>Sign In</span>
                  <i data-lucide="arrow-right" width="18" height="18"></i>
                </button>
              </div>
            </form>
          </div>
        </section>

        <!-- Admin Dashboard Content (Visible after login) -->
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
          <div class="max-w-2xl">
            <p class="mb-2 text-xs font-bold uppercase tracking-[.18em] text-[#e56b4f]">Kiosk Telemetry &amp; Controls</p>
            <h1 class="brand-type text-4xl sm:text-5xl font-bold leading-[.98] text-[#102a43]">Printing Desk Overview</h1>
            <p class="mt-2 text-sm text-[#627d98]">Real-time operational dashboard connected to your Laravel backend.</p>
          </div>
          <div class="flex items-center gap-3">
            <div class="canva-tag flex items-center gap-2 rounded-full bg-[#dcefe6] px-4 py-2 text-xs font-bold text-[#1f6f5e]">
              <span class="pulse-dot h-2 w-2 rounded-full bg-[#1f6f5e]"></span>
              <span>Backend Connected</span>
            </div>
            <button id="admin-logout-btn" class="rounded-xl border border-[#b23b27] bg-[#fdf2f2] text-[#b23b27] hover:bg-[#fbd9d5] px-3.5 py-2 text-xs font-bold transition" type="button">
              Sign Out
            </button>
          </div>
        </div>

        <!-- Metric Cards -->
        <section aria-label="Operational overview" class="grid gap-4 sm:grid-cols-3">
          <article class="metric-card paper-panel rounded-2xl p-5">
            <div class="flex items-center justify-between">
              <p class="text-xs font-semibold uppercase tracking-wider text-[#627d98]">Active Queue</p>
              <i data-lucide="layers-3" class="text-[#e56b4f]" width="20" height="20"></i>
            </div>
            <p id="queue-metric" class="brand-type mt-3 text-4xl font-bold text-[#102a43]">0</p>
            <p class="mt-1 text-xs text-[#627d98]">Pending &amp; printing jobs</p>
          </article>
          <article class="metric-card paper-panel rounded-2xl p-5">
            <div class="flex items-center justify-between">
              <p class="text-xs font-semibold uppercase tracking-wider text-[#627d98]">Printer Readiness</p>
              <i data-lucide="printer-check" class="text-[#1f6f5e]" width="20" height="20"></i>
            </div>
            <p id="printer-metric" class="brand-type mt-3 text-4xl font-bold text-[#1f6f5e]">1/1</p>
            <p class="mt-1 text-xs text-[#627d98]">Kiosk printer online</p>
          </article>
          <article class="metric-card paper-panel rounded-2xl p-5">
            <div class="flex items-center justify-between">
              <p class="text-xs font-semibold uppercase tracking-wider text-[#627d98]">Daily Earnings</p>
              <i data-lucide="circle-dollar-sign" class="text-[#c9553b]" width="20" height="20"></i>
            </div>
            <p id="revenue-metric" class="brand-type mt-3 text-4xl font-bold text-[#c9553b]">₱0.00</p>
            <p class="mt-1 text-xs text-[#627d98]">Settled kiosk coin transactions</p>
          </article>
        </section>

        <!-- Main Admin Panels -->
        <div class="mt-7 grid items-start gap-7 lg:grid-cols-[1.3fr_.7fr]">
          <!-- Active Jobs & Transactions Table -->
          <section class="paper-panel overflow-hidden rounded-[1.5rem]">
            <div class="flex items-center justify-between border-b border-[#ece5d6] px-6 py-5">
              <div>
                <h2 class="brand-type text-2xl font-bold text-[#102a43]">Live Jobs &amp; Recent Transactions</h2>
                <p class="mt-0.5 text-xs text-[#627d98]">Monitors physical print queue and advances status.</p>
              </div>
              <span id="active-count" class="rounded-full bg-[#dcefe6] px-3 py-1 text-xs font-bold text-[#1f6f5e]">0 active</span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full min-w-[600px] text-left text-sm">
                <thead class="bg-[#faf7f0] text-xs text-[#627d98] border-b border-[#ece5d6]">
                  <tr>
                    <th class="px-5 py-3 font-bold">Reference</th>
                    <th class="px-3 py-3 font-bold">Document</th>
                    <th class="px-3 py-3 font-bold">Setup</th>
                    <th class="px-3 py-3 font-bold">Status</th>
                    <th class="px-3 py-3 font-bold">Action</th>
                  </tr>
                </thead>
                <tbody id="admin-job-list">
                  <tr>
                    <td colspan="5" class="px-5 py-6 text-center text-[#627d98]">Loading queue records...</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <!-- Right Sidebar: Printer Status, Maintenance & Pricing -->
          <aside class="space-y-5">
            <!-- Hardware / Maintenance Controls -->
            <section class="paper-panel rounded-[1.5rem] p-5">
              <h2 class="brand-type text-xl font-bold text-[#102a43]">Printer Controls</h2>
              <div class="mt-4 rounded-xl bg-[#faf7f0] p-4 border border-[#ece5d6]">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <p class="font-bold text-sm text-[#102a43]">Main Kiosk Printer</p>
                    <p class="mt-0.5 text-xs text-[#627d98]">Epson EcoTank L3210 (USB)</p>
                  </div>
                  <span id="printer-one-status" class="canva-tag rounded-full bg-[#1f6f5e] text-white px-2.5 py-1 text-xs font-bold">
                    Online &amp; Ready
                  </span>
                </div>
                <button id="printer-maintenance" class="mt-4 w-full rounded-xl bg-[#b23b27] hover:bg-[#8f2716] px-3 py-2.5 text-xs font-bold text-white transition focus:outline-none focus:ring-2 focus:ring-[#e56b4f]" type="button">
                  Toggle Maintenance Mode
                </button>
              </div>
            </section>

            <!-- Pricing Configuration -->
            <section class="paper-panel rounded-[1.5rem] p-5">
              <h2 class="brand-type text-xl font-bold text-[#102a43]">Pricing Configuration</h2>
              <p class="mt-0.5 text-xs text-[#627d98]">Server-side rates (PHP ₱) updated in database.</p>
              <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                  <label class="mb-1 block text-xs font-semibold text-[#243b53]" for="admin-bw-price">B&amp;W Page (₱)</label>
                  <input id="admin-bw-price" class="field-control text-sm" type="number" min="0" step="0.50" value="2.00">
                </div>
                <div>
                  <label class="mb-1 block text-xs font-semibold text-[#243b53]" for="admin-colour-price">Color Page (₱)</label>
                  <input id="admin-colour-price" class="field-control text-sm" type="number" min="0" step="0.50" value="5.00">
                </div>
              </div>
              <button id="btn-save-pricing" class="mt-4 w-full rounded-xl bg-[#102a43] hover:bg-[#243b53] px-3 py-2.5 text-xs font-bold text-white transition" type="button">
                Update Pricing
              </button>
            </section>
          </aside>
        </div>
      </section>
    </main>

    <!-- Footer -->
    <footer class="w-full px-5 py-8 text-center sm:px-8 border-t border-[#ece5d6] text-[#627d98]">
      <p class="text-xs">© 2026 PrintGO Self-Service Printing System. All rights reserved.</p>
    </footer>
  </div>

  <!-- Row Template for Admin Job Table -->
  <template id="admin-job-template">
    <tr class="job-row border-t border-[#ece5d6] hover:bg-gray-50/50 transition">
      <td class="job-id px-5 py-4 font-bold text-[#243b53]"></td>
      <td class="job-document px-3 py-4 font-semibold text-[#102a43]"></td>
      <td class="job-setup px-3 py-4 text-xs text-[#627d98]"></td>
      <td class="px-3 py-4"><span class="job-status rounded-full px-2.5 py-1 text-xs font-bold"></span></td>
      <td class="px-3 py-4">
        <button class="job-action rounded-lg bg-[#102a43] hover:bg-[#243b53] px-3 py-1.5 text-xs font-bold text-white transition focus:outline-none focus:ring-2 focus:ring-[#e56b4f]" type="button">
          Action
        </button>
      </td>
    </tr>
  </template>

  <!-- ========================================== -->
  <!-- Payment & Coin Insertion Modal             -->
  <!-- ========================================== -->
  <div id="payment-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-backdrop hidden">
    <div class="w-full max-w-md rounded-[2rem] bg-white p-6 sm:p-8 shadow-2xl paper-panel border border-[#ece5d6]">
      <div class="flex items-center justify-between border-b border-[#ece5d6] pb-4">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#fff0eb] text-[#e56b4f]">
            <i data-lucide="coins" width="22" height="22"></i>
          </div>
          <div>
            <h3 class="brand-type text-2xl font-bold text-[#102a43]">Kiosk Coin Slot</h3>
            <p class="text-xs text-[#627d98]">Insert physical coins to proceed</p>
          </div>
        </div>
        <button id="btn-close-payment-modal" class="text-[#627d98] hover:text-[#102a43]">
          <i data-lucide="x" width="20" height="20"></i>
        </button>
      </div>

      <div class="my-6 space-y-4">
        <div class="grid grid-cols-3 gap-3 text-center">
          <div class="rounded-xl bg-[#faf7f0] p-3 border border-[#ece5d6]">
            <p class="text-xs text-[#627d98]">Required</p>
            <p id="payment-required" class="brand-type text-xl font-bold text-[#102a43] mt-1">₱0.00</p>
          </div>
          <div class="rounded-xl bg-[#faf7f0] p-3 border border-[#ece5d6]">
            <p class="text-xs text-[#627d98]">Inserted</p>
            <p id="payment-inserted" class="brand-type text-xl font-bold text-[#1f6f5e] mt-1">₱0.00</p>
          </div>
          <div class="rounded-xl bg-[#faf7f0] p-3 border border-[#ece5d6]">
            <p class="text-xs text-[#627d98]">Remaining</p>
            <p id="payment-remaining" class="brand-type text-xl font-bold text-[#e56b4f] mt-1">₱0.00</p>
          </div>
        </div>

        <!-- Progress bar for payment completion -->
        <div class="w-full bg-[#ece5d6] rounded-full h-3 overflow-hidden">
          <div id="payment-progress-bar" class="bg-[#1f6f5e] h-full transition-all duration-300" style="width: 0%"></div>
        </div>
        <p id="payment-status-text" class="text-xs text-center text-[#627d98] font-medium">
          Waiting for physical coin insertion...
        </p>

        <!-- Simulated Coin Inserter for Kiosk Testing -->
        <div class="border-t border-[#ece5d6] pt-4">
          <p class="text-xs font-semibold text-[#243b53] mb-2 text-center">Simulate Coin Insertion (Demo / Test):</p>
          <div class="grid grid-cols-4 gap-2">
            <button class="btn-insert-coin rounded-xl bg-[#fffdf8] border border-[#c9c0b0] hover:border-[#e56b4f] py-2 text-xs font-bold text-[#102a43] transition" data-amount="1">+₱1</button>
            <button class="btn-insert-coin rounded-xl bg-[#fffdf8] border border-[#c9c0b0] hover:border-[#e56b4f] py-2 text-xs font-bold text-[#102a43] transition" data-amount="5">+₱5</button>
            <button class="btn-insert-coin rounded-xl bg-[#fffdf8] border border-[#c9c0b0] hover:border-[#e56b4f] py-2 text-xs font-bold text-[#102a43] transition" data-amount="10">+₱10</button>
            <button class="btn-insert-coin rounded-xl bg-[#fffdf8] border border-[#c9c0b0] hover:border-[#e56b4f] py-2 text-xs font-bold text-[#102a43] transition" data-amount="20">+₱20</button>
          </div>
        </div>

        <!-- Printing Progress Section -->
        <div id="print-progress-section" class="hidden border-t border-[#ece5d6] pt-4">
          <p class="text-xs font-bold text-[#102a43] mb-1">Print Job Execution Progress:</p>
          <div class="w-full bg-[#ece5d6] rounded-full h-3 overflow-hidden">
            <div id="print-progress-bar" class="bg-[#e56b4f] h-full transition-all duration-300" style="width: 0%"></div>
          </div>
          <p id="print-progress-label" class="text-xs text-[#627d98] mt-1.5 font-medium"></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="/js/api.js?v={{ time() }}"></script>
  <script src="/js/kiosk.js?v={{ time() }}"></script>
  <script src="/js/admin.js?v={{ time() }}"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const themeBtn = document.getElementById('theme-toggle-btn');
      const themeIcon = document.getElementById('theme-icon');

      function syncTheme(isDark) {
        if (isDark) {
          document.documentElement.classList.add('dark');
          localStorage.setItem('printgo_theme', 'dark');
          if (themeIcon) themeIcon.setAttribute('data-lucide', 'sun');
          if (themeBtn) themeBtn.setAttribute('title', 'Switch to Light Mode');
        } else {
          document.documentElement.classList.remove('dark');
          localStorage.setItem('printgo_theme', 'light');
          if (themeIcon) themeIcon.setAttribute('data-lucide', 'moon');
          if (themeBtn) themeBtn.setAttribute('title', 'Switch to Dark Mode');
        }
        if (window.lucide) {
          lucide.createIcons();
        }
      }

      if (themeBtn) {
        themeBtn.addEventListener('click', () => {
          syncTheme(!document.documentElement.classList.contains('dark'));
        });
      }

      // Initial sync based on active class
      syncTheme(document.documentElement.classList.contains('dark'));
    });
  </script>
</body>
</html>
