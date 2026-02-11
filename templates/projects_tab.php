<div id="projects-tab" class="tab-content">
    <div class="projects-section" id="projects-section">
    <h3>📝 OJT Projects</h3>
    <?php if (!empty($submitError)): ?>
        <div class="error-msg"><?= htmlspecialchars($submitError) ?></div>
    <?php endif; ?>

    <div id="projectsGrid" style="display:block;">
        <?php if (!empty($projects)): ?>
            <div class="projects-grid">
                <?php foreach ($projects as $project): ?>
                    <?php
                    $is_approved = false;
                    foreach ($submissions as $sub) {
                        if ($sub['project_id'] == $project['project_id'] && $sub['status'] == 'Approved') {
                            $is_approved = true;
                            break;
                        }
                    }
                    $is_disabled = $is_approved || strtolower($project['status']) == 'completed';
                    ?>
                    <div class="project-card <?php if ($is_disabled) echo 'disabled'; ?>" <?php if (!$is_disabled) echo 'onclick="selectProjectForSubmission(' . $project['project_id'] . ', \'' . htmlspecialchars($project['project_name']) . '\')"'; ?>>
                        <h5><?= htmlspecialchars($project['project_name']) ?></h5>
                        <p><?= htmlspecialchars(substr($project['description'], 0, 100)) ?>...</p>
                        <div style="font-size: 12px; color: #999; margin-top: 10px;">
                            <div>📅 Due: <?= date('M d, Y', strtotime($project['due_date'])) ?></div>
                            <div>Status: <span style="color: #28a745; font-weight: bold;"><?= ucfirst($project['status']) ?></span></div>
                            <?php if ($is_approved): ?>
                                <div style="color: #28a745; font-weight: bold;">✅ Approved</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: #999;">No projects available yet.</p>
        <?php endif; ?>
    </div>
    </div>

    <div class="code-editor-section" id="submissionSection" style="display:none;">
        <h4>📤 Submit for: <span id="selectedProjectName"></span></h4>
        <div style="margin-bottom: 15px;">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cancelSubmission()">← Back to Projects</button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="submissionForm">
            <input type="hidden" name="project_id" id="projectId" value="">
            <input type="hidden" name="submission_type" id="submissionType" value="code">

            <div style="margin-bottom: 20px; display: flex; gap: 10px; border-bottom: 2px solid #e0e0e0; padding-bottom: 15px;">
                <button type="button" class="btn btn-sm" id="codeTabBtn" style="border: none; border-bottom: 3px solid #28a745; padding: 8px 15px; background: none; color: #28a745; font-weight: 600;" onclick="switchSubmissionTab('code')">
                    ✏️ Write Code
                </button>
                <button type="button" class="btn btn-sm" id="fileTabBtn" style="border: none; padding: 8px 15px; background: none; color: #999; font-weight: 600;" onclick="switchSubmissionTab('file')">
                    📎 Upload File
                </button>
            </div>

            <small style="color: #666; display: block; margin-bottom: 10px;">💡 Supports: PHP, HTML, CSS, Java, JavaScript, and more</small>
            <div id="codeTab" style="display: none;">
                <div class="editor-half">
                    <h6>Code Editor:</h6>
                    <div id="codeEditorContainer">
                        <textarea id="codeEditor" name="code_content"><?php echo htmlspecialchars($defaultCode); ?></textarea>
                    </div>
                </div>

                <div class="preview-half">
                    <div class="preview-controls">
                        <h6>Preview Output:</h6>
                        <button type="button" id="runCodeBtn" class="btn btn-primary btn-sm">▶️ Run Code</button>
                    </div>
                    <iframe
                        id="editorPreview"
                        style="width:100%; height:100%; border:1px solid #ddd; border-radius:6px;"
                    ></iframe>
                </div>
            </div>

            <div id="fileTab" style="display: none;">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">📎 Upload File:</label>
                    <input type="file" name="submission_file" id="submissionFile" class="form-control" accept=".pdf,.doc,.docx,.txt,.zip,.rar,.php,.html,.css,.java,.js">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">📝 Remarks (Optional):</label>
                <textarea name="remarks" class="form-control" rows="3" placeholder="Add any notes or comments about your submission..."></textarea>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="submit_file" class="btn btn-success">📤 Submit Project</button>
                <button type="button" class="btn btn-secondary" onclick="cancelSubmission()">Cancel</button>
            </div>
        </form>
    </div>

    <?php if (!empty($submissions)): ?>
        <div class="submissions-list" style="margin-top: 30px;">
            <h5>📋 Your Submissions</h5>
            <?php foreach ($submissions as $sub): ?>
                <div class="submission-card">
                    <h6><?= htmlspecialchars($sub['project_name']) ?></h6>
                    <div class="submission-meta">
                        <div>📅 Submitted: <strong><?= date('M d, Y H:i', strtotime($sub['submission_date'])) ?></strong></div>
                        <div>Status: <strong style="color: <?= $sub['status'] == 'Approved' ? '#28a745' : ($sub['status'] == 'Rejected' ? '#dc3545' : '#ffc107') ?>;"><?= ucfirst($sub['status']) ?></strong></div>
                        <?php if (!empty($sub['remarks'])): ?>
                            <div>Grade: <?= htmlspecialchars($sub['remarks']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($sub['graded_at'])): ?>
                            <div>✅ Graded on: <?= date('M d, Y', strtotime($sub['graded_at'])) ?></div>
                        <?php endif; ?>
                        <div style="margin-top: 10px;">
                            <a href="view_output.php?file=<?= urlencode($sub['file_path']) ?>" class="btn btn-sm btn-outline-primary" target="_blank">👁️ View Output</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
