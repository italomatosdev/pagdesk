{{-- Não inclua nas views que usam o layout padrão: a máscara BRL é aplicada pelo script global em layouts/vendor-scripts.blade.php (após @yield). Use só data-mask-money="brl" nos inputs. Este partial fica como referência/fallback. --}}
<script>
(function() {
    var maskMoneyOpts = { prefix: 'R$ ', thousands: '.', decimal: ',', precision: 2, allowZero: true, affixesStay: false };
    var sel = '[data-mask-money="brl"]';
    var ids = ['valor_aprovacao_automatica', 'valor_movimentacao', 'preco_venda', 'valor_total_fixo', 'valor', 'valor_min', 'valor_pagamento', 'valor_juros_renovacao_fixo', 'valor_juros_fixo', 'cheque-valor', 'edit-cheque-valor', 'edit-valor'];

    function parseInitial(v) {
        if (!v || !v.toString) return NaN;
        var s = (v + '').replace(/\s/g, '').replace(/R\$\s?/g, '').trim();
        if (!s) return NaN;
        if (s.indexOf(',') !== -1) s = s.replace(/\./g, '').replace(',', '.');
        var n = parseFloat(s);
        return (isNaN(n) || n < 0) ? NaN : n;
    }

    function applyToEl($el) {
        if (!$el.length || $el.data('maskMoney') || $el[0].readOnly) return;
        $el.maskMoney(maskMoneyOpts);
        var n = parseInitial($el.val());
        if (!isNaN(n)) $el.maskMoney('mask', n);
    }

    function applyMask() {
        if (typeof jQuery === 'undefined' || !jQuery.fn.maskMoney) return;
        jQuery(sel).each(function() { applyToEl(jQuery(this)); });
        ids.forEach(function(id) {
            var $el = jQuery('#' + id);
            if ($el.length) applyToEl($el);
        });
    }

    function unmaskForm(form) {
        if (typeof jQuery === 'undefined') return;
        jQuery(form).find(sel).each(function() {
            var $in = jQuery(this);
            if ($in.data('maskMoney')) {
                var num = $in.maskMoney('unmasked')[0];
                if (num != null && !isNaN(num)) this.value = parseFloat(num).toFixed(2);
            }
        });
    }

    if (!window._maskBrlBound) {
        window._maskBrlBound = true;
        jQuery(document).on('submit', 'form', function() { unmaskForm(this); });
        var orig = HTMLFormElement.prototype.submit;
        HTMLFormElement.prototype.submit = function() { unmaskForm(this); return orig.apply(this, arguments); };
    }

    applyMask();
    jQuery(document).ready(applyMask);
    setTimeout(applyMask, 100);
    setTimeout(applyMask, 350);
    setTimeout(applyMask, 700);
    jQuery(document).on('shown.bs.modal', '.modal', applyMask);
    window.MoneyMaskBRL = window.MoneyMaskBRL || {};
    window.MoneyMaskBRL.applyMaskToAll = applyMask;
})();
</script>
