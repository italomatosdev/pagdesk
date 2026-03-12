<!-- JAVASCRIPT -->
<!-- jQuery (necessário para Select2 e maskMoney) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<!-- jQuery maskMoney (máscara de valor BRL: R$ 1.234,56) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>
<script src="{{ asset('build/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('build/libs/metismenujs/metismenujs.min.js') }}"></script>
<script src="{{ asset('build/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('build/libs/eva-icons/eva.min.js') }}"></script>
<!-- Sweet Alerts js -->
<script src="{{ asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<!-- Sweet Alert Helper -->
<script>
    // Helper para usar Sweet Alert com mensagens do Laravel
    (function() {
        'use strict';

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
            @if(session('success'))
                showSuccess('{{ session('success') }}');
            @endif

            @if(session('error'))
                showError('{{ session('error') }}');
            @endif

            @if(session('warning'))
                showWarning('{{ session('warning') }}');
            @endif

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
</script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Loading System JS -->
<script src="{{ asset('build/js/loading.js') }}"></script>
<!-- App js -->
<script src="{{ asset('build/js/app.js') }}"></script>
@yield('scripts')
{{-- Máscara BRL: único script; inputs com data-mask-money="brl". DEBUG ativo. --}}
<script>
(function(){
    console.log('[MaskBRL] Script carregou, readyState=', document.readyState);
    var opts = { prefix: 'R$ ', thousands: '.', decimal: ',', precision: 2, allowZero: true, affixesStay: false };
    var sel = '[data-mask-money="brl"]';

    function parseVal(v) {
        if (v == null || v === '') return NaN;
        var s = String(v).replace(/\s/g, '').replace(/R\$\s?/g, '').trim();
        if (!s) return NaN;
        if (s.indexOf(',') !== -1) s = s.replace(/\./g, '').replace(',', '.');
        var n = parseFloat(s);
        return (isNaN(n) || n < 0) ? NaN : n;
    }

    function formatBRL(n) {
        var num = typeof n === 'number' ? n : parseFloat(String(n).replace(',', '.'));
        if (isNaN(num)) return '';
        var parts = num.toFixed(2).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return 'R$ ' + parts.join(',');
    }

    function applyOne(jq, el) {
        if (!jq || !jq.fn.maskMoney) { console.log('[MaskBRL] applyOne: jQuery/maskMoney ausente'); return; }
        var $el = jq(el);
        if (!$el.length || el.readOnly) return;
        var id = el.id || el.name || '(sem id)';
        var rawVal = $el.val();
        var num;
        if ($el.data('maskMoney')) {
            try { $el.maskMoney('destroy'); } catch (e) { console.log('[MaskBRL] destroy falhou', e); }
            num = parseVal(rawVal);
            if (isNaN(num)) num = parseVal(el.getAttribute('value'));
            if (isNaN(num)) num = 0;
            console.log('[MaskBRL] applyOne (destroy+reaplica) id=', id, 'rawVal=', JSON.stringify(rawVal), 'num=', num);
        } else {
            num = parseVal(rawVal);
            if (isNaN(num)) num = parseVal(el.getAttribute('value'));
            if (isNaN(num)) num = 0;
            console.log('[MaskBRL] applyOne (novo) id=', id, 'rawVal=', JSON.stringify(rawVal), 'num=', num);
        }
        $el.maskMoney(opts);
        $el.maskMoney('mask', num);
        var after = el.value;
        if (after === '0.0' || after === '0') {
            $el.val(formatBRL(num));
            console.log('[MaskBRL] forçou formatBRL:', el.value);
        } else {
            console.log('[MaskBRL] após mask: el.value=', JSON.stringify(el.value));
        }
    }

    function run() {
        var jq = window.jQuery;
        var nEl = jq ? jq(sel).length : 0;
        console.log('[MaskBRL] run() jQuery=', !!jq, 'maskMoney=', !!(jq && jq.fn.maskMoney), 'elementos=', nEl);
        if (!jq || !jq.fn.maskMoney) return;
        jq(sel).each(function() { applyOne(jq, this); });
    }

    function unmaskForm(jq, form) {
        if (!jq) return;
        jq(form).find(sel).each(function() {
            var $in = jq(this);
            if ($in.data('maskMoney')) {
                var num = $in.maskMoney('unmasked')[0];
                if (num != null && !isNaN(num)) this.value = parseFloat(num).toFixed(2);
            }
        });
    }

    function bindSubmit() {
        var jq = window.jQuery;
        if (!jq) return;
        jq(document).on('submit', 'form', function() { unmaskForm(jq, this); });
        var orig = HTMLFormElement.prototype.submit;
        HTMLFormElement.prototype.submit = function() {
            unmaskForm(jq, this);
            return orig.apply(this, arguments);
        };
    }

    var bound = false;
    function initMaskBRL() {
        run();
        if (bound) return;
        if (!window.jQuery || !window.jQuery.fn.maskMoney) return;
        bound = true;
        bindSubmit();
        if (document.body) {
            document.body.addEventListener('focusin', function(e) {
                var el = e.target;
                if (el && el.getAttribute && el.getAttribute('data-mask-money') === 'brl') {
                    var jq = window.jQuery;
                    if (jq && jq.fn.maskMoney) applyOne(jq, el);
                }
            }, true);
        }
    }

    function scheduleRun() {
        console.log('[MaskBRL] scheduleRun()');
        initMaskBRL();
        setTimeout(function(){ console.log('[MaskBRL] run setTimeout 0'); run(); }, 0);
        setTimeout(function(){ console.log('[MaskBRL] run setTimeout 100'); run(); }, 100);
        setTimeout(function(){ console.log('[MaskBRL] run setTimeout 400'); run(); }, 400);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleRun);
    } else {
        scheduleRun();
    }
    window.addEventListener('load', function(){ console.log('[MaskBRL] window.load'); run(); });
    window.applyMaskBRL = run;
    window.MoneyMaskBRL = {
        applyMaskToAll: run,
        unformat: function(v) { var n = parseVal(v); return isNaN(n) ? '' : n.toFixed(2); },
        format: formatBRL
    };
})();
</script>
