<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($form->name); ?></title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg: #0f172a;
            --card-bg: #1e293b;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
            --error: #ef4444;
            --success: #10b981;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: transparent; color: var(--text); padding: 16px; font-size: 14px; }
        .raptor-form-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 24px;
            max-width: 580px;
            margin: 0 auto;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .form-title { font-size: 20px; font-weight: 700; margin-bottom: 8px; color: var(--text); }
        .form-desc { color: var(--text-muted); font-size: 13px; margin-bottom: 20px; line-height: 1.4; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-weight: 500; margin-bottom: 6px; font-size: 13px; color: var(--text-muted); }
        .form-label span.req { color: var(--error); margin-left: 2px; }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            background: #0b1120;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-size: 14px;
            transition: border-color 0.15s ease;
        }
        .form-control:focus { outline: none; border-color: var(--primary); }
        textarea.form-control { resize: vertical; min-height: 80px; }
        .btn-submit {
            display: inline-block;
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 8px;
        }
        .btn-submit:hover { background: var(--primary-hover); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
        /* Anti-spam honeypot (hidden from human visitors) */
        .raptor-hp { display: none !important; visibility: hidden !important; }
        /* Alert boxes */
        .msg-box { display: none; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; line-height: 1.4; }
        .msg-success { background: rgba(16, 185, 129, 0.15); border: 1px solid var(--success); color: var(--success); }
        .msg-error { background: rgba(239, 68, 68, 0.15); border: 1px solid var(--error); color: var(--error); }
    </style>
</head>
<body>
    <div class="raptor-form-card">
        <h2 class="form-title"><?= htmlspecialchars($form->name); ?></h2>
        <?php if (!empty($form->description)): ?>
            <p class="form-desc"><?= htmlspecialchars($form->description); ?></p>
        <?php endif; ?>

        <div id="msgSuccess" class="msg-box msg-success">
            <?= htmlspecialchars($form->success_message); ?>
        </div>
        <div id="msgError" class="msg-box msg-error"></div>

        <form id="raptorLeadForm" method="POST" action="index.php?route=api_lead_capture/form">
            <input type="hidden" name="form_uuid" value="<?= htmlspecialchars($form->form_uuid); ?>">

            <!-- Anti-spam Honeypot -->
            <div class="raptor-hp">
                <input type="text" name="_raptor_hp" tabindex="-1" autocomplete="off">
            </div>

            <!-- Auto-populated tracking fields -->
            <input type="hidden" name="_rpt_uid" id="_rpt_uid" value="">
            <input type="hidden" name="page_url" id="page_url" value="">
            <input type="hidden" name="referrer" id="referrer" value="">
            <input type="hidden" name="utm_source" id="utm_source" value="">
            <input type="hidden" name="utm_medium" id="utm_medium" value="">
            <input type="hidden" name="utm_campaign" id="utm_campaign" value="">
            <input type="hidden" name="utm_content" id="utm_content" value="">
            <input type="hidden" name="utm_term" id="utm_term" value="">

            <?php foreach ($fields as $field): ?>
                <div class="form-group">
                    <label class="form-label" for="f_<?= htmlspecialchars($field['name']); ?>">
                        <?= htmlspecialchars($field['label']); ?>
                        <?php if (!empty($field['required'])): ?><span class="req">*</span><?php endif; ?>
                    </label>

                    <?php if ($field['type'] === 'textarea'): ?>
                        <textarea class="form-control" id="f_<?= htmlspecialchars($field['name']); ?>" name="<?= htmlspecialchars($field['name']); ?>" <?= !empty($field['required']) ? 'required' : ''; ?>></textarea>
                    <?php else: ?>
                        <input type="<?= htmlspecialchars($field['type']); ?>" class="form-control" id="f_<?= htmlspecialchars($field['name']); ?>" name="<?= htmlspecialchars($field['name']); ?>" <?= !empty($field['required']) ? 'required' : ''; ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" id="btnSubmit" class="btn-submit">
                <?= htmlspecialchars($form->submit_button_text); ?>
            </button>
        </form>
    </div>

    <script>
        (function() {
            // Read parent or local URL params for UTMs
            function getParam(name) {
                var search = window.location.search.substring(1);
                if (window.parent && window.parent.location && window.parent.location.search) {
                    search = window.parent.location.search.substring(1);
                }
                var pairs = search.split('&');
                for (var i = 0; i < pairs.length; i++) {
                    var p = pairs[i].split('=');
                    if (decodeURIComponent(p[0]) === name) return decodeURIComponent(p[1] || '');
                }
                return '';
            }

            // Populate tracking
            document.getElementById('utm_source').value = getParam('utm_source');
            document.getElementById('utm_medium').value = getParam('utm_medium');
            document.getElementById('utm_campaign').value = getParam('utm_campaign');
            document.getElementById('utm_content').value = getParam('utm_content');
            document.getElementById('utm_term').value = getParam('utm_term');

            try {
                document.getElementById('page_url').value = (window.parent && window.parent.location) ? window.parent.location.href : window.location.href;
                document.getElementById('referrer').value = (window.parent && window.parent.document) ? window.parent.document.referrer : document.referrer;
            } catch(e) {
                document.getElementById('page_url').value = window.location.href;
            }

            // Read visitor cookie if available
            var match = document.cookie.match(new RegExp('(^|;\\s*)_rpt_uid=([^;]*)'));
            if (match) {
                document.getElementById('_rpt_uid').value = decodeURIComponent(match[2]);
            }

            // Form Submit Interception
            var form = document.getElementById('raptorLeadForm');
            var btn = document.getElementById('btnSubmit');
            var msgSuccess = document.getElementById('msgSuccess');
            var msgError = document.getElementById('msgError');

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                btn.disabled = true;
                btn.innerText = 'Submitting...';
                msgError.style.display = 'none';

                var formData = new FormData(form);
                var json = {};
                formData.forEach(function(value, key){ json[key] = value; });

                fetch('index.php?route=api_lead_capture/form', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(json)
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        form.style.display = 'none';
                        msgSuccess.innerText = res.message || 'Submission successful!';
                        msgSuccess.style.display = 'block';

                        if (res.redirect_url) {
                            setTimeout(function() {
                                if (window.top) window.top.location.href = res.redirect_url;
                                else window.location.href = res.redirect_url;
                            }, 1200);
                        }
                    } else {
                        btn.disabled = false;
                        btn.innerText = '<?= htmlspecialchars($form->submit_button_text); ?>';
                        msgError.innerText = res.error || 'An error occurred. Please try again.';
                        msgError.style.display = 'block';
                    }
                })
                .catch(function(err) {
                    btn.disabled = false;
                    btn.innerText = '<?= htmlspecialchars($form->submit_button_text); ?>';
                    msgError.innerText = 'Network error. Please try again.';
                    msgError.style.display = 'block';
                });
            });
        })();
    </script>
</body>
</html>
