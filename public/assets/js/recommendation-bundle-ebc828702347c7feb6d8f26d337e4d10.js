/**
 * Recently Viewed / Recommended Products Bundle
 * Personalized product recommendations with carousel UI
 */
class RecommendationBundle {
    constructor(options = {}) {
        this.options = {
            recentlyViewedContainer: options.recentlyViewedContainer || '#recently-viewed-container',
            recommendedContainer: options.recommendedContainer || '#recommended-container',
            alsoBoughtContainer: options.alsoBoughtContainer || '#also-bought-container',
            apiEndpoints: {
                recentlyViewed: '/api/products/recently-viewed',
                recommended: '/api/products/recommended',
                trackView: '/api/products/track-view',
                alsoBought: '/api/products/also-bought'
            },
            userId: options.userId || null,
            limit: options.limit || 5,
            autoTrack: options.autoTrack !== false,
            showTitles: options.showTitles !== false,
            carouselOptions: {
                items: 1,
                slideBy: 1,
                nav: true,
                dots: false,
                autoplay: false,
                responsive: {
                    0: { items: 1 },
                    576: { items: 2 },
                    768: { items: 3 },
                    992: { items: 4 },
                    1200: { items: 5 }
                }
            },
            ...options
        };
        
        this.init();
    }

    init() {
        this.loadRecommendations();
        this.setupAutoTracking();
        this.setupEventListeners();
    }

    async loadRecommendations() {
        try {
            // Load recently viewed products
            await this.loadRecentlyViewed();
            
            // Load recommended products
            await this.loadRecommended();
            
        } catch (error) {
            console.error('Error loading recommendations:', error);
            this.showError('Failed to load recommendations');
        }
    }

