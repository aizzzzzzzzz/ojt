<!-- Verified Attendance Modal -->
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

<!-- Full Screen IDE Modal -->
<div id="fullscreenIDE" class="fullscreen-ide" style="display: none;">
    <div class="ide-header">
        <h4 id="ideProjectName">Project IDE</h4>
        <button onclick="closeFullScreenIDE()" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer;">✕</button>
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
// ========== GLOBAL FUNCTIONS ==========

// Tab switching function
function switchTab(tabName, button) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => content.classList.remove('active'));

    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(btn => btn.classList.remove('active'));

    // Show selected tab content
    document.getElementById(tabName + '-tab').classList.add('active');

    // Add active class to clicked button
    button.classList.add('active');

    // Hide/show welcome header and summary based on tab
    const welcomeHeader = document.getElementById('welcomeHeader');
    const summarySection = document.getElementById('summarySection');

    if (tabName === 'projects') {
        if (welcomeHeader) welcomeHeader.style.display = 'none';
        if (summarySection) summarySection.style.display = 'none';

        document.getElementById('submissionSection').style.display = 'none';
        document.getElementById('projects-section').style.display = 'block';

        // Initialize CodeMirror if not already done
        if (!window.codeEditor) {
            // Wait a bit for the tab to be visible
            setTimeout(initCodeEditor, 100);
        }
    } else {
        if (welcomeHeader) welcomeHeader.style.display = 'flex';
        if (summarySection) summarySection.style.display = 'block';
    }
}

// Project selection for submission
function selectProjectForSubmission(projectId, projectName) {
    // Show submission section
    document.getElementById('projects-section').style.display = 'none';
    document.getElementById('submissionSection').style.display = 'block';

    // Set project info
    document.getElementById('selectedProjectName').textContent = projectName;
    document.getElementById('projectId').value = projectId;

    // Reset form and preview
    document.getElementById('submissionForm').reset();
    document.getElementById('editorPreview').srcdoc = '';
    document.getElementById('submissionFile').value = '';

    // Switch to code tab by default
    switchSubmissionTab('code');

    // Initialize CodeMirror and attach events after display is set
    setTimeout(() => {
        // Reset editor content to default
        if (window.codeEditor && typeof window.codeEditor.setValue === 'function') {
            window.codeEditor.setValue(`<?php echo htmlspecialchars($defaultCode); ?>`);
        } else {
            // Reinitialize editor if not properly set up
            initCodeEditor();
        }

        // Attach run button event
        const runBtn = document.getElementById('runCodeBtn');
        if (runBtn) {
            // Remove existing event listeners
            const newRunBtn = runBtn.cloneNode(true);
            runBtn.parentNode.replaceChild(newRunBtn, runBtn);
            newRunBtn.addEventListener('click', runCodePreview);
        }
    }, 100);
}

function cancelSubmission() {
    // Hide submission section, show project list
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

        // Initialize CodeMirror if not already done
        if (!window.codeEditor) {
            setTimeout(initCodeEditor, 50);
        }
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

// Run code for preview (safe iframe display)
function runCodePreview(event) {
    event.preventDefault();
    if (window.codeEditor && typeof window.codeEditor.getValue === 'function') {
        const code = window.codeEditor.getValue();
        const iframe = document.getElementById('editorPreview');
        // Display code safely in iframe without execution
        iframe.srcdoc = code;
    } else {
        console.error('Code editor not initialized yet.');
    }
}

// Initialize CodeMirror editor
function initCodeEditor() {
    const textarea = document.getElementById('codeEditor');
    if (!textarea) {
        console.error('codeEditor textarea not found!');
        return;
    }

    // Clean up any existing editor
    if (window.codeEditor && window.codeEditor.toTextArea) {
        try {
            window.codeEditor.toTextArea();
        } catch (e) {
            console.log('Error cleaning up editor:', e);
        }
    }

    // Create new editor instance
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

    // Refresh the editor to ensure proper rendering
    setTimeout(() => {
        if (window.codeEditor) {
            window.codeEditor.refresh();
            window.codeEditor.focus();
        }
    }, 150);

    return window.codeEditor;
}

// Full Screen IDE Functions (optional)
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

// ========== DOM CONTENT LOADED ==========
document.addEventListener('DOMContentLoaded', function() {
    // Handle form submission validation
    const form = document.getElementById('submissionForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const submissionType = document.getElementById('submissionType').value;

            if (submissionType === 'code') {
                // Save editor content to textarea
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

    // Initialize export form date ranges to current month
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
    // Check if modal has already been shown today using localStorage
    const todayDate = '<?= date('Y-m-d') ?>';
    const modalShownKey = 'attendance_modal_shown_' + todayDate;

    if (!localStorage.getItem(modalShownKey)) {
        var myModal = new bootstrap.Modal(document.getElementById('verifiedModal'), {});
        myModal.show();
        // Mark modal as shown for today
        localStorage.setItem(modalShownKey, 'true');
    }
    <?php endif; ?>
});
</script>

</body>
</html>
