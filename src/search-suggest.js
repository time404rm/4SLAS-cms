// Автодополнение для поля поиска
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('.search-form input[name="q"]');
    if (!searchInput) return;
    
    let suggestionsContainer = null;
    let currentRequest = null;
    
    // Создаём контейнер для подсказок (стили вынесены в CSS)
    const createContainer = () => {
        const container = document.createElement('div');
        container.className = 'search-suggestions';
        searchInput.parentNode.style.position = 'relative';
        searchInput.parentNode.appendChild(container);
        return container;
    };
    
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        if (query.length < 2) {
            if (suggestionsContainer) suggestionsContainer.style.display = 'none';
            return;
        }
        
        if (currentRequest) currentRequest.abort();
        
        currentRequest = new XMLHttpRequest();
        currentRequest.open('GET', '/api/search_suggest.php?q=' + encodeURIComponent(query));
        currentRequest.onload = function() {
            if (this.status === 200) {
                const data = JSON.parse(this.responseText);
                if (!suggestionsContainer) suggestionsContainer = createContainer();
                showSuggestions(data);
            }
        };
        currentRequest.send();
    });
    
    function showSuggestions(items) {
        if (!suggestionsContainer) return;
        if (items.length === 0) {
            suggestionsContainer.style.display = 'none';
            return;
        }
        suggestionsContainer.innerHTML = '';
        items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'suggestion-item';
            div.textContent = item.text;
            div.addEventListener('click', () => {
                if (item.url) {
                    window.location.href = item.url;
                } else {
                    searchInput.value = item.text;
                    searchInput.closest('form').submit();
                }
                suggestionsContainer.style.display = 'none';
            });
            suggestionsContainer.appendChild(div);
        });
        suggestionsContainer.style.display = 'block';
    }
    
    document.addEventListener('click', function(e) {
        if (suggestionsContainer && !suggestionsContainer.contains(e.target) && e.target !== searchInput) {
            suggestionsContainer.style.display = 'none';
        }
    });
});