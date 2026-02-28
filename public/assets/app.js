const refreshSeconds = Number(window.STATUS_AGENT_REFRESH_SECONDS || 10);

const pillText = document.getElementById('pillText');
const dot = document.getElementById('dot');
const errorBox = document.getElementById('error');
const grid = document.getElementById('grid');

const uptimeEl = document.getElementById('uptime');
const loadEl = document.getElementById('load');
const updatedEl = document.getElementById('updated');

const memBar = document.getElementById('memBar');
const memPct = document.getElementById('memPct');

const diskBar = document.getElementById('diskBar');
const diskPct = document.getElementById('diskPct');

const tokenInput = document.getElementById('token');
const loadPrivateBtn = document.getElementById('loadPrivate');
const clearTokenBtn = document.getElementById('clearToken');
const privateHint = document.getElementById('privateHint');

const privateCard = document.getElementById('privateCard');
const privateJson = document.getElementById('privateJson');

const lastUpdateTop = document.getElementById('lastUpdate');

const langEnBtn = document.getElementById('langEn');
const langPtBtn = document.getElementById('langPt');

const I18N = {
    en: {
        title: 'Status',
        loading: 'Loading',
        auto_refresh: 'Auto refresh',
        last_update: 'Last update',
        public_json: 'Public JSON',
        private_json: 'Private JSON',
        public_overview: 'Public overview',
        uptime: 'Uptime',
        load_average: 'Load average',
        updated: 'Updated',
        memory: 'Memory',
        usage: 'Usage',
        disk_root: 'Disk /',
        owner_tools: 'Owner tools',
        owner_hint_a: 'Enter your token to fetch',
        load_private: 'Load private',
        clear: 'Clear',
        private_json_title: 'Private JSON',
        operational: 'Operational',
        degraded: 'Degraded',
        token_empty: 'Token is empty.',
        loading_dots: 'Loading...',
        loaded: 'Loaded.',
        cleared: 'Cleared.',
        failed_public: 'Failed to load /public',
        failed_private: 'Failed to load /status'
    },
    'pt-br': {
        title: 'Status',
        loading: 'Carregando',
        auto_refresh: 'Auto refresh',
        last_update: 'Última atualização',
        public_json: 'JSON público',
        private_json: 'JSON privado',
        public_overview: 'Resumo público',
        uptime: 'Uptime',
        load_average: 'Carga',
        updated: 'Atualizado',
        memory: 'Memória',
        usage: 'Uso',
        disk_root: 'Disco /',
        owner_tools: 'Área do dono',
        owner_hint_a: 'Informe seu token para buscar',
        load_private: 'Carregar privado',
        clear: 'Limpar',
        private_json_title: 'JSON privado',
        operational: 'Operacional',
        degraded: 'Problema',
        token_empty: 'Token vazio.',
        loading_dots: 'Carregando...',
        loaded: 'Carregado.',
        cleared: 'Limpo.',
        failed_public: 'Falha ao carregar /public',
        failed_private: 'Falha ao carregar /status'
    }
};

function normLang(v) {
    v = String(v || '').toLowerCase().trim();
    if (v === 'pt' || v === 'pt-br') return 'pt-br';
    return 'en';
}

let lang = normLang(window.STATUS_AGENT_LANG || localStorage.getItem('status_agent_lang') || 'en');

function t(key) {
    const dict = I18N[lang] || I18N.en;
    return dict[key] || I18N.en[key] || key;
}

function applyI18n() {
    document.querySelectorAll('[data-i18n]').forEach((el) => {
        const key = el.getAttribute('data-i18n');
        if (!key) return;
        el.textContent = t(key);
    });

    if (tokenInput) tokenInput.placeholder = 'Bearer token';

    if (langEnBtn) langEnBtn.classList.toggle('active', lang === 'en');
    if (langPtBtn) langPtBtn.classList.toggle('active', lang === 'pt-br');
}

function setBad(msg) {
    dot.classList.add('bad');
    pillText.textContent = t('degraded');
    errorBox.style.display = 'block';
    errorBox.textContent = msg;
    grid.style.display = 'none';
}

function setOk() {
    dot.classList.remove('bad');
    pillText.textContent = t('operational');
    errorBox.style.display = 'none';
    grid.style.display = 'grid';
}

