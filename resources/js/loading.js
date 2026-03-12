/**
 * Sistema de Loading Global - PagDesk
 * Funções reutilizáveis para exibir loadings em todo o sistema
 */

// Overlay global de loading
const LoadingOverlay = {
    overlay: null,
    
    init() {
        if (!this.overlay) {
            this.overlay = document.createElement('div');
            this.overlay.id = 'global-loading-overlay';
            this.overlay.className = 'loading-overlay d-none';
            this.overlay.innerHTML = `
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <p id="loading-message">Processando...</p>
            `;
            document.body.appendChild(this.overlay);
        }
    },
    
    show(message = 'Processando...') {
        this.init();
        const messageEl = this.overlay.querySelector('#loading-message');
        if (messageEl) {
            messageEl.textContent = message;
        }
        this.overlay.classList.remove('d-none');
    },
    
    hide() {
        if (this.overlay) {
            this.overlay.classList.add('d-none');
        }
    }
};

// Loading em botões
const ButtonLoading = {
    show(button, text = 'Processando...') {
        if (!button) return;
        
        button.disabled = true;
        button.classList.add('btn-loading');
        
        const originalHTML = button.innerHTML;
        button.dataset.originalHtml = originalHTML;
        
        button.innerHTML = `
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span>${text}</span>
        `;
    },
    
    hide(button) {
        if (!button) return;
        
        button.disabled = false;
        button.classList.remove('btn-loading');
        
        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
    }
};

// Loading em campos de input
const FieldLoading = {
    show(input) {
        if (!input) return;
        input.classList.add('field-loading');
        input.disabled = true;
    },
    
    hide(input) {
        if (!input) return;
        input.classList.remove('field-loading');
        input.disabled = false;
    }
};

// Loading em formulários
const FormLoading = {
    show(form, message = 'Salvando...') {
        if (!form) return;
        
        // NÃO desabilitar campos - apenas mostrar overlay
        // Desabilitar campos impede que sejam enviados no POST
        // Apenas desabilitar o botão de submit para evitar duplo submit
        const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitButton) {
            if (!submitButton.dataset.wasDisabled) {
                submitButton.dataset.wasDisabled = submitButton.disabled ? 'true' : 'false';
            }
            submitButton.disabled = true;
        }
        
        // Mostrar overlay
        LoadingOverlay.show(message);
    },
    
    hide(form) {
        if (!form) return;
        
        // Reabilitar botão de submit
        const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitButton) {
            const wasDisabled = submitButton.dataset.wasDisabled === 'true';
            submitButton.disabled = wasDisabled;
            delete submitButton.dataset.wasDisabled;
        }
        
        // Esconder overlay
        LoadingOverlay.hide();
    }
};

// Loading em tabelas/áreas de conteúdo
const ContentLoading = {
    show(container, message = 'Carregando...') {
        if (!container) return;
        
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'text-center py-5';
        loadingDiv.id = 'content-loading';
        loadingDiv.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-3 text-muted">${message}</p>
        `;
        
        container.style.position = 'relative';
        container.style.minHeight = '200px';
        container.innerHTML = '';
        container.appendChild(loadingDiv);
    },
    
    hide(container) {
        if (!container) return;
        const loadingDiv = container.querySelector('#content-loading');
        if (loadingDiv) {
            loadingDiv.remove();
        }
    }
};

// Auto-aplicar loading em formulários
document.addEventListener('DOMContentLoaded', function() {
    // Garantir que o overlay esteja escondido ao carregar a página
    LoadingOverlay.hide();
    // Interceptar submit de formulários
    // Usar capture: false para que listeners específicos do formulário executem primeiro
    document.addEventListener('submit', function(e) {
        const form = e.target;
        
        // Verificar se é um formulário que deve ter loading
        if (form.tagName === 'FORM' && !form.dataset.noLoading) {
            // Verificar se o formulário tem um listener customizado que pode fazer preventDefault
            // Se o evento já foi preventDefaulted, não mostrar loading
            if (e.defaultPrevented) {
                return;
            }
            
            // Ignorar formulários GET (filtros de busca) - são navegações normais
            const method = form.method?.toUpperCase() || 'POST';
            if (method === 'GET') {
                return; // Não aplicar loading em formulários GET (filtros de busca)
            }
            
            // Ignorar formulários de autenticação (login, register, etc)
            const action = form.action || '';
            if (action.includes('/login') || action.includes('/register') || action.includes('/password') || action.includes('/logout')) {
                return; // Não aplicar loading em formulários de autenticação
            }
            
            // Determinar mensagem baseado no método e ação
            let message = 'Processando...';
            
            if (method === 'POST' && action.includes('store')) {
                message = 'Salvando...';
            } else if (method === 'PUT' || method === 'PATCH' || action.includes('update')) {
                message = 'Atualizando...';
            }
            
            // Usar setTimeout para dar tempo dos listeners específicos executarem primeiro
            setTimeout(() => {
                // Verificar novamente se o evento não foi preventDefaulted
                if (!e.defaultPrevented && !form.dataset.noLoading) {
                    FormLoading.show(form, message);
                }
            }, 0);
        }
    }, false);
    
    // Não aplicar loading em botões de formulários GET (filtros de busca)
    // Formulários GET são navegações normais e não precisam de loading
    
    // Adicionar loading em Select2 durante busca AJAX
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $(document).on('select2:open', function() {
            // Adicionar loading quando Select2 abrir (se for AJAX)
        });
        
        $(document).on('select2:selecting', function(e) {
            // Mostrar loading ao selecionar (se necessário)
        });
    }
    
    // Interceptar navegação (para mostrar loading em links)
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href]');
        if (link && link.href && !link.href.includes('#') && !link.dataset.noLoading) {
            // Ignorar links que são apenas para abrir submenus
            if (link.classList.contains('has-arrow') || link.getAttribute('href') === 'javascript: void(0);' || link.getAttribute('href') === 'javascript:void(0);') {
                return; // Não mostrar loading para links de submenu
            }
            
            // Se não for um link especial (modal, dropdown, etc)
            if (!link.hasAttribute('data-bs-toggle') && !link.hasAttribute('data-toggle')) {
                const href = link.getAttribute('href');
                // Só mostrar loading se for uma rota interna
                if (href && !href.startsWith('http') && !href.startsWith('mailto:') && !href.startsWith('tel:') && !href.startsWith('javascript:')) {
                    LoadingOverlay.show('Carregando página...');
                }
            }
        }
    });
    
    // Esconder overlay quando a página terminar de carregar
    window.addEventListener('load', function() {
        LoadingOverlay.hide();
    });
    
    // Esconder overlay ao voltar/avançar no histórico do navegador
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            LoadingOverlay.hide();
        }
    });
});

// Esconder overlay imediatamente ao carregar o script (caso tenha ficado de uma navegação anterior)
LoadingOverlay.hide();

// Exportar para uso global
window.LoadingOverlay = LoadingOverlay;
window.ButtonLoading = ButtonLoading;
window.FieldLoading = FieldLoading;
window.FormLoading = FormLoading;
window.ContentLoading = ContentLoading;
