/**
 * Helper para usar Sweet Alert com mensagens do Laravel
 * Captura mensagens de sessão (success, error) e exibe via Sweet Alert
 */
(function() {
    'use strict';

    // Verificar se Sweet Alert está disponível
    if (typeof Swal === 'undefined') {
        console.warn('Sweet Alert não está carregado');
        return;
    }

    // Função para exibir alerta de sucesso
    function showSuccess(message) {
        Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: message,
            confirmButtonColor: '#038edc',
            timer: 3000,
            timerProgressBar: true
        });
    }

    // Função para exibir alerta de erro
    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: message,
            confirmButtonColor: '#038edc'
        });
    }

    // Função para exibir alerta de aviso
    function showWarning(message) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção!',
            text: message,
            confirmButtonColor: '#038edc'
        });
    }

    // Função para exibir alerta de informação
    function showInfo(message) {
        Swal.fire({
            icon: 'info',
            title: 'Informação',
            text: message,
            confirmButtonColor: '#038edc'
        });
    }

    // Função para confirmar ação
    function showConfirm(options) {
        return Swal.fire({
            title: options.title || 'Tem certeza?',
            text: options.text || 'Esta ação não pode ser desfeita!',
            icon: options.icon || 'warning',
            showCancelButton: true,
            confirmButtonColor: '#038edc',
            cancelButtonColor: '#6c757d',
            confirmButtonText: options.confirmText || 'Sim, confirmar!',
            cancelButtonText: options.cancelText || 'Cancelar'
        });
    }

    // Capturar mensagens da sessão Laravel quando a página carregar
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar se há mensagens de sucesso na sessão
        @if(session('success'))
            showSuccess('{{ session('success') }}');
        @endif

        // Verificar se há mensagens de erro na sessão
        @if(session('error'))
            showError('{{ session('error') }}');
        @endif

        // Verificar se há mensagens de aviso na sessão
        @if(session('warning'))
            showWarning('{{ session('warning') }}');
        @endif

        // Verificar se há mensagens de informação na sessão
        @if(session('info'))
            showInfo('{{ session('info') }}');
        @endif
    });

    // Expor funções globalmente
    window.SweetAlertHelper = {
        success: showSuccess,
        error: showError,
        warning: showWarning,
        info: showInfo,
        confirm: showConfirm
    };
})();
