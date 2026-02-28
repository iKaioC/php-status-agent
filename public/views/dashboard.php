<?php

declare(strict_types=1);

$lang = 'en';
$qLang = $_GET['lang'] ?? null;

if (is_string($qLang)) {
    $qLang = strtolower(trim($qLang));
    if ($qLang === 'pt-br' || $qLang === 'pt') $lang = 'pt-br';
    if ($qLang === 'en') $lang = 'en';
}

?>
<!doctype html>
<html lang="<?= $lang === 'pt-br' ? 'pt-BR' : 'en' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Status</title>
    <?php $vCss = (string) @filemtime(__DIR__ . '/../assets/app.css'); ?>
    <link rel="stylesheet" href="/assets/app.css?v=<?= htmlspecialchars($vCss, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="wrap">
    <div class="top">
        <div class="title">
            <div class="titleRow">
                <h1 data-i18n="title">Status</h1>
                <div class="langToggle" role="group" aria-label="Language">
                    <button type="button" id="langEn" class="langBtn">EN</button>
                    <button type="button" id="langPt" class="langBtn">PT-BR</button>
                </div>
            </div>

            <div class="meta">
                <div class="metaItem">
                    <span class="metaLabel" data-i18n="auto_refresh">Auto refresh</span>
                    <span class="metaValue mono"><?= (int) $refresh ?>s</span>
                </div>

                <div class="metaItem">
                    <span class="metaLabel" data-i18n="last_update">Last update</span>
                    <span class="metaValue mono" id="lastUpdate">-</span>
                </div>

                <div class="metaItem">
                    <span class="metaLabel" data-i18n="public_json">Public JSON</span>
                    <span class="metaValue mono">/public</span>
                </div>

                <div class="metaItem">
                    <span class="metaLabel" data-i18n="private_json">Private JSON</span>
                    <span class="metaValue mono">/status</span>
                </div>
            </div>
        </div>

        <div class="pill" id="pill">
            <span class="dot" id="dot"></span>
            <span id="pillText" data-i18n="loading">Loading</span>
        </div>
    </div>

    <div id="error" class="err" style="display:none;"></div>

    <div class="grid" id="grid" style="display:none;">
        <div class="card">
            <h2 data-i18n="public_overview">Public overview</h2>
            <div class="kv">
                <div class="k" data-i18n="uptime">Uptime</div>
                <div class="v" id="uptime">-</div>

                <div class="k" data-i18n="load_average">Load average</div>
                <div class="v mono" id="load">-</div>

                <div class="k" data-i18n="updated">Updated</div>
                <div class="v mono" id="updated">-</div>
            </div>
        </div>

        <div class="card">
            <h2 data-i18n="memory">Memory</h2>
            <div class="bar"><span id="memBar"></span></div>
            <div class="row">
                <div id="memLabel" data-i18n="usage">Usage</div>
                <div id="memPct">-</div>
            </div>
        </div>

        <div class="card">
            <h2 data-i18n="disk_root">Disk /</h2>
            <div class="bar"><span id="diskBar"></span></div>
            <div class="row">
                <div id="diskLabel" data-i18n="usage">Usage</div>
                <div id="diskPct">-</div>
            </div>
        </div>

        <div class="card">
            <h2 data-i18n="owner_tools">Owner tools</h2>
            <div class="small">
                <span data-i18n="owner_hint_a">Enter your token to fetch</span>
                <span class="mono">/status</span>.
            </div>

            <div class="inputRow">
                <input type="password" id="token" placeholder="Bearer token">
                <button id="loadPrivate" data-i18n="load_private">Load private</button>
                <button id="clearToken" data-i18n="clear">Clear</button>
            </div>

            <div class="small" id="privateHint" style="margin-top: 10px;"></div>
        </div>

        <div class="card full" id="privateCard" style="display:none;">
            <h2 data-i18n="private_json_title">Private JSON</h2>
            <pre class="mono" id="privateJson"></pre>
        </div>
    </div>
</div>

<script>
    window.STATUS_AGENT_REFRESH_SECONDS = <?= (int) $refresh ?>;
    window.STATUS_AGENT_LANG = <?= json_encode($lang, JSON_UNESCAPED_SLASHES) ?>;
</script>
<?php $vJs = (string) @filemtime(__DIR__ . '/../assets/app.js'); ?>
<script src="/assets/app.js?v=<?= htmlspecialchars($vJs, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>