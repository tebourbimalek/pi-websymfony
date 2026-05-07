/**
 * Face Authentication JavaScript Module
 * Handles face capture, registration, and login using OpenCV
 */

class FaceAuth {
    constructor() {
        this.video = null;
        this.canvas = null;
        this.stream = null;
        this.isInitialized = false;
    }

    /**
     * Initialize webcam for face capture
     */
    async initWebcam(videoElementId, canvasElementId) {
        this.video = document.getElementById(videoElementId);
        this.canvas = document.getElementById(canvasElementId);

        if (!this.video || !this.canvas) {
            console.error('Video or canvas element not found');
            return false;
        }

        try {
            console.log('Requesting camera access...');
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: 'user'
                },
                audio: false
            });

            console.log('Camera access granted, setting up video...');
            this.video.srcObject = this.stream;
            this.video.style.display = 'block';
            this.canvas.style.display = 'none';

            // Wait for video to be ready with timeout
            return new Promise((resolve) => {
                const timeout = setTimeout(() => {
                    console.warn('Video metadata timeout, trying anyway...');
                    this.isInitialized = true;
                    resolve(true);
                }, 3000); // 3 second timeout

                this.video.onloadedmetadata = () => {
                    clearTimeout(timeout);
                    console.log('Video metadata loaded');
                    this.video.play().then(() => {
                        this.isInitialized = true;
                        console.log('Webcam initialized and playing');
                        resolve(true);
                    }).catch(e => {
                        console.error('Error playing video:', e);
                        resolve(false);
                    });
                };
            });
        } catch (error) {
            console.error('Error accessing webcam:', error);
            this.isInitialized = false;
            return false;
        }
    }

    /**
     * Capture face image from webcam
     */
    captureImage() {
        console.log('Capture attempt - isInitialized:', this.isInitialized, 'video:', !!this.video, 'canvas:', !!this.canvas);
        
        if (!this.isInitialized) {
            console.error('Capture failed: isInitialized is false');
            alert('Camera not initialized. Please wait or click "Start Camera".');
            return null;
        }
        
        if (!this.video) {
            console.error('Capture failed: video element is null');
            alert('Video element not found.');
            return null;
        }
        
        if (!this.canvas) {
            console.error('Capture failed: canvas element is null');
            alert('Canvas element not found.');
            return null;
        }

        const context = this.canvas.getContext('2d');
        this.canvas.width = this.video.videoWidth || 640;
        this.canvas.height = this.video.videoHeight || 480;

        // Draw video frame to canvas
        context.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);
        
        // Show canvas, hide video
        this.video.style.display = 'none';
        this.canvas.style.display = 'block';

        // Convert to base64
        return this.canvas.toDataURL('image/jpeg', 0.9);
    }

    /**
     * Stop webcam stream
     */
    stopWebcam() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        if (this.video) {
            this.video.srcObject = null;
        }
        this.isInitialized = false;
    }

    /**
     * Show face overlay guide
     */
    drawFaceGuide() {
        if (!this.canvas) return;

        const context = this.canvas.getContext('2d');
        const centerX = this.canvas.width / 2;
        const centerY = this.canvas.height / 2;
        const radius = Math.min(this.canvas.width, this.canvas.height) * 0.35;

        // Clear canvas
        context.clearRect(0, 0, this.canvas.width, this.canvas.height);

        // Draw oval face guide
        context.beginPath();
        context.ellipse(centerX, centerY, radius * 0.7, radius, 0, 0, 2 * Math.PI);
        context.strokeStyle = 'rgba(255, 255, 255, 0.8)';
        context.lineWidth = 3;
        context.setLineDash([10, 5]);
        context.stroke();

        // Draw corner markers
        const cornerSize = 20;
        context.setLineDash([]);
        context.lineWidth = 4;
        context.strokeStyle = '#4CAF50';

        // Top-left corner
        context.beginPath();
        context.moveTo(centerX - radius * 0.7 - 30, centerY - radius - 10);
        context.lineTo(centerX - radius * 0.7 - 30, centerY - radius + cornerSize);
        context.stroke();

        context.beginPath();
        context.moveTo(centerX - radius * 0.7 - 30, centerY - radius - 10);
        context.lineTo(centerX - radius * 0.7 - 30 + cornerSize, centerY - radius - 10);
        context.stroke();

        // Top-right corner
        context.beginPath();
        context.moveTo(centerX + radius * 0.7 + 30, centerY - radius - 10);
        context.lineTo(centerX + radius * 0.7 + 30, centerY - radius + cornerSize);
        context.stroke();

        context.beginPath();
        context.moveTo(centerX + radius * 0.7 + 30, centerY - radius - 10);
        context.lineTo(centerX + radius * 0.7 + 30 - cornerSize, centerY - radius - 10);
        context.stroke();

        // Bottom-left corner
        context.beginPath();
        context.moveTo(centerX - radius * 0.7 - 30, centerY + radius + 10);
        context.lineTo(centerX - radius * 0.7 - 30, centerY + radius - cornerSize);
        context.stroke();

        context.beginPath();
        context.moveTo(centerX - radius * 0.7 - 30, centerY + radius + 10);
        context.lineTo(centerX - radius * 0.7 - 30 + cornerSize, centerY + radius + 10);
        context.stroke();

        // Bottom-right corner
        context.beginPath();
        context.moveTo(centerX + radius * 0.7 + 30, centerY + radius + 10);
        context.lineTo(centerX + radius * 0.7 + 30, centerY + radius - cornerSize);
        context.stroke();

        context.beginPath();
        context.moveTo(centerX + radius * 0.7 + 30, centerY + radius + 10);
        context.lineTo(centerX + radius * 0.7 + 30 - cornerSize, centerY + radius + 10);
        context.stroke();

        // Draw instruction text
        context.font = '16px Arial';
        context.fillStyle = 'white';
        context.textAlign = 'center';
        context.shadowColor = 'rgba(0, 0, 0, 0.5)';
        context.shadowBlur = 4;
        context.fillText('Position your face within the oval', centerX, centerY + radius + 40);
        context.fillText('Ensure good lighting', centerX, centerY + radius + 60);
        context.shadowBlur = 0;
    }

    /**
     * Register face for current user
     */
    async registerFace(imageData, onSuccess, onError) {
        try {
            const response = await fetch('/face-auth/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ image: imageData })
            });

            const data = await response.json();

            if (data.success) {
                if (onSuccess) onSuccess(data);
            } else {
                if (onError) onError(data.error || 'Registration failed');
            }

            return data;
        } catch (error) {
            console.error('Error registering face:', error);
            if (onError) onError('Network error. Please try again.');
            return { success: false, error: error.message };
        }
    }

    /**
     * Login with face
     */
    async loginWithFace(imageData, onSuccess, onError) {
        try {
            const response = await fetch('/face-auth/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ image: imageData })
            });

            if (response.redirected) {
                // User was authenticated and redirected
                if (onSuccess) onSuccess({ success: true, redirect: response.url });
                window.location.href = response.url;
                return { success: true, redirect: response.url };
            }

            const data = await response.json();

            if (data.success) {
                if (onSuccess) onSuccess(data);
            } else {
                if (onError) onError(data.error || 'Face verification failed');
            }

            return data;
        } catch (error) {
            console.error('Error verifying face:', error);
            if (onError) onError('Network error. Please try again.');
            return { success: false, error: error.message };
        }
    }

    /**
     * Check if user has face registered
     */
    async checkFaceRegistration() {
        try {
            const response = await fetch('/face-auth/check', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            return await response.json();
        } catch (error) {
            console.error('Error checking face registration:', error);
            return { registered: false, enabled: false };
        }
    }

    /**
     * Toggle face authentication
     */
    async toggleFaceAuth(enabled, onSuccess, onError) {
        try {
            const response = await fetch('/face-auth/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ enabled: enabled })
            });

            const data = await response.json();

            if (data.success) {
                if (onSuccess) onSuccess(data);
            } else {
                if (onError) onError(data.error || 'Failed to toggle face authentication');
            }

            return data;
        } catch (error) {
            console.error('Error toggling face auth:', error);
            if (onError) onError('Network error. Please try again.');
            return { success: false, error: error.message };
        }
    }
}

