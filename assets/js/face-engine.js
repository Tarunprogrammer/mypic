/**
 * High-Accuracy Biometric Face Detection & Feature Extraction Engine (MYPIC)
 * 
 * Powered by Google SSD MobileNet V1 (Deep Neural Network) + TinyFaceDetector Fallback
 * - Detects ALL faces accurately in group photos, portraits, and wide-angle event photos
 * - 68-Point Facial Landmark Alignment
 * - 128-Dimensional Normalized Biometric Descriptors
 */

const FaceEngine = {
    initialized: false,
    modelsLoaded: false,
    loadingPromise: null,
    modelPath: 'assets/models',

    async init(modelPath = null) {
        if (modelPath) {
            this.modelPath = modelPath;
        } else if (window.BASE_URL) {
            this.modelPath = window.BASE_URL + '/assets/models';
        } else {
            this.modelPath = 'assets/models';
        }

        if (typeof faceapi !== 'undefined' && 
            faceapi.nets.ssdMobilenetv1 && faceapi.nets.ssdMobilenetv1.params && 
            faceapi.nets.tinyFaceDetector && faceapi.nets.tinyFaceDetector.params && 
            faceapi.nets.faceLandmark68Net && faceapi.nets.faceLandmark68Net.params && 
            faceapi.nets.faceRecognitionNet && faceapi.nets.faceRecognitionNet.params) {
            this.modelsLoaded = true;
            this.initialized = true;
            return true;
        }

        if (this.modelsLoaded) return true;
        if (this.loadingPromise) return this.loadingPromise;

        this.loadingPromise = (async () => {
            try {
                // Load all models in parallel: SSD MobileNet V1, TinyFaceDetector, 68 Landmarks, 128D Recognition
                const loadTasks = [];
                if (!faceapi.nets.ssdMobilenetv1.params) {
                    loadTasks.push(faceapi.nets.ssdMobilenetv1.loadFromUri(this.modelPath));
                }
                if (!faceapi.nets.tinyFaceDetector.params) {
                    loadTasks.push(faceapi.nets.tinyFaceDetector.loadFromUri(this.modelPath));
                }
                if (!faceapi.nets.faceLandmark68Net.params) {
                    loadTasks.push(faceapi.nets.faceLandmark68Net.loadFromUri(this.modelPath));
                }
                if (!faceapi.nets.faceRecognitionNet.params) {
                    loadTasks.push(faceapi.nets.faceRecognitionNet.loadFromUri(this.modelPath));
                }
                if (loadTasks.length > 0) {
                    await Promise.all(loadTasks);
                }
                this.modelsLoaded = true;
                this.initialized = true;
                return true;
            } catch (err) {
                console.error('FaceEngine model loading failed from ' + this.modelPath, err);
                try {
                    const altPath = (window.BASE_URL || '') + '/assets/models';
                    await Promise.all([
                        faceapi.nets.ssdMobilenetv1.loadFromUri(altPath),
                        faceapi.nets.tinyFaceDetector.loadFromUri(altPath),
                        faceapi.nets.faceLandmark68Net.loadFromUri(altPath),
                        faceapi.nets.faceRecognitionNet.loadFromUri(altPath)
                    ]);
                    this.modelsLoaded = true;
                    this.initialized = true;
                    return true;
                } catch (e2) {
                    console.error('FaceEngine fallback load also failed:', e2);
                    throw e2;
                }
            } finally {
                this.loadingPromise = null;
            }
        })();

        return this.loadingPromise;
    },

    /**
     * Accurate Face Extraction using SSD MobileNet V1 & TinyFaceDetector
     * Detects ALL faces in group, event, and portrait photos
     */
    async extractFacesAccurate(imgElement, options = {}) {
        await this.init();

        const origW = imgElement.naturalWidth || imgElement.width;
        const origH = imgElement.naturalHeight || imgElement.height;

        if (origW === 0 || origH === 0) {
            return [];
        }

        // Scaled Down Offscreen Canvas for AI Detection (Max 1400px for high group detail)
        const maxDetectDim = 1400;
        let scale = 1;
        if (origW > maxDetectDim || origH > maxDetectDim) {
            scale = maxDetectDim / Math.max(origW, origH);
        }

        const detW = Math.round(origW * scale);
        const detH = Math.round(origH * scale);

        const detectCanvas = document.createElement('canvas');
        detectCanvas.width = detW;
        detectCanvas.height = detH;
        const detCtx = detectCanvas.getContext('2d');
        detCtx.drawImage(imgElement, 0, 0, detW, detH);

        let detections = [];

        // 1. Primary Pass: SSD MobileNet V1 (State-of-the-art accuracy on photos)
        if (faceapi.nets.ssdMobilenetv1 && faceapi.nets.ssdMobilenetv1.params) {
            try {
                detections = await faceapi
                    .detectAllFaces(detectCanvas, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.28 }))
                    .withFaceLandmarks()
                    .withFaceDescriptors();
            } catch (ssdErr) {
                console.warn('SSD detector pass error:', ssdErr);
            }
        }

        // 2. Fallback to TinyFaceDetector if SSD found 0
        if (detections.length === 0 && faceapi.nets.tinyFaceDetector && faceapi.nets.tinyFaceDetector.params) {
            try {
                detections = await faceapi
                    .detectAllFaces(detectCanvas, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.20 }))
                    .withFaceLandmarks()
                    .withFaceDescriptors();
            } catch (tinyErr) {
                console.warn('Tiny detector fallback pass error:', tinyErr);
            }
        }

        const detectedFaces = [];

        // Map detected coordinates back to original image dimensions
        detections.forEach(det => {
            const box = det.detection.box;
            detectedFaces.push({
                box: {
                    x: Math.round(box.x / scale),
                    y: Math.round(box.y / scale),
                    width: Math.round(box.width / scale),
                    height: Math.round(box.height / scale)
                },
                score: det.detection.score,
                landmarks: det.landmarks,
                descriptor: Array.from(det.descriptor)
            });
        });

        // Sort left-to-right
        detectedFaces.sort((a, b) => a.box.x - b.box.x);

        return detectedFaces;
    },

    /**
     * Generates a square cropped face avatar dataURL for inspection
     */
    generateFaceCropDataUrl(imgElement, box, targetSize = 120) {
        try {
            const origW = imgElement.naturalWidth || imgElement.width;
            const origH = imgElement.naturalHeight || imgElement.height;

            const marginX = box.width * 0.25;
            const marginY = box.height * 0.25;

            const cropX = Math.max(0, box.x - marginX);
            const cropY = Math.max(0, box.y - marginY);
            const cropW = Math.min(origW - cropX, box.width + marginX * 2);
            const cropH = Math.min(origH - cropY, box.height + marginY * 2);

            const canvas = document.createElement('canvas');
            canvas.width = targetSize;
            canvas.height = targetSize;
            const ctx = canvas.getContext('2d');

            ctx.drawImage(imgElement, cropX, cropY, cropW, cropH, 0, 0, targetSize, targetSize);
            return canvas.toDataURL('image/jpeg', 0.85);
        } catch (e) {
            return '';
        }
    }
};

window.FaceEngine = FaceEngine;
