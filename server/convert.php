<?php
function decodeBase64Gz(string $encoded): array {
    $pretty = '';
    $isJson = false;

    $bin = base64_decode($encoded, true);
    if ($bin !== false) {
        $out = @gzuncompress($bin);
        if ($out !== false) {
            $decoded = $out;

            $json = json_decode($decoded, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $isJson = true;
                $pretty = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        } else {
            $decoded = '⚠️ Failed to gzuncompress after base64 decode.';
        }
    } else {
        $decoded = '⚠️ Invalid Base64 input.';
    }

    return ['decoded' => $decoded, 'pretty' => $pretty, 'isJson' => $isJson];
}

$decoded = '';
$pretty = '';
$isJson = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['encoded'])) {
    $encoded = trim($_POST['encoded']);
    $result = decodeBase64Gz($encoded);
    $decoded = $result['decoded'];
    $pretty = $result['pretty'];
    $isJson = $result['isJson'];
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Decode Base64 + gzuncompress</title>
    <style>
        body{margin:0;font-family:sans-serif;background:#0b0f17;color:#e5e7eb;display:flex;justify-content:center}
        .wrap{max-width:1200px;margin:2rem;padding:2rem;background:#111827;border-radius:16px;box-shadow:0 8px 20px rgba(0,0,0,.4)}
        h1{margin-top:0;color:#f9fafb}
        textarea:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.3)}
        textarea{display:block;min-width: 800px;width:100%;max-width:100%;min-height:160px;padding:1rem;border-radius:12px;border:1px solid #1f2a3b;background:#0e1421;color:#e5e7eb;line-height:1.4;resize:vertical;overflow: hidden;box-sizing:border-box;}
        button{margin-top:1rem;padding:.8rem 1.2rem;font-weight:700;border-radius:10px;border:0;cursor:pointer;background:linear-gradient(180deg,#3b82f6,#2563eb);color:#fff}
        .section{margin-top:2rem}
        pre{background:#0a0f1a;border:1px solid #1f2937;padding:1rem;border-radius:12px;overflow:auto;max-height:400px;white-space:pre-wrap}
        .toolbar{display:flex;justify-content:flex-end;margin-bottom:.5rem}
        .toolbar button{background:#1f2937;font-size:.8rem;padding:.4rem .7rem;margin:0;border-radius:8px}
        #enc{margin-top: 1rem;}
    </style>
</head>
<body>
<div class="wrap">
    <h1>Decode Base64 + gzuncompress</h1>
    <form method="post">
        <div class="toolbar">
            <button type="button" onclick="copyText('enc')">Copy</button>
        </div>

        <label for="enc">Enter Encoded String</label>
        <textarea id="enc" name="encoded"><?= isset($_POST['encoded']) ? htmlspecialchars($_POST['encoded']) : '' ?></textarea>
        <br>
        <button type="submit">Decode</button>
    </form>

    <div class="section">
        <h2>Decoded String</h2>
        <?php if ($decoded !== ''): ?>
            <div class="toolbar"><button type="button" onclick="copyText('decoded')">Copy</button></div>
            <pre id="decoded"><?= htmlspecialchars($isJson ? $pretty : $decoded) ?></pre>
        <?php else: ?>
            <pre>—</pre>
        <?php endif; ?>
    </div>
</div>

<script>
    function copyText(elementId) {
        const element = document.getElementById(elementId);
        if (!element) {
            alert(`Element with ID "${elementId}" not found.`);
            return;
        }

        const textToCopy = element.value || element.innerText || '';
        if (!textToCopy) {
            alert(`No text to copy from element "${elementId}".`);
            return;
        }

        navigator.clipboard?.writeText(textToCopy)
            .then(() => alert(`Copied text from "${elementId}" to clipboard.`))
            .catch(err => {
                const tempTextarea = document.createElement('textarea');
                tempTextarea.value = textToCopy;
                document.body.appendChild(tempTextarea);
                tempTextarea.select();

                try {
                    document.execCommand('copy');
                    alert(`Copied text from "${elementId}" to clipboard.`);
                } catch (fallbackErr) {
                    alert(`Failed to copy text from "${elementId}": ${fallbackErr.message}`);
                } finally {
                    document.body.removeChild(tempTextarea);
                }
            });
    }
</script>
</body>
</html>