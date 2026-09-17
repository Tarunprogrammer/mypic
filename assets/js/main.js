/**
 * Main Application Utilities & Fit-to-Screen Interactive Zoom Lightbox
 * Features:
 * - Full Fit-to-Screen Photo Viewer
 * - Pinch-to-Zoom & Double-Tap Zoom (1x to 4x)
 * - Pan / Drag navigation when zoomed
 * - Mouse wheel zoom on Desktop
 * - Direct "Save Photo" button & Mobile Back-Button Trap
 */

function showToast(message, type = 'info', duration = 4000) {
    return;
}

const Lightbox = {
    modal: null,
    stage: null,
    img: null,
    downloadBtn: null,
    zoomBtn: null,
    counterEl: null,
    prevBtn: null,
    nextBtn: null,
    isOpen: false,

    // Gallery state & In-Memory Preload Buffer Cache
    gallery: [],
    currentIndex: 0,
    preloadCache: new Map(),

    // Zoom, Pan & Rotation State
    scale: 1,
    minScale: 1,
    maxScale: 4,
    posX: 0,
    posY: 0,
    startX: 0,
    startY: 0,
    rotation: 0,
    isDragging: false,

    // Two-Finger Gestures (Pinch Zoom + Rotate)
    initialPinchDist: 0,
    initialPinchScale: 1,
    initialTouchAngle: 0,
    initialRotation: 0,
    isTwoFingerGesture: false,

    // Double Tap State
    lastTapTime: 0,
    lastTapX: 0,
    lastTapY: 0,

    // Swipe Gesture State (when scale === 1)
    isSwiping: false,
    isSwipeLocked: false,
    swipeStartX: 0,
    swipeStartY: 0,
    swipeDeltaX: 0,

    // Desktop Mouse Drag Swipe
    isMouseSwiping: false,
    mouseSwipeStartX: 0,
    mouseSwipeDeltaX: 0,
    currentLoadToken: 0,

    init() {
        this.modal = document.getElementById('lightboxModal');
        this.stage = document.getElementById('lightboxImgContainer') || document.querySelector('.lightbox-img-container');
        this.img = document.getElementById('lightboxImg');
        this.downloadBtn = document.getElementById('lightboxDownload');
        this.spinnerEl = document.getElementById('lightboxSpinner');

        if (!this.modal || !this.img) return;

        // Backdrop click: tapping/clicking outside the photo closes the modal
        this.modal.addEventListener('click', (e) => {
            if (e.target.closest('#lightboxDownload') || e.target.closest('.lightbox-download-icon') || e.target.closest('.btn-save-photo')) {
                return;
            }
            if (e.target === this.modal || e.target === this.stage || e.target.classList.contains('lightbox-img-container') || e.target.classList.contains('lightbox-content') || e.target.closest('#lightboxClose') || e.target.classList.contains('lightbox-close')) {
                this.close();
            }
        });

        // Setup Touch Gestures (Pinch-to-zoom, Pan, Double-tap, Horizontal Swipe)
        this.setupTouchGestures();

        // Setup Desktop Mouse Gestures (Wheel zoom, drag pan, double click, drag swipe)
        this.setupMouseGestures();

        // Keyboard navigation (Escape to close, Left/Right arrows to navigate)
        document.addEventListener('keydown', (e) => {
            if (!this.isOpen) return;
            if (e.key === 'Escape') {
                this.close();
            } else if (e.key === 'ArrowRight') {
                this.next();
            } else if (e.key === 'ArrowLeft') {
                this.prev();
            }
        });

        // Native Phone Back Button Trap (handles both Lightbox and Selection Mode)
        window.addEventListener('popstate', (e) => {
            if (this.isOpen) {
                this.close(false);
                return;
            }
            if (window.FaceScanner && window.FaceScanner.isSelectionMode) {
                window.FaceScanner.exitSelectionMode(false);
                return;
            }
        });
    },

    setupTouchGestures() {
        if (!this.stage) return;

        this.stage.addEventListener('touchstart', (e) => {
            if (!this.isOpen) return;

            if (e.touches.length === 2) {
                // Two-Finger Gesture (Pinch Zoom + Two-Finger Rotate)
                this.isTwoFingerGesture = true;
                this.isSwiping = false;
                this.isSwipeLocked = false;
                this.isDragging = false;

                const t1 = e.touches[0];
                const t2 = e.touches[1];

                // 1. Initial distance for pinch zoom
                this.initialPinchDist = Math.hypot(t2.clientX - t1.clientX, t2.clientY - t1.clientY);
                this.initialPinchScale = this.scale;

                // 2. Initial angle for two-finger rotation
                this.initialTouchAngle = Math.atan2(t2.clientY - t1.clientY, t2.clientX - t1.clientX) * (180 / Math.PI);
                this.initialRotation = this.rotation;

            } else if (e.touches.length === 1) {
                this.isTwoFingerGesture = false;
                const touch = e.touches[0];
                const now = Date.now();

                // Double Tap Detection: within 350ms and 40px radius
                const timeDiff = now - this.lastTapTime;
                const distDiff = Math.hypot(touch.clientX - this.lastTapX, touch.clientY - this.lastTapY);

                if (timeDiff < 350 && distDiff < 40) {
                    // Double Tap Zoom In / Out
                    this.lastTapTime = 0;
                    this.isSwiping = false;
                    this.isSwipeLocked = false;
                    this.isDragging = false;

                    if (this.scale > 1.05) {
                        // If already zoomed, double tap zooms back out to 1x
                        this.resetZoom(true);
                    } else {
                        // Double tap to zoom in (2.5x) centered on the tapped point
                        const centerX = window.innerWidth / 2;
                        const centerY = window.innerHeight / 2;
                        const targetScale = 2.5;
                        const targetX = -(touch.clientX - centerX) * (targetScale - 1);
                        const targetY = -(touch.clientY - centerY) * (targetScale - 1);
                        this.setZoom(targetScale, targetX, targetY, true);
                    }
                    return;
                }

                this.lastTapTime = now;
                this.lastTapX = touch.clientX;
                this.lastTapY = touch.clientY;

                if (this.scale > 1) {
                    // Panning when zoomed
                    this.isDragging = true;
                    this.startX = touch.clientX - this.posX;
                    this.startY = touch.clientY - this.posY;
                    this.isSwiping = false;
                    this.isSwipeLocked = false;
                } else {
                    // Normal 1x fit: prepare for horizontal swipe
                    this.isSwiping = true;
                    this.isSwipeLocked = false;
                    this.swipeStartX = touch.clientX;
                    this.swipeStartY = touch.clientY;
                    this.swipeDeltaX = 0;
                }
            }
        }, { passive: false });

        this.stage.addEventListener('touchmove', (e) => {
            if (!this.isOpen) return;

            if (e.touches.length === 2 && this.isTwoFingerGesture) {
                e.preventDefault();
                const t1 = e.touches[0];
                const t2 = e.touches[1];

                // 1. Pinch Zoom
                if (this.initialPinchDist > 0) {
                    const currentDist = Math.hypot(t2.clientX - t1.clientX, t2.clientY - t1.clientY);
                    const newScale = Math.min(this.maxScale, Math.max(this.minScale, this.initialPinchScale * (currentDist / this.initialPinchDist)));
                    this.scale = newScale;
                }

                // 2. Two-Finger Rotation
                const currentAngle = Math.atan2(t2.clientY - t1.clientY, t2.clientX - t1.clientX) * (180 / Math.PI);
                const angleDiff = currentAngle - this.initialTouchAngle;
                this.rotation = this.initialRotation + angleDiff;

                this.applyTransform(false);

            } else if (e.touches.length === 1 && this.isDragging && this.scale > 1) {
                // Panning when zoomed
                e.preventDefault();
                const touch = e.touches[0];
                this.posX = touch.clientX - this.startX;
                this.posY = touch.clientY - this.startY;
                this.applyTransform(false);

            } else if (e.touches.length === 1 && this.isSwiping && this.scale <= 1) {
                const touch = e.touches[0];
                const diffX = touch.clientX - this.swipeStartX;
                const diffY = touch.clientY - this.swipeStartY;

                // Invalidate tap if finger moved more than 8px
                if (Math.hypot(diffX, diffY) > 8) {
                    this.lastTapTime = 0;
                }

                if (!this.isSwipeLocked) {
                    if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 8) {
                        this.isSwipeLocked = true;
                    } else if (Math.abs(diffY) > 12) {
                        this.isSwiping = false;
                        return;
                    }
                }

                if (this.isSwipeLocked) {
                    e.preventDefault();
                    this.swipeDeltaX = diffX;
                    if (this.img) {
                        this.img.style.transition = 'none';
                        this.img.style.transform = `translate3d(${this.swipeDeltaX * 0.75}px, 0px, 0px) scale(1) rotate(${this.rotation}deg)`;
                    }
                }
            }
        }, { passive: false });

        this.stage.addEventListener('touchend', (e) => {
            if (e.touches.length < 2 && this.isTwoFingerGesture) {
                this.isTwoFingerGesture = false;
                this.initialPinchDist = 0;

                // Smoothly snap to nearest 90 degrees on rotation release
                const nearest90 = Math.round(this.rotation / 90) * 90;
                this.rotation = nearest90;

                if (this.scale < 1) {
                    this.scale = 1;
                    this.posX = 0;
                    this.posY = 0;
                } else if (this.scale > this.maxScale) {
                    this.scale = this.maxScale;
                }

                this.clampPosition(true);
                return;
            }

            if (e.touches.length === 0) {
                this.isDragging = false;

                if (this.scale <= 1) {
                    if (this.isSwipeLocked && Math.abs(this.swipeDeltaX) > 0) {
                        const threshold = Math.min(50, window.innerWidth * 0.16);
                        if (this.swipeDeltaX < -threshold) {
                            // Swiped left -> NEXT photo
                            this.next();
                        } else if (this.swipeDeltaX > threshold) {
                            // Swiped right -> PREVIOUS photo
                            this.prev();
                        } else {
                            if (this.img) {
                                this.img.style.transition = 'transform 0.25s cubic-bezier(0.16, 1, 0.3, 1)';
                                this.img.style.transform = `translate3d(0px, 0px, 0px) scale(1) rotate(${this.rotation}deg)`;
                            }
                        }
                    } else {
                        // Return to center position (respecting rotation)
                        this.posX = 0;
                        this.posY = 0;
                        this.applyTransform(true);
                    }
                    this.isSwiping = false;
                    this.isSwipeLocked = false;
                    this.swipeDeltaX = 0;
                } else {
                    this.clampPosition(true);
                }
            }
        });
    },

    setupMouseGestures() {
        if (!this.stage) return;

        // Desktop Double Click
        this.stage.addEventListener('dblclick', (e) => {
            if (this.scale > 1.05) {
                this.resetZoom(true);
            } else {
                const centerX = window.innerWidth / 2;
                const centerY = window.innerHeight / 2;
                const targetScale = 2.5;
                const targetX = -(e.clientX - centerX) * (targetScale - 1);
                const targetY = -(e.clientY - centerY) * (targetScale - 1);
                this.setZoom(targetScale, targetX, targetY, true);
            }
        });

        // Mouse Wheel Zoom
        this.stage.addEventListener('wheel', (e) => {
            if (!this.isOpen) return;
            e.preventDefault();
            const delta = e.deltaY < 0 ? 0.3 : -0.3;
            const newScale = Math.min(this.maxScale, Math.max(this.minScale, this.scale + delta));
            this.setZoom(newScale, this.posX, this.posY, true);
        }, { passive: false });

        // Mouse Drag (Pan when zoomed, Swipe when at 1x)
        this.stage.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return;
            if (e.target.closest('.lightbox-close') || e.target.closest('.lightbox-zoom-btn') || e.target.closest('.btn-save-photo') || e.target.closest('#lightboxDownload') || e.target.closest('.lightbox-download-icon')) return;

            if (this.scale > 1) {
                this.isDragging = true;
                this.startX = e.clientX - this.posX;
                this.startY = e.clientY - this.posY;
                this.stage.style.cursor = 'grabbing';
            } else {
                this.isMouseSwiping = true;
                this.mouseSwipeStartX = e.clientX;
                this.mouseSwipeDeltaX = 0;
            }
        });

        window.addEventListener('mousemove', (e) => {
            if (this.isDragging && this.scale > 1) {
                e.preventDefault();
                this.posX = e.clientX - this.startX;
                this.posY = e.clientY - this.startY;
                this.applyTransform(false);
            } else if (this.isMouseSwiping && this.scale <= 1) {
                this.mouseSwipeDeltaX = e.clientX - this.mouseSwipeStartX;
                if (Math.abs(this.mouseSwipeDeltaX) > 8 && this.img) {
                    this.img.style.transition = 'none';
                    this.img.style.transform = `translate3d(${this.mouseSwipeDeltaX * 0.55}px, 0px, 0px) scale(1) rotate(${this.rotation}deg)`;
                }
            }
        });

        window.addEventListener('mouseup', () => {
            if (this.isDragging) {
                this.isDragging = false;
                if (this.stage) this.stage.style.cursor = '';
                this.clampPosition(true);
            }
            if (this.isMouseSwiping) {
                this.isMouseSwiping = false;
                if (Math.abs(this.mouseSwipeDeltaX) > 50) {
                    if (this.mouseSwipeDeltaX < 0) {
                        this.next();
                    } else {
                        this.prev();
                    }
                } else if (this.img && this.scale <= 1) {
                    this.img.style.transition = 'transform 0.25s cubic-bezier(0.16, 1, 0.3, 1)';
                    this.img.style.transform = `translate3d(0px, 0px, 0px) scale(1) rotate(${this.rotation}deg)`;
                }
                this.mouseSwipeDeltaX = 0;
            }
        });
    },

    /**
     * Calculate the scale factor needed to fit the image to the screen at a given rotation angle
     */
    getFitScale(rotationDeg = this.rotation) {
        if (!this.img) return 1;

        const Vw = window.innerWidth;
        const Vh = window.innerHeight;
        if (!Vw || !Vh) return 1;

        const Nw = this.img.naturalWidth || this.img.clientWidth || Vw;
        const Nh = this.img.naturalHeight || this.img.clientHeight || Vh;
        if (!Nw || !Nh) return 1;

        const imgRatio = Nw / Nh;
        const viewRatio = Vw / Vh;

        // Unrotated fit dimensions within viewport
        let w0, h0;
        if (imgRatio > viewRatio) {
            w0 = Vw;
            h0 = Vw / imgRatio;
        } else {
            h0 = Vh;
            w0 = Vh * imgRatio;
        }

        const rad = (rotationDeg * Math.PI) / 180;
        const cos = Math.abs(Math.cos(rad));
        const sin = Math.abs(Math.sin(rad));

        const bboxWidth = w0 * cos + h0 * sin;
        const bboxHeight = w0 * sin + h0 * cos;

        if (bboxWidth <= 0 || bboxHeight <= 0) return 1;

        return Math.min(Vw / bboxWidth, Vh / bboxHeight);
    },

    setZoom(scale, x = 0, y = 0, animate = true) {
        this.scale = Math.min(this.maxScale, Math.max(this.minScale, scale));
        this.posX = this.scale === 1 ? 0 : x;
        this.posY = this.scale === 1 ? 0 : y;

        if (this.zoomBtn) {
            this.zoomBtn.innerHTML = this.scale > 1 ? '<i class="ri-zoom-out-line"></i>' : '<i class="ri-zoom-in-line"></i>';
        }

        this.applyTransform(animate);
    },

    clampPosition(animate = true) {
        if (!this.img) return;
        const fitScale = this.getFitScale(this.rotation);
        const totalScale = this.scale * fitScale;
        const effectiveScale = Math.max(1, totalScale);

        const maxOffset = (effectiveScale - 1) * Math.max(window.innerWidth, window.innerHeight) * 0.45;
        this.posX = Math.max(-maxOffset, Math.min(maxOffset, this.posX));
        this.posY = Math.max(-maxOffset, Math.min(maxOffset, this.posY));
        this.applyTransform(animate);
    },

    applyTransform(animate = true) {
        if (!this.img) return;
        const fitScale = this.getFitScale(this.rotation);
        const totalScale = this.scale * fitScale;

        this.img.style.transition = animate ? 'transform 0.25s cubic-bezier(0.16, 1, 0.3, 1)' : 'none';
        this.img.style.transform = `translate3d(${this.posX}px, ${this.posY}px, 0px) scale(${totalScale}) rotate(${this.rotation}deg)`;
    },

    resetZoom(animate = true) {
        this.scale = 1;
        this.posX = 0;
        this.posY = 0;
        this.isDragging = false;
        if (this.zoomBtn) {
            this.zoomBtn.innerHTML = '<i class="ri-zoom-in-line"></i>';
        }
        this.applyTransform(animate);
    },

    resetRotation(animate = true) {
        this.rotation = 0;
        this.applyTransform(animate);
    },

    /**
     * Preload an image into memory buffer cache with async off-thread decoding
     */
    preloadImage(url) {
        if (!url) return null;
        if (this.preloadCache.has(url)) {
            const cached = this.preloadCache.get(url);
            if (cached.img && cached.img.complete && cached.img.naturalWidth > 0) {
                cached.loaded = true;
            }
            return cached;
        }

        // Keep cache bounded to 40 items to preserve mobile memory
        if (this.preloadCache.size >= 40) {
            const oldestKey = this.preloadCache.keys().next().value;
            this.preloadCache.delete(oldestKey);
        }

        const img = new Image();
        try {
            img.decoding = 'async';
        } catch (e) {}

        const entry = { img: img, loaded: false };
        this.preloadCache.set(url, entry);

        img.onload = () => {
            entry.loaded = true;
        };
        img.onerror = () => {
            entry.loaded = false;
        };
        img.src = url;

        if (img.complete && img.naturalWidth > 0) {
            entry.loaded = true;
        }

        return entry;
    },

    /**
     * Proactively preload adjacent images in both directions (+1, -1, +2, -2, +3, -3)
     */
    fillBuffer(centerIndex, distance = 3) {
        if (!this.gallery || this.gallery.length <= 1) return;
        const total = this.gallery.length;
        const count = Math.min(distance, Math.floor(total / 2));

        // Prioritize forward (+1 first, then -1, +2, -2, +3, -3)
        const indicesToLoad = [];
        for (let i = 1; i <= count; i++) {
            indicesToLoad.push((centerIndex + i) % total);
            indicesToLoad.push((centerIndex - i + total) % total);
        }

        const uniqueIndices = [...new Set(indicesToLoad)];
        uniqueIndices.forEach(idx => {
            const item = this.gallery[idx];
            if (item && item.url) {
                this.preloadImage(item.url);
            }
            if (item && item.thumbUrl && item.thumbUrl !== item.url) {
                this.preloadImage(item.thumbUrl);
            }
        });
    },

    /**
     * Background idle prefill for initial search results
     */
    prefillBuffer(items) {
        if (!Array.isArray(items) || items.length === 0) return;
        const batch = items.slice(0, 6);
        const loadNext = (i) => {
            if (i >= batch.length) return;
            const item = batch[i];
            if (item && item.url) {
                const entry = this.preloadImage(item.url);
                if (entry && entry.img) {
                    if (entry.loaded || (entry.img.complete && entry.img.naturalWidth > 0)) {
                        loadNext(i + 1);
                    } else {
                        entry.img.addEventListener('load', () => loadNext(i + 1), { once: true });
                        entry.img.addEventListener('error', () => loadNext(i + 1), { once: true });
                    }
                } else {
                    loadNext(i + 1);
                }
            } else {
                loadNext(i + 1);
            }
        };

        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(() => loadNext(0), { timeout: 2000 });
        } else {
            setTimeout(() => loadNext(0), 250);
        }
    },

    openGallery(items, startIndex = 0) {
        if (!this.modal) this.init();
        if (!Array.isArray(items) || items.length === 0) return;

        this.gallery = items;
        this.currentIndex = (startIndex >= 0 && startIndex < items.length) ? startIndex : 0;
        const currentItem = this.gallery[this.currentIndex];

        this.open(currentItem.url, currentItem.name, currentItem.downloadUrl, false, currentItem.thumbUrl || currentItem.url);
        this.fillBuffer(this.currentIndex, 3);
    },

    open(src, name = 'photo.jpg', downloadUrl = '', isCircle = false, thumbUrl = '') {
        if (!this.modal) this.init();
        if (!src) return;

        this.isOpen = true;
        this.resetZoom(false);
        this.rotation = 0;
        const token = ++this.currentLoadToken;

        // If gallery wasn't explicitly populated (e.g. single image clicked), wrap it
        if (!this.gallery || this.gallery.length === 0 || (this.gallery.length > 0 && this.gallery[this.currentIndex]?.url !== src && !this.gallery.some(g => g.url === src))) {
            this.gallery = [{ url: src, name: name, downloadUrl: downloadUrl, thumbUrl: thumbUrl || src }];
            this.currentIndex = 0;
        } else if (this.gallery.length > 0) {
            const found = this.gallery.findIndex(g => g.url === src);
            if (found !== -1) this.currentIndex = found;
        }

        if (isCircle) {
            if (this.stage) this.stage.classList.add('circle-mode');
            if (this.modal) this.modal.classList.add('is-circle-profile');
        } else {
            if (this.stage) this.stage.classList.remove('circle-mode');
            if (this.modal) this.modal.classList.remove('is-circle-profile');
        }

        const dlUrl = downloadUrl || src;
        const dlName = name || 'photo.jpg';

        if (this.downloadBtn) {
            this.downloadBtn.href = dlUrl;
            this.downloadBtn.setAttribute('download', dlName);
        }

        const effectiveThumb = thumbUrl || (this.gallery[this.currentIndex] ? this.gallery[this.currentIndex].thumbUrl : '') || src;

        if (this.img) {
            const cacheEntry = this.preloadImage(src);
            const isFullLoaded = cacheEntry && (cacheEntry.loaded || (cacheEntry.img.complete && cacheEntry.img.naturalWidth > 0));

            if (isFullLoaded) {
                if (this.spinnerEl) this.spinnerEl.classList.remove('is-active');
                this.img.src = src;
                this.img.style.transition = 'none';
                this.img.style.opacity = '1';
                this.img.style.transform = 'translate3d(0px, 0px, 0px) scale(1)';
            } else {
                // Instantly show thumbnail placeholder if available so screen is never blank
                if (effectiveThumb && effectiveThumb !== src) {
                    this.img.src = effectiveThumb;
                    this.img.style.transition = 'none';
                    this.img.style.opacity = '1';
                    this.img.style.transform = 'translate3d(0px, 0px, 0px) scale(1)';
                } else {
                    this.img.style.transition = 'none';
                    this.img.style.opacity = '0';
                    this.img.style.transform = 'translate3d(0px, 0px, 0px) scale(1)';
                }

                if (this.spinnerEl) this.spinnerEl.classList.add('is-active');

                const onReady = () => {
                    if (token !== this.currentLoadToken || !this.isOpen) return;
                    if (this.spinnerEl) this.spinnerEl.classList.remove('is-active');
                    this.img.src = src;
                    this.img.style.transition = 'opacity 0.22s ease';
                    this.img.style.opacity = '1';
                };

                if (cacheEntry && cacheEntry.img) {
                    if (cacheEntry.loaded || (cacheEntry.img.complete && cacheEntry.img.naturalWidth > 0)) {
                        onReady();
                    } else {
                        cacheEntry.img.addEventListener('load', onReady, { once: true });
                        cacheEntry.img.addEventListener('error', () => {
                            if (token !== this.currentLoadToken || !this.isOpen) return;
                            if (this.spinnerEl) this.spinnerEl.classList.remove('is-active');
                            this.img.src = src;
                            this.img.style.opacity = '1';
                        }, { once: true });
                    }
                }
            }
        }

        if (this.modal) {
            this.modal.classList.add('active');
        }
        document.body.style.overflow = 'hidden';

        // Fill buffer around current photo
        this.fillBuffer(this.currentIndex, 3);

        try {
            history.pushState({ mypic_lightbox: true }, '');
        } catch (e) {}
    },

    next() {
        if (!this.gallery || this.gallery.length <= 1) return;
        const newIndex = (this.currentIndex + 1) % this.gallery.length;
        this.showPhoto(newIndex, 'next');
    },

    prev() {
        if (!this.gallery || this.gallery.length <= 1) return;
        const newIndex = (this.currentIndex - 1 + this.gallery.length) % this.gallery.length;
        this.showPhoto(newIndex, 'prev');
    },

    showPhoto(index, direction = 'next') {
        if (!this.gallery || this.gallery.length === 0) return;
        if (index < 0) index = this.gallery.length - 1;
        if (index >= this.gallery.length) index = 0;

        // Ensure index moves to a distinct photo if more than 1 photo exists
        if (this.gallery.length > 1 && this.gallery[index]?.url === this.gallery[this.currentIndex]?.url && index === this.currentIndex) {
            index = direction === 'next' ? (index + 1) % this.gallery.length : (index - 1 + this.gallery.length) % this.gallery.length;
        }

        this.currentIndex = index;
        const item = this.gallery[this.currentIndex];
        if (!item || !this.img) return;

        this.resetZoom(false);
        this.rotation = 0;
        const token = ++this.currentLoadToken;

        const dlUrl = item.downloadUrl || item.url;
        const dlName = item.name || 'photo.jpg';
        if (this.downloadBtn) {
            this.downloadBtn.href = dlUrl;
            this.downloadBtn.setAttribute('download', dlName);
        }

        const enterFromX = direction === 'next' ? 60 : -60;

        // Check if high-res image is in buffer
        const cacheEntry = this.preloadImage(item.url);
        const isFullLoaded = cacheEntry && (cacheEntry.loaded || (cacheEntry.img.complete && cacheEntry.img.naturalWidth > 0));

        if (isFullLoaded) {
            // Buffer hit: 0ms instant display, no spinner, crisp photo
            if (this.spinnerEl) {
                this.spinnerEl.classList.remove('is-active');
            }

            this.img.src = item.url;
            this.img.style.transition = 'none';
            this.img.style.transform = `translate3d(${enterFromX}px, 0px, 0px) scale(0.96)`;
            this.img.style.opacity = '0.35';

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    if (token !== this.currentLoadToken || !this.isOpen) return;
                    this.img.style.transition = 'transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease';
                    this.img.style.transform = 'translate3d(0px, 0px, 0px) scale(1)';
                    this.img.style.opacity = '1';
                });
            });
        } else {
            // Buffer in flight: instant progressive thumbnail fallback
            const placeholderSrc = item.thumbUrl || item.url;
            this.img.src = placeholderSrc;
            this.img.style.transition = 'none';
            this.img.style.transform = `translate3d(${enterFromX}px, 0px, 0px) scale(0.96)`;
            this.img.style.opacity = '0.7';

            if (this.spinnerEl) {
                this.spinnerEl.classList.add('is-active');
            }

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    if (token !== this.currentLoadToken || !this.isOpen) return;
                    this.img.style.transition = 'transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease';
                    this.img.style.transform = 'translate3d(0px, 0px, 0px) scale(1)';
                    this.img.style.opacity = '1';
                });
            });

            const onHighResReady = () => {
                if (token !== this.currentLoadToken || !this.isOpen) return;
                if (this.spinnerEl) {
                    this.spinnerEl.classList.remove('is-active');
                }
                this.img.src = item.url;
            };

            if (cacheEntry && cacheEntry.img) {
                if (cacheEntry.loaded || (cacheEntry.img.complete && cacheEntry.img.naturalWidth > 0)) {
                    onHighResReady();
                } else {
                    cacheEntry.img.addEventListener('load', onHighResReady, { once: true });
                    cacheEntry.img.addEventListener('error', () => {
                        if (token !== this.currentLoadToken || !this.isOpen) return;
                        if (this.spinnerEl) {
                            this.spinnerEl.classList.remove('is-active');
                        }
                    }, { once: true });
                }
            }
        }

        // Fill buffer for adjacent images
        this.fillBuffer(this.currentIndex, 3);
    },

    close(popHistory = true) {
        if (!this.isOpen) return;
        this.isOpen = false;
        this.currentLoadToken++;
        this.resetZoom(false);
        this.rotation = 0;
        this.isSwiping = false;
        this.isSwipeLocked = false;
        this.swipeDeltaX = 0;

        if (this.spinnerEl) {
            this.spinnerEl.classList.remove('is-active');
        }
        if (this.stage) this.stage.classList.remove('circle-mode');
        if (this.modal) {
            this.modal.classList.remove('active');
            this.modal.classList.remove('is-circle-profile');
        }
        document.body.style.overflow = '';

        if (this.img) {
            this.img.src = '';
            this.img.style.transform = '';
            this.img.style.opacity = '1';
        }

        if (popHistory) {
            try {
                if (history.state && history.state.mypic_lightbox) {
                    history.back();
                }
            } catch (e) {}
        }
    }
};

window.Lightbox = Lightbox;

document.addEventListener('DOMContentLoaded', () => {
    Lightbox.init();
});
