<div id="export-tab" class="tab-content">
    <div class="export-panel">
        <h5> Export Attendance History</h5>
        <form method="GET" class="export-form">
            <div class="export-controls">
                <div class="date-input-group">
                    <label>From:</label>
                    <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-01') ?>">
                </div>

                <div class="date-input-group">
                    <label>To:</label>
                    <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d') ?>">
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
            <small style="color: #666; display: block; margin-top: 10px;">
                💡 Export your attendance history to Excel with professional formatting, including time calculations and verification status.
            </small>
        </form>
    </div>

    <div style="margin-top: 30px; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px; background: #f8f9fa; max-width: 800px; margin: 30px auto;">
        <h5 style="margin-top: 0; color: #2c3e50; text-align: center;"> How to Use Export</h5>
        <ul style="text-align: left; margin-bottom: 0;">
            <li><strong>Filtered Export:</strong> Select a date range and click "Export Filtered" to get records for specific dates</li>
            <li><strong>All Records:</strong> Click "Export All Records" to download your complete attendance history</li>
            <li><strong>File Format:</strong> Exports as Excel (.xlsx) with formatting, calculations, and summaries</li>
            <li><strong>Includes:</strong> Date, time stamps, status, verification, hours worked, and daily tasks</li>
        </ul>
    </div>
</div>
