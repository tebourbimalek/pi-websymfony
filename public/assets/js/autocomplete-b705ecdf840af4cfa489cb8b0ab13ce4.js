class CourseAutocomplete {
    constructor(inputSelector, options = {}) {
        this.input = document.querySelector(inputSelector);
        this.options = {
            minQueryLength: 2,
            debounceTime: 300,
            maxResults: 5,
            apiUrl: '/api/autocomplete/courses',
            ...options
        };
        
        this.suggestionsContainer = null;
        this.currentSuggestions = [];
        this.selectedIndex = -1;
        this.debounceTimer = null;
        this.isLoading = false;
        
        this.init();
    }
    
    init() {
        if (!this.input) return;
        
        // Create suggestions container
        this.createSuggestionsContainer();
        
        // Event listeners
        this.input.addEventListener('input', (e) => this.handleInput(e));
        this.input.addEventListener('focus', () => this.showSuggestions());
        this.input.addEventListener('blur', () => this.hideSuggestions());
        this.input.addEventListener('keydown', (e) => this.handleKeydown(e));
        
        // Close on click outside
        document.addEventListener('click', (e) => {
            if (!this.input.contains(e.target) && !this.suggestionsContainer.contains(e.target)) {
                this.hideSuggestions();
            }
        });
    }
    
    createSuggestionsContainer() {
        this.suggestionsContainer = document.createElement('div');
        this.suggestionsContainer.className = 'autocomplete-suggestions';
        this.suggestionsContainer.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        `;
        
        // Position the container relative to input
        this.input.parentElement.style.position = 'relative';
        this.input.parentElement.appendChild(this.suggestionsContainer);
    }
    
    handleInput(e) {
        const query = e.target.value.trim();
        
        // Clear previous timer
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        
        if (query.length < this.options.minQueryLength) {
            this.hideSuggestions();
            return;
        }
        
        // Debounce the search
        this.debounceTimer = setTimeout(() => {
            this.fetchSuggestions(query);
        }, this.options.debounceTime);
    }
    
    async fetchSuggestions(query) {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoading();
        
        try {
            const url = `${this.options.apiUrl}?q=${encodeURIComponent(query)}&limit=${this.options.maxResults}`;
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                this.currentSuggestions = data.suggestions;
                this.displaySuggestions();
            } else {
                this.hideSuggestions();
            }
        } catch (error) {
            console.error('Autocomplete error:', error);
            this.hideSuggestions();
        } finally {
            this.isLoading = false;
        }
    }
    
    displaySuggestions() {
        if (this.currentSuggestions.length === 0) {
            this.showNoResults();
            return;
        }
        
        const html = this.currentSuggestions.map((suggestion, index) => `
            <div class="autocomplete-suggestion ${index === this.selectedIndex ? 'selected' : ''}" 
                 data-index="${index}">
                <div class="suggestion-content">
                    ${suggestion.thumbnailUrl ? `
                        <img src="${suggestion.thumbnailUrl}" alt="${suggestion.title}" class="suggestion-thumbnail">
                    ` : ''}
                    <div class="suggestion-details">
                        <div class="suggestion-title">${this.highlightMatch(suggestion.title)}</div>
                        <div class="suggestion-description">${this.highlightMatch(suggestion.description)}</div>
                        <div class="suggestion-meta">
                            <span class="suggestion-level">${suggestion.level}</span>
                            <span class="suggestion-price">$${suggestion.price}</span>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
        
        this.suggestionsContainer.innerHTML = html;
        this.attachSuggestionEvents();
        this.showSuggestions();
    }
    
    highlightMatch(text) {
        const query = this.input.value.trim();
        if (!query) return text;
        
        const regex = new RegExp(`(${this.escapeRegex(query)})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }
    
    escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    showLoading() {
        this.suggestionsContainer.innerHTML = `
            <div class="autocomplete-loading">
                <div class="spinner"></div>
                <span>Searching...</span>
            </div>
        `;
        this.showSuggestions();
    }
    
    showNoResults() {
        this.suggestionsContainer.innerHTML = `
            <div class="autocomplete-no-results">
                <div class="no-results-icon">🔍</div>
                <div class="no-results-text">No courses found</div>
            </div>
        `;
        this.showSuggestions();
    }
    
    attachSuggestionEvents() {
        const suggestions = this.suggestionsContainer.querySelectorAll('.autocomplete-suggestion');
        suggestions.forEach(suggestion => {
            suggestion.addEventListener('click', () => {
                const index = parseInt(suggestion.dataset.index);
                this.selectSuggestion(index);
            });
            
            suggestion.addEventListener('mouseenter', () => {
                this.selectedIndex = parseInt(suggestion.dataset.index);
                this.updateSelectedClass();
            });
        });
    }
    
    updateSelectedClass() {
        const suggestions = this.suggestionsContainer.querySelectorAll('.autocomplete-suggestion');
        suggestions.forEach((suggestion, index) => {
            if (index === this.selectedIndex) {
                suggestion.classList.add('selected');
            } else {
                suggestion.classList.remove('selected');
            }
        });
    }
    
    handleKeydown(e) {
        if (!this.suggestionsContainer.style.display || this.suggestionsContainer.style.display === 'none') {
            return;
        }
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, this.currentSuggestions.length - 1);
                this.updateSelectedClass();
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                this.updateSelectedClass();
                break;
                
            case 'Enter':
                e.preventDefault();
                if (this.selectedIndex >= 0) {
                    this.selectSuggestion(this.selectedIndex);
                }
                break;
                
            case 'Escape':
                this.hideSuggestions();
                break;
        }
    }
    
    selectSuggestion(index) {
        const suggestion = this.currentSuggestions[index];
        if (suggestion) {
            this.input.value = suggestion.title;
            this.hideSuggestions();
            // Navigate to course page
            window.location.href = suggestion.url;
        }
    }
    
    showSuggestions() {
        this.suggestionsContainer.style.display = 'block';
    }
    
    hideSuggestions() {
        this.suggestionsContainer.style.display = 'none';
        this.selectedIndex = -1;
    }
}

// Initialize autocomplete
document.addEventListener('DOMContentLoaded', () => {
    new CourseAutocomplete('input[name="search"]', {
        minQueryLength: 2,
        debounceTime: 300,
        maxResults: 5
    });
});