// Initialize FaceAuth module
window.FaceAuth = new FaceAuth();

/**
 * Initialize face capture modal for registration
 */
function initFaceRegistration() {
    const modal = document.getElementById('faceRegistrationModal');
    if (!modal) return;

    const video = document.getElementById('faceVideo');
    const canvas = document.getElementById('faceCanvas');
    const captureBtn = document.getElementById('captureFaceBtn');

    // Start camera immediately when modal opens
    modal.addEventListener('shown.bs.modal', async () => {
        if (captureBtn) {
            captureBtn.disabled = true;
            captureBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Starting Camera...';
        }
        
        // Small delay to ensure modal is fully visible
        setTimeout(async () => {
            const success = await window.FaceAuth.initWebcam('faceVideo', 'faceCanvas');
            if (success) {
                console.log('Camera ready');
                if (captureBtn) {
                    captureBtn.disabled = false;
                    captureBtn.innerHTML = '<i class="fas fa-camera me-2"></i>Capture Face';
                }
            } else {
                console.error('Camera failed');
                if (captureBtn) {
                    captureBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Camera Failed - Reload Page';
                }
                alert('Could not access camera. Please:\n1. Allow camera permission\n2. Use a modern browser\n3. Ensure no other app is using the camera');
            }
        }, 500);
    });

    // Stop webcam when modal closes
    modal.addEventListener('hidden.bs.modal', () => {
        window.FaceAuth.stopWebcam();
        if (captureBtn) {
            captureBtn.disabled = true;
            captureBtn.innerHTML = '<i class="fas fa-camera me-2"></i>Capture Face';
        }
    });

    // Capture button - for registration, just store image, don't call API
    if (captureBtn) {
        captureBtn.addEventListener('click', async () => {
            if (!window.FaceAuth.isInitialized) {
                alert('Camera is not ready yet. Please wait a moment.');
                return;
            }
            
            const imageData = window.FaceAuth.captureImage();
            if (imageData) {
                const faceInput = document.getElementById('faceImageInput');
                const statusDiv = document.getElementById('faceRegistrationStatus');
                
                if (faceInput) {
                    faceInput.value = imageData;
                }
                
                if (statusDiv) {
                    statusDiv.classList.remove('d-none');
                }
                
                bootstrap.Modal.getInstance(modal).hide();
                window.FaceAuth.stopWebcam();
            }
        });
    }
}