function formatLoad(load) {
    if (!Array.isArray(load) || load.length < 3) return 'n/a';
    return `${load[0]} · ${load[1]} · ${load[2]}`;
}

function clampPct(v) {
    v = Number(v);
    if (!Number.isFinite(v)) return null;
    if (v < 0) return 0;
    if (v > 100) return 100;
    return Math.round(v);
}

function formatDateTime(tsSeconds) {
    const ts = Number(tsSeconds || 0);
    if (!ts) return '-';
    const d = new Date(ts * 1000);

    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const mi = String(d.getMinutes()).padStart(2, '0');
    const ss = String(d.getSeconds()).padStart(2, '0');

    return `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
}

function readMemoryPercent(publicObj) {
    if (!publicObj || typeof publicObj !== 'object') return null;

    const mem = publicObj.memory;
    if (mem && typeof mem === 'object' && mem.used_percent !== undefined) {
        return clampPct(mem.used_percent);
    }

    if (publicObj.memory_percent !== undefined) {
        return clampPct(publicObj.memory_percent);
    }

    return null;
}

function readDiskRootPercent(publicObj) {
    if (!publicObj || typeof publicObj !== 'object') return null;

    const d = publicObj.disk_root;
    if (d && typeof d === 'object' && d.used_percent !== undefined) {
        return clampPct(d.used_percent);
    }

    if (publicObj.disk_root_percent !== undefined) {
        return clampPct(publicObj.disk_root_percent);
    }

    return null;
}

async function loadPublic() {
    try {
        const r = await fetch('/public', { headers: { 'Accept': 'application/json' } });
        const j = await r.json();

        if (!r.ok || !j || j.ok !== true) {
            const err = (j && j.error) ? j.error : `HTTP ${r.status}`;
            setBad(err);
            return;
        }

        const p = j.public || {};
        setOk();

        uptimeEl.textContent = p.uptime_human || 'n/a';
        loadEl.textContent = formatLoad(p.loadavg);

        const last = formatDateTime(j.timestamp);
        updatedEl.textContent = last;
        if (lastUpdateTop) lastUpdateTop.textContent = last;

        const mp = readMemoryPercent(p);
        memBar.style.width = (mp === null ? 0 : mp) + '%';
        memPct.textContent = mp === null ? 'n/a' : `${mp}%`;

        const dp = readDiskRootPercent(p);
        diskBar.style.width = (dp === null ? 0 : dp) + '%';
        diskPct.textContent = dp === null ? 'n/a' : `${dp}%`;
    } catch (e) {
        setBad(t('failed_public'));
    }
}

async function loadPrivate() {
    const token = tokenInput.value.trim();
    if (!token) {
        privateHint.textContent = t('token_empty');
        return;
    }

    try {
        privateHint.textContent = t('loading_dots');

        const r = await fetch('/status', {
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const j = await r.json().catch(() => null);

        if (!r.ok || !j || j.ok !== true) {
            const err = (j && j.error) ? j.error : `HTTP ${r.status}`;
            privateHint.textContent = err;
            privateCard.style.display = 'none';
            return;
        }

        privateJson.textContent = JSON.stringify(j, null, 2);
        privateCard.style.display = 'block';
        privateHint.textContent = t('loaded');
        localStorage.setItem('status_agent_token', token);
    } catch (e) {
        privateHint.textContent = t('failed_private');
        privateCard.style.display = 'none';
    }
}

function restoreToken() {
    const tkn = localStorage.getItem('status_agent_token') || '';
    tokenInput.value = tkn;
}

function setLang(newLang) {
    lang = normLang(newLang);
    localStorage.setItem('status_agent_lang', lang);

    const url = new URL(window.location.href);
    url.searchParams.set('lang', lang);
    window.location.href = url.toString();
}

if (langEnBtn) langEnBtn.addEventListener('click', () => setLang('en'));
if (langPtBtn) langPtBtn.addEventListener('click', () => setLang('pt-br'));

if (loadPrivateBtn) loadPrivateBtn.addEventListener('click', loadPrivate);

if (clearTokenBtn) {
    clearTokenBtn.addEventListener('click', () => {
        tokenInput.value = '';
        localStorage.removeItem('status_agent_token');
        privateHint.textContent = t('cleared');
        privateCard.style.display = 'none';
    });
}

applyI18n();
restoreToken();
loadPublic();

setInterval(() => {
    loadPublic();
}, refreshSeconds * 1000);