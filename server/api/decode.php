<?php
declare(strict_types=1);

function encodeBase64Gz(string $plain, int $level = 6, bool $urlSafe = false): string {
    $compressed = gzcompress($plain, $level);
    $b64 = base64_encode($compressed);
    if ($urlSafe) {
        // Make base64 URL-safe: +/ -> -_, strip padding =
        $b64 = rtrim(strtr($b64, '+/', '-_'), '=');
    }
    return $b64;
}

/**
 * @return array{
 *   ok:bool,
 *   decoded?:string,
 *   pretty?:string,
 *   isJson?:bool,
 *   error?:string,
 *   sizes?:array<string,int>,
 *   note?:string
 * }
 */
function decodeBase64Gz(string $encoded): array {
    $encoded = trim($encoded);
    if ($encoded === '') {
        return ['ok' => false, 'error' => 'Input is empty.'];
    }

    // Accept URL-safe base64 too
    $normalized = $encoded;
    if (preg_match('~^[A-Za-z0-9\-_]+$~', $encoded)) {
        $normalized = strtr($encoded, '-_', '+/');
        $pad = strlen($normalized) % 4;
        if ($pad) {
            $normalized .= str_repeat('=', 4 - $pad);
        }
    }

    $bin = base64_decode($normalized, true);
    if ($bin === false) {
        return ['ok' => false, 'error' => 'Invalid base64 string.'];
    }

    $out = @gzuncompress($bin);
    if ($out === false) {
        // As a convenience, try base64-only (no gz) if gz fails.
        $maybeText = @($normalized === $encoded ? base64_decode($encoded, true) : base64_decode($normalized, true));
        if ($maybeText !== false && $maybeText !== '') {
            return [
                'ok' => true,
                'decoded' => $maybeText,
                'pretty' => tryPrettyJson($maybeText),
                'isJson' => isJson($maybeText),
                'sizes' => [
                    'base64_bytes' => strlen($encoded),
                    'decoded_bytes' => strlen($maybeText),
                ],
                'note' => 'Decoded as base64 only (no gzip).'
            ];
        }
        return ['ok' => false, 'error' => 'gzuncompress() failed. Make sure the data was encoded with gzcompress() before base64.'];
    }

    return [
        'ok' => true,
        'decoded' => $out,
        'pretty' => tryPrettyJson($out),
        'isJson' => isJson($out),
        'sizes' => [
            'base64_bytes'     => strlen($encoded),
            'compressed_bytes' => strlen($bin),
            'decoded_bytes'    => strlen($out),
        ]
    ];
}

function isJson(string $s): bool {
    json_decode($s, true);
    return json_last_error() === JSON_ERROR_NONE;
}

