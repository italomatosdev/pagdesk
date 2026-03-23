{{-- Recarrega #consultores-select ao mudar #relatorio-operacao-id (mesma regra que getConsultoresParaRelatorio). --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    var consultorEl = document.getElementById('consultores-select');
    if (!consultorEl) return;

    function initConsultoresSelect2() {
        if (typeof $ === 'undefined' || !$.fn.select2) return;
        var $el = $('#consultores-select');
        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
        var ph = consultorEl.getAttribute('data-select2-placeholder') || 'Consultores';
        $el.select2({ theme: 'bootstrap-5', placeholder: ph, allowClear: true });
    }

    initConsultoresSelect2();

    var operacaoEl = document.getElementById('relatorio-operacao-id');
    if (!operacaoEl) return;

    var url = @json(route('relatorios.consultores-por-operacao'));

    function reloadConsultores() {
        var op = operacaoEl.value || '';
        var keep = new Set(Array.from(consultorEl.selectedOptions).map(function(o) { return o.value; }));
        fetch(url + '?operacao_id=' + encodeURIComponent(op), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(data) {
                if (!data.consultores) return;
                consultorEl.innerHTML = '';
                data.consultores.forEach(function(c) {
                    var opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name;
                    if (keep.has(String(c.id))) opt.selected = true;
                    consultorEl.appendChild(opt);
                });
                initConsultoresSelect2();
            })
            .catch(function() {});
    }

    operacaoEl.addEventListener('change', reloadConsultores);
});
</script>