    async loadRecentlyViewed() {
        const container = document.querySelector(this.options.recentlyViewedContainer);
        if (!container) return;

        try {
            const params = new URLSearchParams({
                limit: this.options.limit,
                ...(this.options.userId && { user_id: this.options.userId })
            });

            const response = await fetch(`${this.options.apiEndpoints.recentlyViewed}?${params}`);
            const data = await response.json();

            if (data.success) {
                this.renderRecentlyViewed(data.data, container);
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            console.error('Error loading recently viewed:', error);
            container.innerHTML = this.getErrorHTML('Recently Viewed');
        }
    }

    async loadRecommended() {
        const container = document.querySelector(this.options.recommendedContainer);
        if (!container) return;

        try {
            const params = new URLSearchParams({
                limit: this.options.limit,
                ...(this.options.userId && { user_id: this.options.userId })
            });

            const response = await fetch(`${this.options.apiEndpoints.recommended}?${params}`);
            const data = await response.json();

            if (data.success) {
                this.renderRecommended(data.data, container);
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            console.error('Error loading recommended:', error);
            container.innerHTML = this.getErrorHTML('Recommended for you');
        }
    }

    async loadAlsoBought(productId) {
        const container = document.querySelector(this.options.alsoBoughtContainer);
        if (!container) return;

        try {
            const params = new URLSearchParams({
                limit: this.options.limit
            });

            const response = await fetch(`${this.options.apiEndpoints.alsoBought}/${productId}?${params}`);
            const data = await response.json();

            if (data.success) {
                this.renderAlsoBought(data.data, container, data.meta);
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            console.error('Error loading also bought:', error);
            container.innerHTML = this.getErrorHTML('People also bought');
        }
    }

    renderRecentlyViewed(products, container) {
        if (products.length === 0) {
            container.innerHTML = this.getEmptyHTML('No recently viewed products');
            return;
        }

        const html = `
            ${this.options.showTitles ? '<h3 class="recommendation-title">🛍 Recently Viewed</h3>' : ''}
            <div class="recommendation-carousel" data-carousel="recently-viewed">
                ${products.map(product => this.getProductCard(product)).join('')}
            </div>
        `;

        container.innerHTML = html;
        this.initializeCarousel(container.querySelector('[data-carousel="recently-viewed"]'));
    }

    renderRecommended(products, container) {
        if (products.length === 0) {
            container.innerHTML = this.getEmptyHTML('No recommendations available');
            return;
        }

        const html = `
            ${this.options.showTitles ? '<h3 class="recommendation-title">🛍 Recommended for you</h3>' : ''}
            <div class="recommendation-carousel" data-carousel="recommended">
                ${products.map(product => this.getProductCard(product)).join('')}
            </div>
        `;

        container.innerHTML = html;
        this.initializeCarousel(container.querySelector('[data-carousel="recommended"]'));
    }

    renderAlsoBought(products, container, meta) {
        if (products.length === 0) {
            container.innerHTML = this.getEmptyHTML('No related products found');
            return;
        }

        const html = `
            ${this.options.showTitles ? `<h3 class="recommendation-title">🛍 People also bought this</h3>` : ''}
            <div class="recommendation-carousel" data-carousel="also-bought">
                ${products.map(product => this.getProductCard(product)).join('')}
            </div>
        `;

        container.innerHTML = html;
        this.initializeCarousel(container.querySelector('[data-carousel="also-bought"]'));
    }

    getProductCard(product) {
        return `
            <div class="recommendation-card">
                <div class="recommendation-image">
                    ${product.image ? 
                        `<img src="${product.image}" alt="${product.title}" loading="lazy">` : 
                        `<div class="no-image-placeholder">
                            <i class="fas fa-image"></i>
                        </div>`
                    }
                </div>
                <div class="recommendation-content">
                    <h4 class="recommendation-product-title">${this.truncateText(product.title, 50)}</h4>
                    <p class="recommendation-description">${this.truncateText(product.description, 80)}</p>
                    <div class="recommendation-meta">
                        <span class="recommendation-category">${product.category}</span>
                        <div class="recommendation-rating">
                            <i class="fas fa-star"></i>
                            <span>${product.rating}</span>
                        </div>
                    </div>
                    <div class="recommendation-footer">
                        <span class="recommendation-price">$${product.price}</span>
                        <a href="/product/${product.slug}" class="btn btn-primary btn-sm">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
        `;
    }

    initializeCarousel(element) {
        if (!element) return;

        // Simple carousel implementation
        const cards = element.querySelectorAll('.recommendation-card');
        if (cards.length === 0) return;

        let currentIndex = 0;
        const visibleCards = this.getVisibleCards();

        // Create navigation buttons
        const navHTML = `
            <button class="carousel-nav carousel-prev" data-direction="prev">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="carousel-nav carousel-next" data-direction="next">
                <i class="fas fa-chevron-right"></i>
            </button>
        `;

        element.style.position = 'relative';
        element.insertAdjacentHTML('beforeend', navHTML);

        // Setup navigation
        const prevBtn = element.querySelector('.carousel-prev');
        const nextBtn = element.querySelector('.carousel-next');

        prevBtn.addEventListener('click', () => this.navigateCarousel(element, -1));
        nextBtn.addEventListener('click', () => this.navigateCarousel(element, 1));

        // Update navigation state
        this.updateCarouselNavigation(element, currentIndex, visibleCards);
    }

    navigateCarousel(element, direction) {
        const cards = element.querySelectorAll('.recommendation-card');
        const visibleCards = this.getVisibleCards();
        const currentIndex = parseInt(element.dataset.currentIndex || 0);
        
        let newIndex = currentIndex + direction;
        newIndex = Math.max(0, Math.min(newIndex, cards.length - visibleCards));
        
        element.dataset.currentIndex = newIndex;
        
        // Scroll to new position
        const cardWidth = cards[0].offsetWidth + 16; // Include gap
        element.scrollLeft = newIndex * cardWidth;
        
        this.updateCarouselNavigation(element, newIndex, visibleCards);
    }

    updateCarouselNavigation(element, currentIndex, visibleCards) {
        const cards = element.querySelectorAll('.recommendation-card');
        const prevBtn = element.querySelector('.carousel-prev');
        const nextBtn = element.querySelector('.carousel-next');
        
        if (prevBtn) {
            prevBtn.disabled = currentIndex === 0;
        }
        
        if (nextBtn) {
            nextBtn.disabled = currentIndex >= cards.length - visibleCards;
        }
    }

    getVisibleCards() {
        const width = window.innerWidth;
        if (width < 576) return 1;
        if (width < 768) return 2;
        if (width < 992) return 3;
        if (width < 1200) return 4;
        return 5;
    }

    setupAutoTracking() {
        if (!this.options.autoTrack) return;

        // Track product views when user visits product pages
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    this.checkForProductPage();
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Initial check
        this.checkForProductPage();
    }

    checkForProductPage() {
        // Check if we're on a product page
        const productElement = document.querySelector('[data-product-id]');
        if (productElement) {
            const productId = productElement.dataset.productId;
            this.trackProductView(productId);
        }
    }

    async trackProductView(productId) {
        try {
            await fetch(this.options.apiEndpoints.trackView, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    user_id: this.options.userId
                })
            });
        } catch (error) {
            console.error('Error tracking product view:', error);
        }
    }

