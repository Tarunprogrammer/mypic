/**
 * Seamless SPA Page Transitions for Admin Portal
 * Prevents page reloads so background photo ingestion & uploads continue uninterrupted
 */

document.addEventListener('DOMContentLoaded', () => {
    // Intercept sidebar navigation clicks
    document.body.addEventListener('click', async (e) => {
        const link = e.target.closest('.sidebar-link');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href || link.getAttribute('target') === '_blank' || href.includes('logout.php')) {
            return;
        }

        e.preventDefault();
        await navigateAdmin(href);
    });

    // Handle browser forward/back buttons
    window.addEventListener('popstate', () => {
        navigateAdmin(window.location.href, false);
    });
});

async function navigateAdmin(url, pushState = true) {
    try {
        const res = await fetch(url);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const html = await res.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // Extract container content
        const newContainer = doc.querySelector('.admin-container');
        const currentContainer = document.querySelector('.admin-container');
        const newHeaderTitle = doc.querySelector('#adminPageHeaderTitle');
        const currentHeaderTitle = document.querySelector('#adminPageHeaderTitle');

        if (newContainer && currentContainer) {
            currentContainer.innerHTML = newContainer.innerHTML;
        }

        if (newHeaderTitle && currentHeaderTitle) {
            currentHeaderTitle.innerHTML = newHeaderTitle.innerHTML;
        }

        document.title = doc.title;

        if (pushState) {
            window.history.pushState({}, '', url);
        }

        // Update sidebar active status
        const currentPage = url.split('/').pop().split('?')[0] || 'index.php';
        document.querySelectorAll('.sidebar-link').forEach(l => {
            const lHref = l.getAttribute('href') || '';
            if (lHref.endsWith(currentPage)) {
                l.classList.add('active');
            } else {
                l.classList.remove('active');
            }
        });

        // Trigger page-specific initializers
        if (currentPage.includes('upload.php')) {
            if (window.AdminUploader) {
                window.AdminUploader.onPageEnter();
            }
        } else if (currentPage.includes('photos.php')) {
            initPhotosPageHandlers();
        }

    } catch (err) {
        console.warn('SPA navigation fallback to direct load:', err);
        window.location.href = url;
    }
}

// Re-attach handlers on photos.php
function initPhotosPageHandlers() {
    if (typeof selectedAdminPhotoIds !== 'undefined') {
        selectedAdminPhotoIds.clear();
    }
    const selectAll = document.getElementById('adminSelectAll');
    if (selectAll) {
        selectAll.checked = false;
        selectAll.onchange = (e) => {
            const isChecked = e.target.checked;
            const allCheckboxes = document.querySelectorAll('.admin-photo-chk');

            allCheckboxes.forEach(chk => {
                chk.checked = isChecked;
                const photoId = parseInt(chk.dataset.id);
                const card = document.getElementById(`photo-card-${photoId}`);

                if (isChecked) {
                    if (typeof selectedAdminPhotoIds !== 'undefined') selectedAdminPhotoIds.add(photoId);
                    if (card) card.classList.add('is-selected');
                } else {
                    if (typeof selectedAdminPhotoIds !== 'undefined') selectedAdminPhotoIds.delete(photoId);
                    if (card) card.classList.remove('is-selected');
                }
            });

            if (typeof updateAdminSelectionUI === 'function') {
                updateAdminSelectionUI();
            }
        };
    }
}
