<div class="modal fade" id="verifiedModal" tabindex="-1" aria-labelledby="verifiedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verifiedModalLabel">Attendance Verified</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Your attendance was verified today.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<div id="fullscreenIDE" class="fullscreen-ide" style="display: none;">
    <div class="ide-header">
        <h4 id="ideProjectName">Project IDE</h4>
        <button onclick="closeFullScreenIDE()" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;padding:0;">✕</button>
    </div>
    <div class="ide-content">
        <div class="code-panel">
            <div class="panel-header">Code Editor</div>
            <div class="panel-content">
                <textarea id="fullscreenEditor"></textarea>
            </div>
        </div>
        <div class="output-panel">
            <div class="panel-header">Output Preview</div>
            <div class="panel-content">
                <iframe id="fullscreenPreview" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
        </div>
    </div>
    <div class="ide-controls">
        <button onclick="runCode()" class="btn btn-primary">▶️ Run Code</button>
        <button onclick="generatePDF()" class="btn btn-success">📄 Generate PDF & Submit</button>
        <button onclick="closeFullScreenIDE()" class="btn btn-secondary">❌ Close</button>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

function switchTab(tabName, button) {
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => content.classList.remove('active'));

    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(btn => btn.classList.remove('active'));

    document.getElementById(tabName + '-tab').classList.add('active');

    button.classList.add('active');

    const welcomeHeader = document.getElementById('welcomeHeader');
    const summarySection = document.getElementById('summarySection');

    if (tabName === 'projects') {
        if (welcomeHeader) welcomeHeader.style.display = 'none';
        if (summarySection) summarySection.style.display = 'none';

        document.getElementById('submissionSection').style.display = 'none';
        document.getElementById('projects-section').style.display = 'block';

        if (!window.codeEditor) {
            setTimeout(initCodeEditor, 100);
        }
    } else {
        if (welcomeHeader) welcomeHeader.style.display = 'flex';
        if (summarySection) summarySection.style.display = 'block';
    }
}

function selectProjectForSubmission(projectId, projectName) {
    document.getElementById('projects-section').style.display = 'none';
    document.getElementById('submissionSection').style.display = 'block';

    document.getElementById('selectedProjectName').textContent = projectName;
    document.getElementById('projectId').value = projectId;

    document.getElementById('submissionForm').reset();
    document.getElementById('editorPreview').srcdoc = '';
    const pp = document.getElementById('previewPanel'); if (pp) pp.style.display = 'none';
    document.getElementById('submissionFile').value = '';

    switchSubmissionTab('code');

    setTimeout(() => {
        if (window.codeEditor && typeof window.codeEditor.setValue === 'function') {
            window.codeEditor.setValue(`<?php echo htmlspecialchars($defaultCode); ?>`);
        } else {
            initCodeEditor();
        }

    }, 100);
}

function cancelSubmission() {
    document.getElementById('submissionSection').style.display = 'none';
    document.getElementById('projects-section').style.display = 'block';
}

function switchSubmissionTab(tabType) {
    const submissionType = document.getElementById('submissionType');

    if (tabType === 'code') {
        submissionType.value = 'code';
        document.getElementById('codeTab').style.display = 'flex';
        document.getElementById('fileTab').style.display = 'none';
        document.getElementById('codeTabBtn').style.borderBottom = '3px solid #28a745';
        document.getElementById('codeTabBtn').style.color = '#28a745';
        document.getElementById('fileTabBtn').style.borderBottom = 'none';
        document.getElementById('fileTabBtn').style.color = '#999';

        setTimeout(function() {
            if (!window.codeEditor) {
                initCodeEditor();
            } else {
                window.codeEditor.refresh();
                window.codeEditor.focus();
            }
        }, 100);
    } else {
        submissionType.value = 'file';
        document.getElementById('codeTab').style.display = 'none';
        document.getElementById('fileTab').style.display = 'block';
        document.getElementById('codeTabBtn').style.borderBottom = 'none';
        document.getElementById('codeTabBtn').style.color = '#999';
        document.getElementById('fileTabBtn').style.borderBottom = '3px solid #28a745';
        document.getElementById('fileTabBtn').style.color = '#28a745';
    }
}

function runCodePreview(event) {
    if (event) event.preventDefault();
    let code = '';
    if (window.codeEditor && typeof window.codeEditor.getValue === 'function') {
        window.codeEditor.save();
        code = window.codeEditor.getValue();
    }
    if (!code) {
        const ta = document.getElementById('codeEditor');
        if (ta) code = ta.value;
    }
    if (!code) {
        const ta = document.querySelector('textarea[name="code_content"]');
        if (ta) code = ta.value;
    }
    if (!code) {
        alert('No code to preview. Please write some code first.');
        return;
    }
    openFullscreenPreview(code);
}