    setupEventListeners() {
        // Handle window resize for carousel
        window.addEventListener('resize', () => {
            document.querySelectorAll('.recommendation-carousel').forEach(carousel => {
                const currentIndex = parseInt(carousel.dataset.currentIndex || 0);
                this.updateCarouselNavigation(carousel, currentIndex, this.getVisibleCards());
            });
        });
    }

    truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }

    getEmptyHTML(message) {
        return `
            <div class="recommendation-empty">
                <i class="fas fa-box-open fa-2x text-muted mb-3"></i>
                <p class="text-muted">${message}</p>
            </div>
        `;
    }

    getErrorHTML(title) {
        return `
            <div class="recommendation-error">
                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-3"></i>
                <p class="text-muted">Failed to load ${title}</p>
                <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                    <i class="fas fa-redo me-1"></i>Retry
                </button>
            </div>
        `;
    }

    showError(message) {
        // Show error notification
        const notification = document.createElement('div');
        notification.className = 'alert alert-warning alert-dismissible fade show position-fixed top-0 end-0 m-3';
        notification.style.zIndex = '9999';
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${message}
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 5000);
    }

    // Public methods
    refresh() {
        this.loadRecommendations();
    }

    setUserId(userId) {
        this.options.userId = userId;
        this.refresh();
    }

    loadAlsoBoughtForProduct(productId) {
        this.loadAlsoBought(productId);
    }
}

// CSS for recommendation bundle
const recommendationCSS = `
.recommendation-container {
    margin: 2rem 0;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

.recommendation-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.recommendation-carousel {
    position: relative;
    overflow-x: auto;
    scroll-behavior: smooth;
    scrollbar-width: thin;
    scrollbar-color: #dee2e6 #f8f9fa;
    padding: 0.5rem 0;
}

.recommendation-carousel::-webkit-scrollbar {
    height: 8px;
}

.recommendation-carousel::-webkit-scrollbar-track {
    background: #f8f9fa;
}

.recommendation-carousel::-webkit-scrollbar-thumb {
    background: #dee2e6;
    border-radius: 4px;
}

.recommendation-cards-wrapper {
    display: flex;
    gap: 1rem;
    transition: transform 0.3s ease;
}

.recommendation-card {
    flex: 0 0 auto;
    width: 280px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    overflow: hidden;
}

.recommendation-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.recommendation-image {
    width: 100%;
    height: 160px;
    overflow: hidden;
    background: #f8f9fa;
}

.recommendation-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.recommendation-card:hover .recommendation-image img {
    transform: scale(1.05);
}

.no-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    font-size: 2rem;
}

.recommendation-content {
    padding: 1rem;
}

.recommendation-product-title {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #212529;
    line-height: 1.3;
}

.recommendation-description {
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 0.75rem;
    line-height: 1.4;
}

.recommendation-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.recommendation-category {
    font-size: 0.75rem;
    background: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-weight: 500;
}

.recommendation-rating {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: #ffc107;
}

.recommendation-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.recommendation-price {
    font-size: 1.1rem;
    font-weight: 700;
    color: #28a745;
}

.carousel-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 10;
}

.carousel-nav:hover {
    background: #f8f9fa;
    border-color: #adb5bd;
}

.carousel-nav:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.carousel-prev {
    left: 10px;
}

.carousel-next {
    right: 10px;
}

.recommendation-empty,
.recommendation-error {
    text-align: center;
    padding: 2rem;
}

.recommendation-empty i,
.recommendation-error i {
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .recommendation-card {
        width: 240px;
    }
    
    .recommendation-container {
        padding: 1rem;
    }
    
    .carousel-nav {
        width: 35px;
        height: 35px;
    }
}

@media (max-width: 576px) {
    .recommendation-card {
        width: 200px;
    }
    
    .recommendation-content {
        padding: 0.75rem;
    }
    
    .recommendation-product-title {
        font-size: 0.85rem;
    }
    
    .recommendation-description {
        font-size: 0.75rem;
    }
}
`;

// Auto-inject CSS
if (!document.querySelector('#recommendation-styles')) {
    const style = document.createElement('style');
    style.id = 'recommendation-styles';
    style.textContent = recommendationCSS;
    document.head.appendChild(style);
}
