</main>

<script>
// =============================================
// Sidebar Toggle
// =============================================
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
});

// =============================================
// User Menu Dropdown
// =============================================
document.getElementById('userMenuBtn')?.addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('userDropdown').classList.toggle('hidden');
});

document.addEventListener('click', function() {
    document.getElementById('userDropdown')?.classList.add('hidden');
});

// =============================================
// Session Timer
// =============================================
const sessionStart = <?php echo (int)($_SESSION['login_time'] ?? time()); ?>;

function formatTime(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return `${h}h ${m}m`;
}

function updateTimer() {
    const sessionSeconds = Math.max(0, Math.floor(Date.now() / 1000) - sessionStart);
    const timerEl = document.getElementById('sessionTimer');
    if (timerEl) {
        timerEl.textContent = formatTime(sessionSeconds);
    }
}

updateTimer();
setInterval(updateTimer, 1000);

// =============================================
// Session Timeout Warning
// =============================================
let idleTime = 0;
const IDLE_LIMIT = 25 * 60; // 25 minutes
const TIMEOUT = 30 * 60;     // 30 minutes

setInterval(function() {
    idleTime++;
    
    if (idleTime === IDLE_LIMIT) {
        if (confirm('You have been idle for 25 minutes. Click OK to stay logged in.')) {
            idleTime = 0;
        }
    }
    
    if (idleTime >= TIMEOUT) {
        window.location.href = 'logout.php?timeout=1';
    }
}, 1000);

// Reset idle timer on user activity
['click', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(function(event) {
    document.addEventListener(event, function() {
        idleTime = 0;
    }, true);
});

// =============================================
// Auto-hide Flash Messages
// =============================================
const notificationBtn = document.getElementById('notificationBtn');
const notificationDropdown = document.getElementById('notificationDropdown');
if (notificationBtn && notificationDropdown) {
    notificationBtn.addEventListener('click', function(event) {
        event.stopPropagation();
        notificationDropdown.classList.toggle('hidden');
    });
    document.addEventListener('click', function(event) {
        if (!notificationDropdown.contains(event.target) && !notificationBtn.contains(event.target)) {
            notificationDropdown.classList.add('hidden');
        }
    });
}

setTimeout(() => {
    document.querySelectorAll('.flash-message').forEach(el => {
        el.style.opacity = '0';
        el.style.transition = 'opacity 0.5s';
        setTimeout(() => el.remove(), 500);
    });
}, 5000);
</script>

</body>
</html>