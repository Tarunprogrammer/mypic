        </div> <!-- End admin-container -->
    </div> <!-- End admin-main -->

    <!-- Global Full-Screen Photo Viewer Modal -->
    <div class="lightbox-modal" id="lightboxModal">
        <div class="lightbox-content">
            <button type="button" class="lightbox-close" id="lightboxClose" title="Close"><i class="ri-close-line"></i></button>
            
            <div class="lightbox-img-container" id="lightboxImgContainer">
                <img src="" class="lightbox-img" id="lightboxImg" alt="Full Photo">
            </div>

            <div style="position: fixed; bottom: 24px; left: 0; right: 0; display: flex; justify-content: center; z-index: 3060;">
                <a href="#" id="lightboxDownload" class="btn btn-primary btn-sm" download style="padding: 10px 24px; font-weight: 800; border-radius: var(--radius-full);">
                    <i class="ri-download-2-line"></i> Download Photo
                </a>
            </div>
        </div>
    </div>

    <!-- Mobile Sidebar & Global Initializer Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            const sidebar = document.getElementById('adminSidebar');
            
            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    sidebar.classList.toggle('show');
                });

                document.addEventListener('click', (e) => {
                    if (sidebar.classList.contains('show') && !sidebar.contains(e.target) && e.target !== toggleBtn) {
                        sidebar.classList.remove('show');
                    }
                });
            }

            if (typeof Lightbox !== 'undefined') {
                Lightbox.init();
            }
        });
    </script>
</body>
</html>