function openFullscreenPreview(code) {
    let overlay = document.getElementById('previewOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'previewOverlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:#fff;display:flex;flex-direction:column;font-family:DM Sans,Segoe UI,sans-serif;';

        const header = document.createElement('div');
        header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:12px 20px;background:#111827;color:#fff;border-bottom:1px solid #374151;flex-shrink:0;';
        header.innerHTML = `
            <span style="font-size:14px;font-weight:700;letter-spacing:-.2px;">&#9654; Preview Output</span>
            <div style="display:flex;gap:10px;align-items:center;">
                <button onclick="refreshPreview()" style="background:#374151;border:none;color:#d1d5db;padding:6px 14px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;">&#8635; Refresh</button>
                <button onclick="closeFullscreenPreview()" style="background:#dc2626;border:none;color:#fff;padding:6px 14px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;">&#x2715; Close</button>
            </div>`;

        const iframe = document.createElement('iframe');
        iframe.id = 'previewOverlayFrame';
        iframe.style.cssText = 'flex:1;border:none;width:100%;';

        overlay.appendChild(header);
        overlay.appendChild(iframe);
        document.body.appendChild(overlay);
    }

    overlay.style.display = 'flex';
    document.getElementById('previewOverlayFrame').srcdoc = code;
    overlay._lastCode = code;
    document.body.style.overflow = 'hidden';
}

function refreshPreview() {
    const overlay = document.getElementById('previewOverlay');
    if (!overlay) return;
    let code = '';
    if (window.codeEditor && typeof window.codeEditor.getValue === 'function') {
        window.codeEditor.save();
        code = window.codeEditor.getValue();
    }
    if (!code) {
        const ta = document.querySelector('textarea[name="code_content"]');
        if (ta) code = ta.value;
    }
    if (!code) code = overlay._lastCode || '';
    overlay._lastCode = code;
    document.getElementById('previewOverlayFrame').srcdoc = code;
}

function closeFullscreenPreview() {
    const overlay = document.getElementById('previewOverlay');
    if (overlay) overlay.style.display = 'none';
    document.body.style.overflow = '';
}

function closePreview() { closeFullscreenPreview(); }

function initCodeEditor() {
    const textarea = document.getElementById('codeEditor');
    if (!textarea) {
        console.error('codeEditor textarea not found!');
        return;
    }

    if (window.codeEditor && window.codeEditor.toTextArea) {
        try {
            window.codeEditor.toTextArea();
        } catch (e) {
            console.log('Error cleaning up editor:', e);
        }
    }

    window.codeEditor = CodeMirror.fromTextArea(textarea, {
        lineNumbers: true,
        theme: "monokai",
        tabSize: 4,
        lineWrapping: true,
        matchBrackets: true,
        autoCloseBrackets: true,
        mode: "application/x-httpd-php",
        value: `<?php echo htmlspecialchars($defaultCode); ?>`,
        viewportMargin: Infinity,
        indentUnit: 4,
        extraKeys: {
            "Ctrl-Space": "autocomplete"
        }
    });

    setTimeout(() => {
        if (window.codeEditor) {
            window.codeEditor.refresh();
            window.codeEditor.focus();
        }
    }, 150);

    return window.codeEditor;
}

function openFullScreenIDE(projectId, projectName) {
    currentProjectId = projectId;
    document.getElementById('ideProjectName').textContent = 'Project: ' + projectName;
    document.getElementById('fullscreenIDE').style.display = 'flex';

    if (!window.fullscreenEditor) {
        window.fullscreenEditor = CodeMirror.fromTextArea(document.getElementById('fullscreenEditor'), {
            lineNumbers: true,
            theme: 'default',
            tabSize: 4,
            lineWrapping: true,
            matchBrackets: true,
            autoCloseBrackets: true,
            mode: 'htmlmixed',
            value: `<?php echo htmlspecialchars($defaultCode); ?>`
        });
    }
}

function closeFullScreenIDE() {
    document.getElementById('fullscreenIDE').style.display = 'none';
}

function runCode() {
    if (window.fullscreenEditor) {
        const code = window.fullscreenEditor.getValue();
        const iframe = document.getElementById('fullscreenPreview');
        iframe.srcdoc = code;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('submissionForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const submissionType = document.getElementById('submissionType').value;

            if (submissionType === 'code') {
                if (window.codeEditor) {
                    window.codeEditor.save();
                }

                const codeTextarea = document.getElementById('codeEditor');
                if (!codeTextarea || codeTextarea.value.trim() === '') {
                    e.preventDefault();
                    alert('Please write some code before submitting');
                    return false;
                }
            } else {
                const fileInput = document.getElementById('submissionFile');
                if (!fileInput.files.length) {
                    e.preventDefault();
                    alert('Please select a file to submit');
                    return false;
                }
            }
            return true;
        });
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeFullscreenPreview(); });

    const today = new Date().toISOString().split('T')[0];
    const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];

    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');

    if (startDateInput && !startDateInput.value) {
        startDateInput.value = firstDay;
    }
    if (endDateInput && !endDateInput.value) {
        endDateInput.value = today;
    }

    <?php if (!empty($today_row) && $today_row['verified'] == 1): ?>
    const todayDate = '<?= date('Y-m-d') ?>';
    const modalShownKey = 'attendance_modal_shown_' + todayDate;

    if (!localStorage.getItem(modalShownKey)) {
        var myModal = new bootstrap.Modal(document.getElementById('verifiedModal'), {});
        myModal.show();
        localStorage.setItem(modalShownKey, 'true');
    }
    <?php endif; ?>
});
</script>

</body>
</html>