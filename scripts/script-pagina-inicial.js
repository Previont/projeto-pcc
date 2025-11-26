// Carrosséis e listas dinâmicas na página inicial
// Objetivo: navegar por campanhas recentes e todas, com indicadores e atualização automática.
document.addEventListener('DOMContentLoaded', function () {
    function initHorizontal(sectionSelector, containerSelector, prevSelector, nextSelector, options) {
        const section = document.querySelector(sectionSelector);
        const container = section ? section.querySelector(containerSelector) : null;
        const prevBtn = section ? section.querySelector(prevSelector) : null;
        const nextBtn = section ? section.querySelector(nextSelector) : null;
        if (!section || !container || !prevBtn || !nextBtn) return null;

        const indicadorEsq = document.createElement('div');
        indicadorEsq.className = 'scroll-indicator left';
        const indicadorDir = document.createElement('div');
        indicadorDir.className = 'scroll-indicator right';
        const pos = document.createElement('div');
        pos.className = 'pos-indicador';
        section.appendChild(indicadorEsq);
        section.appendChild(indicadorDir);
        section.appendChild(pos);

        function stepWidth() {
            const first = container.querySelector(':scope > *');
            const cs = getComputedStyle(container);
            const gapStr = cs.gap || cs.getPropertyValue('gap') || '16px';
            const gap = parseFloat(gapStr) || 16;
            return first ? (first.offsetWidth + gap) : Math.max(280, Math.floor(container.clientWidth * 0.8));
        }

        function atualizarIndicadores() {
            const maxScroll = container.scrollWidth - container.clientWidth;
            const s = container.scrollLeft;
            prevBtn.disabled = s <= 0;
            nextBtn.disabled = s >= maxScroll - 1;
            indicadorEsq.style.display = s > 0 ? 'block' : 'none';
            indicadorDir.style.display = s < maxScroll ? 'block' : 'none';
            const total = container.children.length;
            const current = Math.min(total, Math.max(1, Math.round(s / stepWidth()) + 1));
            pos.textContent = `${current} / ${total}`;
        }

        function scrollByAmount(dir) {
            const amount = stepWidth();
            container.scrollBy({ left: dir * amount, behavior: 'smooth' });
            setTimeout(atualizarIndicadores, 300);
        }

        prevBtn.addEventListener('click', function () { scrollByAmount(-1); });
        nextBtn.addEventListener('click', function () { scrollByAmount(1); });
        container.addEventListener('scroll', atualizarIndicadores, { passive: true });
        window.addEventListener('resize', atualizarIndicadores);
        atualizarIndicadores();

        return { section, container, atualizarIndicadores };
    }

    const recentes = initHorizontal('.carrossel-secao', '.carrossel-container', '.botao-carrossel.prev', '.botao-carrossel.next', { autoUpdate: true });
    const todas = initHorizontal('.todas-as-campanhas', '.grade-campanhas', '.botao-carrossel.prev', '.botao-carrossel.next', { autoUpdate: false });

    if (todas) {
        const ids = new Set();
        const children = Array.from(todas.container.children);
        let removidas = 0;
        for (let i = 0; i < children.length; i++) {
            const el = children[i];
            const idAttr = el.getAttribute('data-id');
            const id = idAttr ? Number(idAttr) : NaN;
            if (!Number.isFinite(id)) continue;
            if (ids.has(id)) {
                el.remove();
                removidas++;
            } else {
                ids.add(id);
            }
        }
        // Atualiza indicador após limpeza
        todas.atualizarIndicadores();
    }

    if (recentes) {
        // Cria card visual para campanha recente
        function criarCard(c) {
            const card = document.createElement('div');
            card.className = 'card-carrossel';
            card.style.opacity = '0';
            card.style.transform = 'translateY(10px)';
            const img = document.createElement('img');
            img.src = c.url_imagem && typeof c.url_imagem === 'string' && (c.url_imagem.startsWith('http://') || c.url_imagem.startsWith('https://')) ? c.url_imagem : `../${String(c.url_imagem || 'uploads/imagem-padrao-item.png')}`;
            img.alt = 'Imagem da Campanha';
            img.loading = 'lazy';
            img.onerror = function () { this.onerror = null; this.src = '../uploads/imagem-padrao-item.png'; };
            const h3 = document.createElement('h3');
            h3.textContent = c.titulo;
            const p = document.createElement('p');
            p.textContent = `Arrecadado: R$ ${Number(c.valor_arrecadado || 0).toFixed(2).replace('.', ',')}`;
            const a = document.createElement('a');
            a.href = `campanha-detalhes.php?id=${c.id}`;
            a.className = 'botao-detalhes';
            a.textContent = 'Ver Mais';
            card.appendChild(img);
            card.appendChild(h3);
            card.appendChild(p);
            card.appendChild(a);
            requestAnimationFrame(function () {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            });
            return card;
        }

        function idsAtuais() {
            return Array.from(recentes.container.querySelectorAll('.card-carrossel')).map(el => el.dataset.id).filter(Boolean).map(Number);
        }

        // Renderiza campanhas novas, evitando duplicar as existentes
        function render(campanhas) {
            campanhas.sort(function (a, b) { return new Date(b.data_criacao) - new Date(a.data_criacao); });
            const existentes = new Set(idsAtuais());
            const fragmento = document.createDocumentFragment();
            for (let i = 0; i < campanhas.length; i++) {
                const c = campanhas[i];
                if (!existentes.has(Number(c.id))) {
                    const card = criarCard(c);
                    card.dataset.id = String(c.id);
                    fragmento.appendChild(card);
                }
            }
            if (fragmento.childNodes.length > 0) {
                recentes.container.insertBefore(fragmento, recentes.container.firstChild);
                recentes.atualizarIndicadores();
            }
        }

        // Busca periódica de campanhas recentes via AJAX
        async function atualizar() {
            try {
                const resp = await fetch('paginainicial.php?ajax=recentes', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!resp.ok) return;
                const dados = await resp.json();
                render(dados);
            } catch (e) {}
        }

        atualizar();
        setInterval(atualizar, 5000);
    }

    window.addEventListener('storage', function (e) {
        if (e.key !== 'campanha_toggle') return;
        let info = null;
        try { info = JSON.parse(e.newValue || '{}'); } catch (err) { info = null; }
        if (!info || typeof info.id !== 'number') return;
        const id = Number(info.id);
        if (!Number.isFinite(id)) return;
        if (info.novo_status === false) {
            const sel = [
                `.carrossel-secao .card-carrossel[data-id="${id}"]`,
                `.todas-as-campanhas .card-campanha[data-id="${id}"]`,
                `.destaque-principal .card-destaque[data-id="${id}"]`
            ];
            sel.forEach(function (s) {
                const el = document.querySelector(s);
                if (el) { el.remove(); }
            });
            const car = document.querySelector('.carrossel-secao .carrossel-container');
            const grade = document.querySelector('.todas-as-campanhas .grade-campanhas');
            if (car) { recentes && recentes.atualizarIndicadores && recentes.atualizarIndicadores(); }
            if (grade) { todas && todas.atualizarIndicadores && todas.atualizarIndicadores(); }
        } else if (info.novo_status === true) {
            try { atualizar && atualizar(); } catch (e) {}
        }
    });

    function runHomeSyncTests() {
        const resultados = [];
        function assert(nome, cond) { resultados.push({ nome, ok: !!cond }); }
        const grade = document.querySelector('.todas-as-campanhas .grade-campanhas');
        const car = document.querySelector('.carrossel-secao .carrossel-container');
        const dest = document.querySelector('.destaque-principal .container-destaques');
        const id = Date.now() % 1000000;
        function add(el, cls, parent) {
            const d = document.createElement('div'); d.className = cls; d.dataset.id = String(id); d.textContent = 'teste';
            parent && parent.appendChild(d); return d;
        }
        const a = dest ? add(null, 'card-destaque', dest) : null;
        const b = car ? add(null, 'card-carrossel', car) : null;
        const c = grade ? add(null, 'card-campanha', grade) : null;
        assert('cards de teste criados', (!!a || !!b || !!c));
        const payload = { id: id, novo_status: false, ts: Date.now() };
        localStorage.setItem('campanha_toggle', JSON.stringify(payload));
        setTimeout(function () {
            const aindaA = a && document.contains(a);
            const aindaB = b && document.contains(b);
            const aindaC = c && document.contains(c);
            assert('remoção de destaque', a ? !aindaA : true);
            assert('remoção de carrossel', b ? !aindaB : true);
            assert('remoção de grade', c ? !aindaC : true);
            const out = document.createElement('div');
            out.id = 'home-sync-tests';
            out.style.position = 'fixed'; out.style.bottom = '10px'; out.style.left = '10px';
            out.style.background = '#1f1f1f'; out.style.color = '#fff'; out.style.padding = '10px'; out.style.border = '1px solid #555'; out.style.borderRadius = '6px';
            resultados.forEach(function (r) { var li = document.createElement('div'); li.textContent = (r.ok ? '✔ ' : '✖ ') + r.nome; li.style.marginBottom = '4px'; out.appendChild(li); });
            document.body.appendChild(out);
        }, 300);
        return resultados;
    }

    try {
        const params = new URLSearchParams(window.location.search);
        if (params.get('runHomeTests') === '1') { runHomeSyncTests(); }
    } catch (e) {}
});
