<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let lastAttendanceCheck = null;
        let lastUpdatesCheck = null;
        const POLL_INTERVAL = 10000;
        
        async function checkAttendanceUpdates() {
            try {
                const url = 'api/check_attendance.php' + (lastAttendanceCheck ? '?since=' + encodeURIComponent(lastAttendanceCheck) : '');
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success && data.latest_timestamp) {
                    if (lastAttendanceCheck && data.latest_timestamp > lastAttendanceCheck) {
                        showNotification('New attendance data detected! Refreshing...', 'info');
                        setTimeout(() => location.reload(), 1500);
                    }
                    lastAttendanceCheck = data.latest_timestamp;
                }
            } catch (err) {
                console.error('Error checking attendance updates:', err);
            }
        }
        
        async function checkDataUpdates() {
            try {
                const url = 'api/check_updates.php' + (lastUpdatesCheck ? '?since=' + encodeURIComponent(lastUpdatesCheck) : '');
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    let hasUpdates = false;
                    let updateMessage = '';
                    
                    if (data.certificates && data.certificates.has_updates) {
                        hasUpdates = true;
                        updateMessage = 'New certificate generated!';
                    }
                    
                    if (data.projects && data.projects.has_updates) {
                        hasUpdates = true;
                        updateMessage = 'New project submission!';
                    }
                    
                    if (hasUpdates) {
                        showNotification(updateMessage + ' Refreshing...', 'info');
                        setTimeout(() => location.reload(), 1500);
                    }
                    
                    const certTime = data.certificates?.latest_timestamp;
                    const projTime = data.projects?.latest_timestamp;
                    
                    if (certTime || projTime) {
                        const times = [certTime, projTime].filter(t => t);
                        if (times.length > 0) {
                            lastUpdatesCheck = times.sort().reverse()[0];
                        }
                    }
                }
            } catch (err) {
                console.error('Error checking data updates:', err);
            }
        }
        
        function showNotification(message, type) {
            const existing = document.querySelector('.polling-notification');
            if (existing) existing.remove();
            
            const notification = document.createElement('div');
            notification.className = 'alert alert-' + type + ' alert-dismissible fade show polling-notification';
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.minWidth = '300px';
            notification.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            
            document.body.appendChild(notification);
            
            setTimeout(function() {
                notification.remove();
            }, 5000);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            checkAttendanceUpdates();
            checkDataUpdates();
            
            setInterval(checkAttendanceUpdates, POLL_INTERVAL);
            setInterval(checkDataUpdates, POLL_INTERVAL);
        });
    </script>
</body>
</html>