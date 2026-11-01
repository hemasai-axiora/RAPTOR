<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="index.php?route=whatsapp/conversations" class="text-secondary text-decoration-none small">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to WhatsApp Inbox
        </a>
    </div>

    <div class="pulse-card p-4">
        <h4 class="text-white mb-1"><i class="fa-solid fa-gear me-2 text-primary"></i>Meta WhatsApp Cloud API Configuration</h4>
        <div class="text-secondary small mb-4">Official Meta Developer Graph API credentials, Webhook callbacks, and security tokens.</div>

        <!-- Webhook Configuration for Meta Dashboard -->
        <div class="card bg-dark border-secondary p-3 mb-4" style="border-radius: 8px;">
            <h6 class="text-white mb-2"><i class="fa-solid fa-webhook me-2 text-info"></i>Meta App Webhook Settings</h6>
            <div class="text-secondary small mb-3">Copy these values into your <strong>Meta App Dashboard &gt; WhatsApp &gt; Configuration &gt; Edit Webhook</strong>:</div>

            <div class="mb-3">
                <label class="form-label text-secondary small">Callback URL</label>
                <div class="input-group">
                    <input type="text" class="form-control bg-black text-info font-monospace border-secondary" value="<?= htmlspecialchars($webhook_url); ?>" readonly>
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label text-secondary small">Verify Token</label>
                <div class="input-group">
                    <input type="text" class="form-control bg-black text-warning font-monospace border-secondary" value="<?= htmlspecialchars($verify_token); ?>" readonly>
                </div>
            </div>
            <div class="text-muted small mt-2">
                Subscribe to webhook fields: <code>messages</code>
            </div>
        </div>

        <!-- Environment Variables Overview -->
        <div class="card bg-dark border-secondary p-3" style="border-radius: 8px;">
            <h6 class="text-white mb-2"><i class="fa-solid fa-key me-2 text-warning"></i>Required .env Secrets</h6>
            <div class="text-secondary small mb-3">Configure these keys in your <code>.env</code> file on the server:</div>

            <pre class="bg-black text-white p-3 rounded small font-monospace mb-0" style="font-size: 0.8rem; line-height: 1.6;">
# Meta WhatsApp Business Cloud API Credentials
WHATSAPP_TOKEN="EAABw..."
WHATSAPP_PHONE_NUMBER_ID="109876543210987"
WHATSAPP_BUSINESS_ACCOUNT_ID="123456789012345"
WHATSAPP_WEBHOOK_VERIFY_TOKEN="<?= htmlspecialchars($verify_token); ?>"
WHATSAPP_APP_SECRET="your_meta_app_secret_here"
WHATSAPP_API_VERSION="v21.0"
            </pre>
        </div>
    </div>
</div>
