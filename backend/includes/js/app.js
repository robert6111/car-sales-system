document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const closeBtn = document.getElementById('sidebarClose');
    const profileToggle = document.getElementById('profileToggle');
    const profileDropdown = document.getElementById('profileDropdown');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        document.body.classList.add('sidebar-is-open');
        if (overlay) overlay.classList.add('show');
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        document.body.classList.remove('sidebar-is-open');
        if (overlay) overlay.classList.remove('show');
    }

    if (toggle) {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            closeSidebar();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeSidebar();
            if (profileDropdown) profileDropdown.classList.remove('show');
        }
    });

    if (profileToggle && profileDropdown) {
        profileToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });

        profileDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        document.addEventListener('click', function () {
            profileDropdown.classList.remove('show');
        });
    }

    // Auto-hide normal action messages after a few seconds.
    // Inline form validation errors are not touched.
    document.querySelectorAll('.flash-autohide').forEach(function (message) {
        setTimeout(function () {
            message.classList.add('flash-hiding');
            setTimeout(function () {
                if (message && message.parentNode) {
                    message.parentNode.removeChild(message);
                }
            }, 450);
        }, 3500);
    });
});
