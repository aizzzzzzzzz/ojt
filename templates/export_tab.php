<div id="export-tab" class="tab-content">
    <div class="export-panel">
        <h5> Export Attendance History</h5>
        <form method="GET" class="export-form">
            <div class="export-controls">
                <div class="date-input-group">
                    <label for="export_start_date">From:</label>
                    <input id="export_start_date" type="date" name="start_date" class="form-control" value="<?= date('Y-m-01') ?>">
                </div>

                <div class="date-input-group">
                    <label for="export_end_date">To:</label>
                    <input id="export_end_date" type="date" name="end_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div class="export-buttons">
                <button type="submit" name="export" value="excel" class="btn-export btn-export-excel">
                    📅 Export Filtered
                </button>
                <a href="?export=excel" class="btn-export btn-export-all">
                    📋 Export All Records
                </a>
            </div>
        </form>
    </div>

    <div class="section-card" style="margin-top:20px;max-width:800px;">
        <h5 style="margin-top:0;font-size:15px;font-weight:700;text-align:center;"> How to Use Export</h5>
        <ul style="text-align:left;margin-bottom:0;font-size:14px;color:var(--text-muted);">
            <li><strong>Filtered Export:</strong> Select a date range and click "Export Filtered" to get records for specific dates</li>
            <li><strong>All Records:</strong> Click "Export All Records" to download your complete attendance history</li>
            <li><strong>File Format:</strong> Exports as Excel (.xlsx) with formatting, calculations, and summaries</li>
            <li><strong>Includes:</strong> Date, time stamps, status, verification, hours worked, and daily tasks</li>
        </ul>
    </div>
</div>