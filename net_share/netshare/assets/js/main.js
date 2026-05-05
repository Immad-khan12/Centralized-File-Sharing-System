/**
 * ============================================================
 *  main.js — Client-Side Logic
 *  NetShare | Network File Transfer System
 * ============================================================
 *
 *  NETWORKING CONCEPTS DEMONSTRATED HERE:
 *
 *  1. XMLHttpRequest (XHR):
 *     XHR is the JavaScript API for making HTTP requests from
 *     the browser without reloading the page (AJAX).
 *     Each upload is an HTTP POST request over TCP/IP.
 *
 *  2. Upload Progress Events:
 *     XHR exposes onprogress events that fire as TCP segments
 *     are acknowledged by the server. We use this to show a
 *     real-time progress bar — exactly how download managers
 *     and FTP clients show progress.
 *
 *  3. Chunk Simulation:
 *     Large files are sent in TCP segments (packets).
 *     We simulate "chunk awareness" by tracking bytes sent
 *     vs total bytes — mirroring how BitTorrent and FTP work.
 *
 *  4. Transfer Speed:
 *     speed = bytes_sent / time_elapsed
 *     This is how network bandwidth meters work.
 * ============================================================
 */

/* ── DOM Ready ───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {

    // Initialize all interactive components
    initDropzone();
    initUploadForm();
    initDeleteConfirm();
    initTerminalLog();
    initTooltips();
});

/* ============================================================
   DROPZONE — Drag & Drop file selection
   ============================================================ */
function initDropzone() {
    const zone  = document.getElementById('dropzone');
    const input = document.getElementById('fileInput');
    const label = document.getElementById('fileChosen');

    if (!zone || !input) return;

    // Click anywhere on zone to open file picker
    zone.addEventListener('click', () => input.click());

    // Visual feedback on drag over
    zone.addEventListener('dragover', e => {
        e.preventDefault();
        zone.classList.add('dragover');
    });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));

    // Handle dropped files
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            updateFileLabel(input.files[0]);
        }
    });

    // Handle normal file picker selection
    input.addEventListener('change', () => {
        if (input.files[0]) updateFileLabel(input.files[0]);
    });

    function updateFileLabel(file) {
        if (!label) return;
        const size = formatBytes(file.size);
        label.textContent = `▶ ${file.name} (${size})`;
        label.style.display = 'block';
    }
}

/* ============================================================
   AJAX UPLOAD — The core networking feature
   ============================================================
   NETWORKING CONCEPT:
   We use XMLHttpRequest Level 2 to POST file data to the
   server over HTTP. The browser breaks the file into TCP
   segments and sends them to the server's IP:port.
   The server reassembles segments into the complete file.
   We listen to XHR's upload.onprogress event to track
   how many bytes have been sent (acknowledged by server).
   ============================================================ */
