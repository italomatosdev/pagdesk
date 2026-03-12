/**
 * Busca Global Inteligente
 */
(function() {
    'use strict';

    let searchTimeout;
    const searchInput = document.getElementById('global-search-input');
    const searchResults = document.getElementById('search-results');
    const searchLoading = document.getElementById('search-loading');
    const searchDropdown = document.getElementById('search-dropdown');

    if (!searchInput || !searchResults) {
        return;
    }

    // Ícones por tipo
    const icons = {
        'cliente': 'bx-user',
        'emprestimo': 'bx-money',
        'operacao': 'bx-building',
        'usuario': 'bx-user-circle'
    };

    // Cores de badge por tipo
    const badgeColors = {
        'cliente': 'bg-info',
        'emprestimo': 'bg-primary',
        'operacao': 'bg-success',
        'usuario': 'bg-warning'
    };

    // Status colors para empréstimos
    const statusColors = {
        'pendente': 'bg-warning',
        'aprovado': 'bg-info',
        'ativo': 'bg-success',
        'rejeitado': 'bg-danger',
        'finalizado': 'bg-secondary'
    };

    /**
     * Fazer busca AJAX
     */
    function performSearch(termo) {
        if (termo.length < 2) {
            showEmptyState('Digite pelo menos 2 caracteres...');
            return;
        }

        searchLoading.style.display = 'block';
        searchResults.innerHTML = '';

        fetch(`/api/search?q=${encodeURIComponent(termo)}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            searchLoading.style.display = 'none';
            
            if (data.results && data.results.length > 0) {
                displayResults(data.results);
            } else {
                showEmptyState('Nenhum resultado encontrado');
            }
        })
        .catch(error => {
            console.error('Erro na busca:', error);
            searchLoading.style.display = 'none';
            showEmptyState('Erro ao buscar. Tente novamente.');
        });
    }

    /**
     * Exibir resultados
     */
    function displayResults(results) {
        let html = '<div class="list-group list-group-flush">';
        
        results.forEach(result => {
            const icon = icons[result.type] || 'bx-file';
            const badgeClass = result.type === 'emprestimo' && statusColors[result.badge.toLowerCase()] 
                ? statusColors[result.badge.toLowerCase()] 
                : badgeColors[result.type] || 'bg-secondary';
            
            html += `
                <a href="${result.url}" class="list-group-item list-group-item-action search-result-item">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <i class="bx ${icon} font-size-20 text-primary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${escapeHtml(result.title)}</h6>
                            <small class="text-muted">${escapeHtml(result.subtitle)}</small>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="badge ${badgeClass}">${escapeHtml(result.badge)}</span>
                        </div>
                    </div>
                </a>
            `;
        });
        
        html += '</div>';
        searchResults.innerHTML = html;
    }

    /**
     * Exibir estado vazio
     */
    function showEmptyState(message) {
        searchResults.innerHTML = `
            <div class="text-center p-3 text-muted">
                <i class="bx bx-search font-size-24 mb-2"></i>
                <p class="mb-0">${escapeHtml(message)}</p>
            </div>
        `;
    }

    /**
     * Escapar HTML para prevenir XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Event listener para input
     */
    searchInput.addEventListener('input', function(e) {
        const termo = e.target.value.trim();
        
        // Limpar timeout anterior
        clearTimeout(searchTimeout);
        
        // Se vazio, mostrar estado inicial
        if (termo.length === 0) {
            showEmptyState('Digite para buscar...');
            return;
        }
        
        // Debounce: aguardar 300ms antes de buscar
        searchTimeout = setTimeout(() => {
            performSearch(termo);
        }, 300);
    });

    /**
     * Fechar dropdown ao clicar fora
     */
    document.addEventListener('click', function(e) {
        if (!searchDropdown.contains(e.target) && e.target.id !== 'search-toggle') {
            const bsDropdown = bootstrap.Dropdown.getInstance(searchDropdown);
            if (bsDropdown) {
                bsDropdown.hide();
            }
        }
    });

    /**
     * Limpar busca ao fechar dropdown
     */
    searchDropdown.addEventListener('hidden.bs.dropdown', function() {
        searchInput.value = '';
        showEmptyState('Digite para buscar...');
    });

    /**
     * Focar no input ao abrir dropdown
     */
    searchDropdown.addEventListener('shown.bs.dropdown', function() {
        searchInput.focus();
    });

    /**
     * Navegação por teclado
     */
    searchInput.addEventListener('keydown', function(e) {
        const items = searchResults.querySelectorAll('.search-result-item');
        
        if (items.length === 0) {
            return;
        }
        
        let currentIndex = -1;
        items.forEach((item, index) => {
            if (item.classList.contains('active')) {
                currentIndex = index;
            }
        });
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            items.forEach(item => item.classList.remove('active'));
            const nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
            items[nextIndex].classList.add('active');
            items[nextIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            items.forEach(item => item.classList.remove('active'));
            const prevIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
            items[prevIndex].classList.add('active');
            items[prevIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const activeItem = searchResults.querySelector('.search-result-item.active');
            if (activeItem) {
                window.location.href = activeItem.href;
            } else if (items.length > 0) {
                window.location.href = items[0].href;
            }
        }
    });
})();
