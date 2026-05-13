/**
 * ============================================================
 * REPOBOOK - Main JavaScript
 * Handles sidebar toggle, dropdown, and UI interactions
 * ============================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    // Elements
    const sidebar        = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const hamburgerBtn   = document.getElementById('hamburgerBtn');
    const userDropdownBtn = document.getElementById('userDropdownBtn');
    const dropdownMenu   = document.getElementById('dropdownMenu');

    // ---- SIDEBAR TOGGLE (Mobile) ----
    function openSidebar() {
        sidebar?.classList.add('open');
        sidebarOverlay?.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar?.classList.remove('open');
        sidebarOverlay?.classList.remove('active');
        document.body.style.overflow = '';
    }

    hamburgerBtn?.addEventListener('click', () => {
        if (sidebar?.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    sidebarOverlay?.addEventListener('click', closeSidebar);

    // Close sidebar on ESC key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeSidebar();
            dropdownMenu?.classList.remove('show');
        }
    });

    // ---- USER DROPDOWN ----
    userDropdownBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdownMenu?.classList.toggle('show');
    });

    document.addEventListener('click', (e) => {
        if (dropdownMenu && !e.target.closest('#userDropdown')) {
            dropdownMenu.classList.remove('show');
        }
    });

    // ---- CATEGORY PILLS ----
    const categoryPills = document.querySelectorAll('.category-pill');
    categoryPills.forEach(pill => {
        pill.addEventListener('click', function () {
            // If it's a link, let it navigate naturally
            if (this.tagName === 'A') return;
            categoryPills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // ---- BOOKMARK BUTTON ----
    document.querySelectorAll('.bookmark-btn, .bookmark-btn-detail').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            e.stopPropagation();

            let ebookId = this.dataset.id;
            
            if (!ebookId) return;

            try {
                // Determine BASE_URL dynamically or assume it's set globally
                const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '/Projek/Repo_ebook/public';
                const response = await fetch(baseUrl + '/bookmark.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ebook_id: ebookId })
                });

                const result = await response.json();

                if (result.success) {
                    this.classList.toggle('saved');
                    const icon = this.querySelector('svg');
                    const textSpan = this.querySelector('.bm-text');

                    if (this.classList.contains('saved')) {
                        icon.setAttribute('fill', 'currentColor');
                        if (textSpan) textSpan.textContent = 'Tersimpan';
                    } else {
                        icon.setAttribute('fill', 'none');
                        if (textSpan) textSpan.textContent = 'Simpan';
                    }
                } else {
                    if (result.message === 'Unauthorized') {
                        window.location.href = baseUrl + '/login.php';
                    } else {
                        alert(result.message || 'Gagal menyimpan bookmark.');
                    }
                }
            } catch (error) {
                console.error('Error bookmarking:', error);
            }
        });
    });

    // ---- FLASH MESSAGE AUTO-DISMISS ----
    document.querySelectorAll('.flash-msg').forEach(msg => {
        setTimeout(() => {
            msg.style.opacity = '0';
            msg.style.transform = 'translateY(-10px)';
            setTimeout(() => msg.remove(), 300);
        }, 4000);
    });

    // ---- RESPONSIVE: close sidebar on resize to desktop ----
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        }, 150);
    });

    console.log('📚 RepoBook initialized');
});
