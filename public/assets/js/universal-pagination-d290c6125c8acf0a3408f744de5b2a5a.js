/**
 * Universal Pagination Component
 * Works across all marketplace pages including admin interface
 */
class UniversalPagination {
    constructor(options = {}) {
        this.options = {
            container: options.container || '#pagination-container',
            apiEndpoint: options.apiEndpoint || '/api/products',
            currentPage: options.currentPage || 1,
            limit: options.limit || 10,
            onPageChange: options.onPageChange || (() => {}),
            showInfo: options.showInfo !== false,
            showJumpTo: options.showJumpTo !== false,
            maxVisiblePages: options.maxVisiblePages || 5,
            ...options
        };
        
        this.currentPage = this.options.currentPage;
        this.totalPages = 1;
        this.total = 0;
        this.loading = false;
        
        this.init();
    }

    init() {
        this.createPaginationHTML();
        this.bindEvents();
        this.loadPage(this.currentPage);
    }

    createPaginationHTML() {
        const container = document.querySelector(this.options.container);
        if (!container) return;

        container.innerHTML = `
            <div class="universal-pagination">
                <!-- Loading State -->
                <div class="pagination-loading" style="display: none;">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span class="ms-2">Loading...</span>
                </div>

                <!-- Pagination Info -->
                <div class="pagination-info" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div class="pagination-summary">
                            <span class="text-muted">
                                Showing <span class="from">0</span> to <span class="to">0</span> 
                                of <span class="total">0</span> results
                            </span>
                        </div>
                        <div class="pagination-controls">
                            <div class="btn-group me-2">
                                <button class="btn btn-outline-primary btn-sm pagination-prev" disabled>
                                    <i class="fas fa-chevron-left"></i> Previous
                                </button>
                                <button class="btn btn-outline-primary btn-sm pagination-next" disabled>
                                    Next <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                            <div class="pagination-pages">
                                <!-- Page numbers will be inserted here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Jump to Page -->
                <div class="pagination-jump" style="display: none;">
                    <div class="d-flex align-items-center">
                        <label class="form-label me-2 mb-0">Jump to page:</label>
                        <input type="number" class="form-control form-control-sm me-2" 
                               min="1" max="1" value="1" style="width: 80px;">
                        <button class="btn btn-primary btn-sm pagination-jump-btn">Go</button>
                    </div>
                </div>
            </div>
        `;
    }