function initUploadForm() {
    const form        = document.getElementById('uploadForm');
    const progressWrap= document.getElementById('progressWrap');
    const progressBar = document.getElementById('progressBar');
    const progressPct = document.getElementById('progressPct');
    const progressBytes = document.getElementById('progressBytes');
    const speedDisplay  = document.getElementById('speedDisplay');
    const statusBox     = document.getElementById('uploadStatus');
    const submitBtn     = document.getElementById('submitBtn');
    const terminalLog   = document.getElementById('terminalLog');

    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault(); // Prevent normal form submission (no page reload)

        const fileInput = document.getElementById('fileInput');
        if (!fileInput.files[0]) {
            showStatus('danger', '⚠ No file selected. Please choose a file first.');
            return;
        }

        const file      = fileInput.files[0];
        const formData  = new FormData(form); // Builds multipart/form-data payload

        // ── Client-side validation (first line of defense) ──
        const maxSize   = 10 * 1024 * 1024; // 10 MB
        const allowed   = ['pdf','jpg','jpeg','png','docx'];
        const ext       = file.name.split('.').pop().toLowerCase();

        if (!allowed.includes(ext)) {
            showStatus('danger', `✗ File type .${ext} not allowed. Accepted: ${allowed.join(', ')}`);
            return;
        }
        if (file.size > maxSize) {
            showStatus('danger', `✗ File too large (${formatBytes(file.size)}). Maximum is 10 MB.`);
            return;
        }

        // ── Prepare XHR (HTTP POST over TCP/IP) ─────────────
        const xhr       = new XMLHttpRequest();
        let   startTime = null;  // Track transfer start time
        let   lastLoaded = 0;    // For per-interval speed calc

        // Show progress UI
        progressWrap.style.display = 'block';
        statusBox.innerHTML = '';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Transmitting…';

        appendLog(terminalLog, 'info',
            `INIT  FILE="${file.name}" SIZE="${formatBytes(file.size)}" TYPE="${ext.toUpperCase()}"`);

        /* ── XHR Progress Event ──────────────────────────────
           NETWORKING:
           xhr.upload.onprogress fires each time the browser
           receives a TCP ACK confirming bytes were received
           by the server. `loaded` = bytes ACK'd so far.
           `total` = full content-length of the request body.
        ─────────────────────────────────────────────────── */
        xhr.upload.onprogress = function (event) {
            if (!event.lengthComputable) return;

            if (!startTime) startTime = Date.now();

            const now      = Date.now();
            const elapsed  = (now - startTime) / 1000;   // seconds
            const pct      = Math.round((event.loaded / event.total) * 100);
            const speed    = elapsed > 0 ? (event.loaded / elapsed) : 0;
            const remaining= event.total - event.loaded;
            const eta      = speed > 0 ? (remaining / speed).toFixed(1) : '?';

            // Update progress bar
            progressBar.style.width = pct + '%';
            progressPct.textContent = pct + '%';
            progressBytes.textContent =
                `${formatBytes(event.loaded)} / ${formatBytes(event.total)}`;
            speedDisplay.textContent =
                `${formatSpeed(speed)} · ETA ${eta}s`;

            // Log every 25%
            if (pct % 25 === 0 && event.loaded !== lastLoaded) {
                lastLoaded = event.loaded;
                appendLog(terminalLog, 'info',
                    `SEND  ${pct}% [${formatBytes(event.loaded)}/${formatBytes(event.total)}] @ ${formatSpeed(speed)}`);
            }
        };

        /* ── XHR Complete ──────────────────────────────────── */
        xhr.onload = function () {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Upload File';

            let res;
            try {
                res = JSON.parse(xhr.responseText);
            } catch (err) {
                appendLog(terminalLog, 'err', 'FAIL  Server returned non-JSON response');
                showStatus('danger', '✗ Server error: unexpected response format.');
                progressBar.style.background = 'var(--danger)';
                return;
            }

            if (xhr.status === 200 && res.success) {
                progressBar.style.width = '100%';
                showStatus('success',
                    `✓ Upload complete! <strong>${escHtml(res.file_name)}</strong> &mdash; ` +
                    `${escHtml(res.file_size)} at ${escHtml(res.speed)}`);
                appendLog(terminalLog, 'ok',
                    `OK    FILE="${res.file_name}" SIZE="${res.file_size}" SPEED="${res.speed}" STATUS=SUCCESS`);
                // Reload file list after short delay
                setTimeout(() => location.reload(), 2000);
            } else {
                progressBar.style.background = 'linear-gradient(90deg, #ff4757, #ff6b81)';
                showStatus('danger', '✗ ' + (res.message || 'Upload failed.'));
                appendLog(terminalLog, 'err', `FAIL  ${res.message || 'Unknown error'} STATUS=FAILED`);
            }
        };

        /* ── XHR Network Error ─────────────────────────────
           NETWORKING CONCEPT:
           onerror fires when the TCP connection is broken
           before the transfer completes — simulating what
           happens when a network cable is unplugged or Wi-Fi
           drops during a file transfer. Real FTP and HTTP
           clients detect the same condition via TCP RST or
           timeout signals.
        ─────────────────────────────────────────────────── */
        xhr.onerror = function () {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Upload File';
            progressBar.style.background = 'linear-gradient(90deg, #ff4757, #ff6b81)';
            showStatus('danger', '✗ Network error: connection to server was lost. Check your LAN connection.');
            appendLog(terminalLog, 'err', 'FAIL  TCP connection dropped — transfer incomplete STATUS=FAILED');
        };

        xhr.ontimeout = function () {
            showStatus('danger', '✗ Request timed out. Server may be unreachable on this network.');
            appendLog(terminalLog, 'err', 'FAIL  Request timeout STATUS=FAILED');
        };

        xhr.timeout = 120000; // 2 minute timeout

        // ── Open & Send HTTP POST request ────────────────────
        xhr.open('POST', '/netshare/ajax/upload.php', true);
        // Note: Do NOT set Content-Type — browser sets it automatically
        // with the correct multipart/form-data boundary string

        appendLog(terminalLog, 'info',
            `POST  /ajax/upload.php HTTP/1.1 HOST=${window.location.host}`);

        xhr.send(formData); // 🚀 Send data over the network!
    });
}

/* ============================================================
   DELETE CONFIRMATION
   ============================================================ */
function initDeleteConfirm() {
    document.querySelectorAll('[data-delete-id]').forEach(btn => {
        btn.addEventListener('click', function () {
            const id   = this.dataset.deleteId;
            const name = this.dataset.deleteName;
            if (confirm(`Delete "${name}" from server?\nThis cannot be undone.`)) {
                window.location.href = `/netshare/ajax/delete.php?id=${id}`;
            }
        });
    });
}

/* ============================================================
   TERMINAL LOG — live event output
   ============================================================ */
function initTerminalLog() {
    const terminal = document.getElementById('terminalLog');
    if (!terminal) return;
    // Show initial ready message
    appendLog(terminal, 'info', 'SYS   NetShare file transfer system ready');
    appendLog(terminal, 'info', `SYS   Client IP visible to server via REMOTE_ADDR`);
    appendLog(terminal, 'ok',   'SYS   Awaiting file selection…');
}

/* ── Helper: append a log line to terminal ───────────────── */
function appendLog(terminal, type, msg) {
    if (!terminal) return;
    const time = new Date().toTimeString().slice(0, 8);
    const line = document.createElement('span');
    line.className = `log-line log-${type}`;
    line.innerHTML = `<span class="log-time">[${time}]</span> ${escHtml(msg)}\n`;
    terminal.appendChild(line);
    terminal.scrollTop = terminal.scrollHeight;
}

/* ============================================================
   STATUS BOX helper
   ============================================================ */
function showStatus(type, html) {
    const box = document.getElementById('uploadStatus');
    if (!box) return;
    box.innerHTML = `<div class="ns-alert ns-alert-${type}">${html}</div>`;
}

/* ── Bootstrap tooltips init ──────────────────────────────── */
function initTooltips() {
    if (typeof bootstrap !== 'undefined') {
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
            .forEach(el => new bootstrap.Tooltip(el));
    }
}

/* ── Format bytes to human-readable string ───────────────── */
function formatBytes(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024)    return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
}

/* ── Format bytes/sec to speed string ───────────────────── */
function formatSpeed(bps) {
    if (bps >= 1048576) return (bps / 1048576).toFixed(2) + ' MB/s';
    if (bps >= 1024)    return (bps / 1024).toFixed(1) + ' KB/s';
    return Math.round(bps) + ' B/s';
}

/* ── Escape HTML to prevent XSS ─────────────────────────── */
function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