function tryPrettyJson(string $s): string {
    $j = json_decode($s, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '';
    }
    return json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function bytesToMb(float $bytes, int $precision = 2): string {
    return number_format($bytes / 1048576, $precision) . ' MB';
}

function ratio(float $a, float $b): string {
    if ($b <= 0) return '—';
    return number_format($a / $b, 2) . '×';
}

// ---------- Handle request ----------
$mode      = $_POST['mode']      ?? 'decode'; // 'encode'|'decode'
$text      = $_POST['text']      ?? '';
$level     = isset($_POST['level']) ? (int)$_POST['level'] : 6;
$urlSafe   = isset($_POST['url_safe']) && $_POST['url_safe'] === '1';
$trimInput = isset($_POST['trim']) && $_POST['trim'] === '1';

if ($trimInput) {
    $text = trim($text);
}

$result = [
    'output'    => '',
    'pretty'    => '',
    'isJson'    => false,
    'error'     => '',
    'mode'      => $mode,
    'level'     => $level,
    'url_safe'  => $urlSafe,
    'trim'      => $trimInput,
    'sizes'     => [],
    'note'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'encode') {
        $lvl = max(0, min(9, $level));
        $compressed = gzcompress($text, $lvl);
        $b64 = base64_encode($compressed);
        if ($urlSafe) {
            $b64 = rtrim(strtr($b64, '+/', '-_'), '=');
        }
        $result['output'] = $b64;
        $result['sizes'] = [
            'input_bytes'      => strlen($text),
            'compressed_bytes' => strlen($compressed),
            'base64_bytes'     => strlen($b64),
        ];
        // Helpful: if input was JSON, also show compact form that was encoded
        if (isJson($text)) {
            $compact = json_encode(json_decode($text, true), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $result['encoded_source_compact'] = $compact;
        }
    } else { // decode
        $dec = decodeBase64Gz($text);
        if ($dec['ok']) {
            $result['output'] = $dec['decoded'] ?? '';
            $result['pretty'] = $dec['pretty'] ?? '';
            $result['isJson'] = $dec['isJson'] ?? false;
            $result['sizes']  = $dec['sizes'] ?? [];
            $result['note']   = $dec['note'] ?? '';
        } else {
            $result['error'] = $dec['error'] ?? 'Unknown error.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Wallet Nile Cron - GZ Encoded/Decoded</title>
    <style>
        :root {
            --bg: #0f172a;
            --panel: #111827;
            --muted: #6b7280;
            --text: #e5e7eb;
            --accent: #22d3ee;
            --error: #ef4444;
            --ok: #10b981;
        }
        * { box-sizing: border-box; }
        html {
            height: 100%;
        }
        body {
            min-height: 100%;
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, "Helvetica Neue", Arial, "Apple Color Emoji", "Segoe UI Emoji";
            background: radial-gradient(1200px 600px at 20% -10%, rgba(34,211,238,0.25), transparent 40%), var(--bg);
            color: var(--text);
        }
        .wrap {
            max-width: 1300px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }
        h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 16px;
            letter-spacing: .2px;
        }
        form.panel {
            background: linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.01));
            border: 1px solid rgba(255,255,255,.06);
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,.25);
        }
        .grid {
            display: grid;
            gap: 12px;
        }
        @media (min-width: 900px) {
            .grid { grid-template-columns: 1.1fr 1fr; }
        }
        label { font-size: 13px; color: var(--muted); }
        textarea, input[type="text"] {
            width: 100%;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.08);
            background: #0b1220;
            color: var(--text);
            padding: 12px;
            min-height: 250px;
            resize: vertical;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 13px;
            line-height: 1.5;
            overflow: auto;
        }
        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 16px;
            align-items: center;
            margin: 8px 0 0;
        }
        .controls .group { display: flex; gap: 8px; align-items: center; }
        button {
            appearance: none;
            border: 0;
            padding: 10px 14px;
            border-radius: 10px;
            background: linear-gradient(180deg, rgba(34,211,238,.2), rgba(34,211,238,.15));
            border: 1px solid rgba(34,211,238,.35);
            color: var(--text);
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { filter: brightness(1.1); }
        .muted { color: var(--muted); font-size: 12px; }
        .badge { padding: 2px 8px; border-radius: 999px; font-size: 11px; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08); }
        .error { color: var(--error); }
        .ok { color: var(--ok); }
        .row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .w-60 { width: 60px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mt-4 { margin-top: 16px; }
        .right { text-align:right; }
        .sticky-footer {
            position: sticky; bottom: 0; padding-top: 8px; background: linear-gradient(180deg, transparent, rgba(15,23,42,.8) 40%);
        }
        .btns { display:flex; gap:8px; flex-wrap:wrap; }
        .small { font-size: 12px; padding: 8px 10px; border-radius: 8px; }
        .code {
            display:inline-block; padding: 3px 6px; border-radius: 6px;
            background: rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size:12px;
        }
        .stats {
            display:grid; gap:6px;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 12px; padding: 10px 12px;
            font-size: 12px;
        }
        .stats .label { color: var(--muted); }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Encode/Decode &nbsp;<span class="badge">base64 ⟷ gzcompress</span></h1>

    <form  class="panel grid" method="post">
        <div style="display: flex; flex-direction: column;">
            <label for="text">Input</label>
            <textarea style="flex-grow: 1" id="text" name="text" placeholder="Paste encoded text (for Decode) or raw text/JSON (for Encode)"><?= htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>

            <div class="controls">
                <div class="group">
                    <label>Mode</label>
                    <label class="row"><input type="radio" name="mode" value="decode" <?= ($result['mode'] ?? 'decode') === 'decode' ? 'checked' : '' ?>> Decode</label>
                    <label class="row"><input type="radio" name="mode" value="encode" <?= ($result['mode'] ?? 'decode') === 'encode' ? 'checked' : '' ?>> Encode</label>
                </div>
                <div class="group" title="0=store only, 9=max compression (but slower)">
                    <label for="level">Level</label>
                    <input class="w-60" type="number" id="level" name="level" min="0" max="9" value="<?= (int)($result['level'] ?? 6) ?>">
                </div>
                <div class="group">
                    <label class="row"><input type="checkbox" name="url_safe" value="1" <?= !empty($result['url_safe']) ? 'checked' : '' ?>> URL‑safe base64</label>
                </div>
                <div class="group">
                    <label class="row"><input type="checkbox" name="trim" value="1" <?= !empty($result['trim']) ? 'checked' : '' ?>> Trim input</label>
                </div>
                <div class="group">
                    <button type="submit">Run</button>
                </div>
            </div>
            <p class="muted mt-4" style="margin-bottom: 0px">Decode path: <span class="code">gzuncompress(base64_decode($in))</span></p>
            <p class="muted">Encode path: <span class="code">base64_encode(gzcompress($in, $level))</span>.</p>
        </div>

        <div>
            <label for="output"><?= $result['mode'] === 'encode' ? 'Encoded output' : 'Decoded output' ?></label>
            <textarea id="output" readonly><?= htmlspecialchars($result['output'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>

            <?php if (!empty($result['error'])): ?>
                <p class="error mt-2">❌ <?= htmlspecialchars($result['error'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endif; ?>

            <?php if (!empty($result['note'])): ?>
                <p class="muted mt-2">ℹ️ <?= htmlspecialchars($result['note'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endif; ?>

            <?php if (!empty($result['sizes'])): ?>
                <div class="mt-3">
                    <label>Size stats (MB & bytes)</label>
                    <div class="stats mt-1">
                        <?php if (($result['mode'] ?? '') === 'encode'): ?>
                            <?php
                                $inB  = $result['sizes']['input_bytes']      ?? 0;
                                $gzB  = $result['sizes']['compressed_bytes'] ?? 0;
                                $b64B = $result['sizes']['base64_bytes']     ?? 0;
                            ?>
                            <div><span class="label">Input (raw):</span> <?= bytesToMb($inB) ?> (<?= number_format($inB) ?> B)</div>
                            <div><span class="label">Compressed (gz):</span> <?= bytesToMb($gzB) ?> (<?= number_format($gzB) ?> B) — ratio <?= ratio($gzB, max(1,$inB)) ?> vs input</div>
                            <div><span class="label">Base64 output:</span> <?= bytesToMb($b64B) ?> (<?= number_format($b64B) ?> B) — overhead <?= ratio($b64B, max(1,$gzB)) ?> vs gz</div>
                        <?php else: ?>
                            <?php
                                $b64B = $result['sizes']['base64_bytes']     ?? 0;
                                $gzB  = $result['sizes']['compressed_bytes'] ?? 0;
                                $outB = $result['sizes']['decoded_bytes']    ?? 0;
                            ?>
                            <div><span class="label">Encoded (base64) length:</span> <?= bytesToMb($b64B) ?> (<?= number_format($b64B) ?> B)</div>
                            <?php if ($gzB): ?>
                                <div><span class="label">Compressed (gz) bytes:</span> <?= bytesToMb($gzB) ?> (<?= number_format($gzB) ?> B)</div>
                            <?php endif; ?>
                            <div><span class="label">Decoded (raw) length:</span> <?= bytesToMb($outB) ?> (<?= number_format($outB) ?> B)</div>
                            <?php if ($gzB && $outB): ?>
                                <div><span class="label">Compression ratio (gz vs raw):</span> <?= ratio($gzB, max(1,$outB)) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (($result['mode'] ?? '') === 'decode' && !empty($result['pretty'])): ?>
                <div class="mt-3">
                    <label for="pretty">Pretty JSON (decoded)</label>
                    <textarea id="pretty" readonly><?= htmlspecialchars($result['pretty'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                </div>
            <?php endif; ?>

            <?php if (($result['mode'] ?? '') === 'encode' && !empty($result['encoded_source_compact'])): ?>
                <p class="muted mt-2">Detected JSON in input — encoded the exact text you provided. Here’s a compacted JSON variant (not encoded), which you may prefer to encode for smaller size:</p>
                <textarea readonly class="mt-1"><?= htmlspecialchars($result['encoded_source_compact'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
            <?php endif; ?>

            <div class="sticky-footer right mt-3 btns">
                <button type="button" class="small" onclick="copyFrom('output')">Copy <?= $result['mode'] === 'encode' ? 'Encoded' : 'Decoded' ?></button>
                <?php if (($result['mode'] ?? '') === 'decode' && !empty($result['pretty'])): ?>
                    <button type="button" class="small" onclick="copyFrom('pretty')">Copy Pretty JSON</button>
                <?php endif; ?>
                <button type="button" class="small" onclick="copyFrom('text')">Copy Input</button>
            </div>
        </div>
    </form>

    <p class="muted mt-4">Tip: If your encoded string is URL-safe (uses <span class="code">-</span>/<span class="code">_</span> instead of <span class="code">+</span>/<span class="code">/</span>), we’ll accept it and auto-fix padding.</p>
</div>

<script>
function copyFrom(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.select();
    try {
        document.execCommand('copy');
    } catch (e) {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = el.value || el.innerText || '';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    }
}
</script>
</body>
</html>
