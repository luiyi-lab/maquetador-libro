/* Maquetador de Libros — JS vanilla, APIs nativas del navegador (§4)
   Las funciones init* son contratadas por las vistas; no renombrar ni
   cambiar firmas sin actualizar las vistas que las invocan. */

/* ---------------------------------------------------------------
   Subida de manuscritos (dropzone)
   --------------------------------------------------------------- */
function initDropzone(zone, input, fileLabel, form) {
    if (!zone || !input) return;

    zone.addEventListener('click', () => input.click());

    input.addEventListener('change', () => {
        if (input.files.length > 0) {
            const name = input.files[0].name;
            fileLabel.textContent = '📄 ' + name;
            fileLabel.classList.add('has-file');
            form.submit(); // envío directo al elegir archivo (comportamiento original)
        }
    });

    ['dragenter', 'dragover'].forEach((evt) =>
        zone.addEventListener(evt, (e) => {
            e.preventDefault();
            zone.classList.add('dragover');
        })
    );
    ['dragleave', 'drop'].forEach((evt) =>
        zone.addEventListener(evt, (e) => {
            e.preventDefault();
            zone.classList.remove('dragover');
        })
    );

    zone.addEventListener('drop', (e) => {
        if (e.dataTransfer.files.length > 0) {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        }
    });

    // Accesibilidad: activar con teclado
    zone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            input.click();
        }
    });
}

/* ---------------------------------------------------------------
   Pestañas (con memoria de la pestaña activa)
   --------------------------------------------------------------- */
function initTabs(tabsContainer) {
    if (!tabsContainer) return;
    const buttons = tabsContainer.querySelectorAll('.tab');
    const panels = document.querySelectorAll('.tab-panel');

    const remembered = sessionStorage.getItem('activeTab');
    if (remembered) activate(remembered);

    buttons.forEach((btn) =>
        btn.addEventListener('click', () => activate(btn.dataset.tab))
    );

    function activate(id) {
        buttons.forEach((b) => b.classList.toggle('active', b.dataset.tab === id));
        panels.forEach((p) => p.classList.toggle('active', p.id === id));
        sessionStorage.setItem('activeTab', id);
    }
}

/* ---------------------------------------------------------------
   Confirmaciones para acciones peligrosas
   --------------------------------------------------------------- */
function initConfirmForms() {
    document.querySelectorAll('form[data-confirm]').forEach((form) =>
        form.addEventListener('submit', (e) => {
            if (!window.confirm(form.dataset.confirm)) e.preventDefault();
        })
    );
}

/* ---------------------------------------------------------------
   Panel de bloques de cada capítulo
   --------------------------------------------------------------- */
function initBlockToggles() {
    document.querySelectorAll('.toggle-blocks').forEach((btn) =>
        btn.addEventListener('click', () => {
            const panel = document.getElementById(btn.dataset.toggle);
            if (!panel) return;
            panel.hidden = !panel.hidden;
            const label = btn.querySelector('.toggle-label');
            const text = panel.hidden ? 'Editar' : 'Cerrar';
            if (label) label.textContent = text; else btn.textContent = text;
        })
    );
}

/* ---------------------------------------------------------------
   Reordenar capítulos con drag & drop (§9).
   El orden resultante se envía explícito al servidor, que lo valida y guarda.
   --------------------------------------------------------------- */
function initDragReorder(list) {
    if (!list) return;
    let dragged = null;
    let target = null;

    list.addEventListener('dragstart', (e) => {
        const card = e.target.closest('.chapter-card');
        if (!card) return;
        dragged = card;
        card.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    list.addEventListener('dragend', () => {
        if (dragged) dragged.classList.remove('dragging');
        if (target) target.classList.remove('drop-target');
        dragged = null;
        target = null;
        saveOrder();
    });

    list.addEventListener('dragover', (e) => {
        e.preventDefault();
        if (!dragged) return;
        const card = e.target.closest('.chapter-card');
        if (!card || card === dragged) return;
        const rect = card.getBoundingClientRect();
        const after = e.clientY > rect.top + rect.height / 2;
        card.parentNode.insertBefore(dragged, after ? card.nextSibling : card);
        if (target && target !== card) target.classList.remove('drop-target');
        card.classList.add('drop-target');
        target = card;
    });

    function saveOrder() {
        const order = Array.from(list.querySelectorAll('.chapter-card'))
            .map((c) => c.dataset.chapterId);

        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const url = list.dataset.reorderUrl;

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ order }),
        }).catch(() => window.location.reload());
    }
}

/* ---------------------------------------------------------------
   Sondeo del estado de la exportación (§15)
   --------------------------------------------------------------- */
function pollExport(el) {
    if (!el) return;
    el.hidden = false;

    const url = el.dataset.statusUrl;
    const timer = setInterval(async () => {
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('status');
            const data = await res.json();

            if (data.status === 'completed') {
                clearInterval(timer);
                window.location.reload();
            } else if (data.status === 'failed') {
                clearInterval(timer);
                el.hidden = false;
                el.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Error al generar el PDF: ' +
                    (data.error || 'desconocido') +
                    ' — puedes reintentarlo desde «Exportaciones».';
            }
        } catch {
            clearInterval(timer);
        }
    }, 2000);
}

/* ---------------------------------------------------------------
   Tema claro/oscuro (persistente, sin duplicar CSS)
   --------------------------------------------------------------- */
function initThemeToggle() {
    const btn = document.getElementById('theme-toggle');
    if (!btn) return;

    const icon = () => {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        btn.innerHTML = dark
            ? '<i class="fa-solid fa-sun" aria-hidden="true"></i>'
            : '<i class="fa-solid fa-moon" aria-hidden="true"></i>';
    };
    icon();

    btn.addEventListener('click', () => {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (dark) {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
        }
        icon();
    });
}

/* ---------------------------------------------------------------
   Menú móvil
   --------------------------------------------------------------- */
function initNavToggle() {
    const toggle = document.getElementById('nav-toggle');
    const menu = document.getElementById('nav-menu');
    if (!toggle || !menu) return;

    toggle.addEventListener('click', () => {
        const open = menu.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    });

    document.addEventListener('click', (e) => {
        if (menu.classList.contains('open') && !menu.contains(e.target) && !toggle.contains(e.target)) {
            menu.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
}

/* ---------------------------------------------------------------
   Toasts: cierre manual y auto-cierre
   --------------------------------------------------------------- */
function initToasts() {
    document.querySelectorAll('.toast').forEach((toast) => {
        const close = toast.querySelector('.toast-close');
        const dismiss = () => {
            toast.classList.add('hide');
            setTimeout(() => toast.remove(), 240);
        };
        if (close) close.addEventListener('click', dismiss);
        setTimeout(dismiss, 6000);
    });
}

/* ---------------------------------------------------------------
   Botones con estado de carga: evita dobles envíos y da feedback
   --------------------------------------------------------------- */
function initLoadingButtons() {
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        const button = form.querySelector('button[type="submit"]');
        if (!button || button.classList.contains('is-loading')) return;

        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');
    });
}

/* Arranque de la capa global de UI */
initThemeToggle();
initNavToggle();
initToasts();
initLoadingButtons();