/**
 * Initialize face login
 */
function initFaceLogin() {
    const faceLoginSection = document.getElementById('faceLoginSection');
    if (!faceLoginSection) return;

    const video = document.getElementById('faceLoginVideo');
    const canvas = document.getElementById('faceLoginCanvas');
    const startBtn = document.getElementById('startFaceLogin');
    const captureBtn = document.getElementById('captureFaceLogin');
    const toggleBtn = document.getElementById('toggleFaceLogin');

    let isCapturing = false;

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            faceLoginSection.classList.toggle('d-none');
            if (!faceLoginSection.classList.contains('d-none')) {
                window.FaceAuth.initWebcam('faceLoginVideo', 'faceLoginCanvas');
            } else {
                window.FaceAuth.stopWebcam();
            }
        });
    }

    if (startBtn) {
        startBtn.addEventListener('click', async () => {
            await window.FaceAuth.initWebcam('faceLoginVideo', 'faceLoginCanvas');
            window.FaceAuth.drawFaceGuide();
            startBtn.classList.add('d-none');
            if (captureBtn) captureBtn.classList.remove('d-none');
        });
    }

    if (captureBtn) {
        captureBtn.addEventListener('click', async () => {
            const imageData = window.FaceAuth.captureImage();
            if (imageData) {
                captureBtn.disabled = true;
                captureBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

                await window.FaceAuth.loginWithFace(
                    imageData,
                    (data) => {
                        // Success - user will be redirected
                        console.log('Login successful');
                    },
                    (error) => {
                        alert(error);
                        captureBtn.disabled = false;
                        captureBtn.innerHTML = '<i class="fas fa-fingerprint"></i> Verify Face';
                    }
                );
            }
        });
    }
}

// Initialize when DOM is ready - comment out to let pages handle their own init
// document.addEventListener('DOMContentLoaded', () => {
//     initFaceRegistration();
//     initFaceLogin();
// });