    bindEvents() {
        const container = document.querySelector(this.options.container);
        if (!container) return;

        // Previous button
        container.querySelector('.pagination-prev').addEventListener('click', () => {
            if (this.currentPage > 1) {
                this.loadPage(this.currentPage - 1);
            }
        });

        // Next button
        container.querySelector('.pagination-next').addEventListener('click', () => {
            if (this.currentPage < this.totalPages) {
                this.loadPage(this.currentPage + 1);
            }
        });

        // Jump to page
        const jumpInput = container.querySelector('.pagination-jump input');
        const jumpBtn = container.querySelector('.pagination-jump-btn');
        
        if (jumpBtn) {
            jumpBtn.addEventListener('click', () => {
                const page = parseInt(jumpInput.value);
                if (page >= 1 && page <= this.totalPages) {
                    this.loadPage(page);
                }
            });
        }

        if (jumpInput) {
            jumpInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    const page = parseInt(jumpInput.value);
                    if (page >= 1 && page <= this.totalPages) {
                        this.loadPage(page);
                    }
                }
            });
        }
    }

    async loadPage(page) {
        if (this.loading) return;

        this.currentPage = page;
        this.setLoading(true);

        try {
            const params = new URLSearchParams({
                page: page,
                limit: this.options.limit,
                ...this.options.filters
            });

            const response = await fetch(`${this.options.apiEndpoint}?${params}`);
            const data = await response.json();

            if (data.success) {
                this.total = data.pagination.total;
                this.totalPages = data.pagination.total_pages;
                this.updatePaginationUI(data.pagination);
                this.options.onPageChange(data.data, data.pagination);
            } else {
                throw new Error(data.error || 'Failed to load data');
            }
        } catch (error) {
            console.error('Pagination error:', error);
            this.showError(error.message);
        } finally {
            this.setLoading(false);
        }
    }

    updatePaginationUI(pagination) {
        const container = document.querySelector(this.options.container);
        if (!container) return;

        // Update info
        if (this.options.showInfo) {
            const infoEl = container.querySelector('.pagination-info');
            const fromEl = container.querySelector('.from');
            const toEl = container.querySelector('.to');
            const totalEl = container.querySelector('.total');
            
            infoEl.style.display = 'block';
            fromEl.textContent = pagination.from || 0;
            toEl.textContent = pagination.to || 0;
            totalEl.textContent = pagination.total || 0;
        }

        // Update jump to page
        if (this.options.showJumpTo) {
            const jumpEl = container.querySelector('.pagination-jump');
            const jumpInput = container.querySelector('.pagination-jump input');
            
            jumpEl.style.display = 'block';
            jumpInput.max = this.totalPages;
            jumpInput.value = this.currentPage;
        }

        // Update buttons
        const prevBtn = container.querySelector('.pagination-prev');
        const nextBtn = container.querySelector('.pagination-next');
        
        prevBtn.disabled = !pagination.has_previous_page;
        nextBtn.disabled = !pagination.has_next_page;

        // Update page numbers
        this.updatePageNumbers();
    }

    updatePageNumbers() {
        const container = document.querySelector(this.options.container);
        const pagesContainer = container.querySelector('.pagination-pages');
        if (!pagesContainer) return;

        const maxVisible = this.options.maxVisiblePages;
        let startPage = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(this.totalPages, startPage + maxVisible - 1);

        // Adjust start if we're near the end
        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        let pageNumbers = '';
        
        // Add first page and ellipsis if needed
        if (startPage > 1) {
            pageNumbers += `
                <button class="btn btn-outline-primary btn-sm page-btn" data-page="1">1</button>
                ${startPage > 2 ? '<span class="mx-2">...</span>' : ''}
            `;
        }

        // Add visible pages
        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === this.currentPage;
            pageNumbers += `
                <button class="btn ${isActive ? 'btn-primary' : 'btn-outline-primary'} btn-sm page-btn" 
                        data-page="${i}" ${isActive ? 'disabled' : ''}>
                    ${i}
                </button>
            `;
        }

        // Add ellipsis and last page if needed
        if (endPage < this.totalPages) {
            pageNumbers += `
                ${endPage < this.totalPages - 1 ? '<span class="mx-2">...</span>' : ''}
                <button class="btn btn-outline-primary btn-sm page-btn" data-page="${this.totalPages}">
                    ${this.totalPages}
                </button>
            `;
        }

        pagesContainer.innerHTML = pageNumbers;

        // Bind click events to page buttons
        pagesContainer.querySelectorAll('.page-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const page = parseInt(btn.dataset.page);
                if (page && page !== this.currentPage) {
                    this.loadPage(page);
                }
            });
        });
    }

    setLoading(loading) {
        this.loading = loading;
        const container = document.querySelector(this.options.container);
        if (!container) return;

        const loadingEl = container.querySelector('.pagination-loading');
        const infoEl = container.querySelector('.pagination-info');
        const jumpEl = container.querySelector('.pagination-jump');

        if (loading) {
            loadingEl.style.display = 'flex';
            infoEl.style.display = 'none';
            jumpEl.style.display = 'none';
        } else {
            loadingEl.style.display = 'none';
        }
    }

    showError(message) {
        const container = document.querySelector(this.options.container);
        if (!container) return;

        const errorHTML = `
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Error: ${message}
            </div>
        `;

        container.innerHTML = errorHTML;
    }

    // Public methods
    refresh() {
        this.loadPage(this.currentPage);
    }

    setLimit(limit) {
        this.options.limit = limit;
        this.loadPage(1);
    }

    setFilters(filters) {
        this.options.filters = { ...this.options.filters, ...filters };
        this.loadPage(1);
    }
}

// CSS for pagination
const paginationCSS = `
.universal-pagination {
    margin: 2rem 0;
}

.pagination-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.pagination-info {
    margin-bottom: 1rem;
}

.pagination-summary {
    font-size: 0.9rem;
    color: #6c757d;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.pagination-pages {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.pagination-jump {
    margin-top: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.page-btn {
    min-width: 40px;
}

@media (max-width: 768px) {
    .pagination-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    
    .pagination-pages {
        justify-content: center;
        flex-wrap: wrap;
    }
}
`;

// Auto-inject CSS
if (!document.querySelector('#pagination-styles')) {
    const style = document.createElement('style');
    style.id = 'pagination-styles';
    style.textContent = paginationCSS;
    document.head.appendChild(style);
}
