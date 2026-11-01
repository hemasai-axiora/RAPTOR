<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Email Preferences</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #334155;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .pref-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            max-width: 520px;
            width: 100%;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .pref-header {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            padding: 30px 24px;
            text-align: center;
        }
        .pref-body {
            padding: 30px 24px;
        }
    </style>
</head>
<body>
    <div class="pref-card">
        <div class="pref-header">
            <i class="fa-solid fa-envelope-circle-check fs-1 mb-2"></i>
            <h4 class="fw-bold mb-1">Email Subscription Preferences</h4>
            <p class="small text-white-50 mb-0">We value your privacy and inbox boundaries</p>
        </div>

        <div class="pref-body">
            <p class="small mb-4 text-muted">
                You are updating preferences for: <strong class="text-dark"><?php echo htmlspecialchars($email); ?></strong>
            </p>

            <form method="POST" action="index.php?route=unsubscribe/action">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="hidden" name="list_id" value="<?php echo htmlspecialchars($list_id); ?>">

                <div class="card bg-light border-0 p-3 mb-3">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="scope" id="scope_list" value="list" checked>
                        <label class="form-check-label fw-semibold" for="scope_list">
                            Unsubscribe from this mailing list only
                        </label>
                        <div class="small text-muted ms-0">
                            You will stop receiving updates for "<strong><?php echo htmlspecialchars($list_name); ?></strong>", but may still receive important transactional notices or other subscribed topics.
                        </div>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="scope" id="scope_global" value="global">
                        <label class="form-check-label fw-semibold text-danger" for="scope_global">
                            Opt-out of all communications (Global Suppression)
                        </label>
                        <div class="small text-muted ms-0">
                            Permanently add <code><?php echo htmlspecialchars($email); ?></code> to our suppression list. You will never receive marketing or announcement emails again.
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary py-2 fw-semibold">
                        Confirm Preference Update
                    </button>
                </div>
            </form>

            <div class="text-center mt-4">
                <p class="small text-muted mb-0" style="font-size: 0.8rem;">
                    Compliant with RFC 8058 One-Click Unsubscribe, CAN-SPAM Act, and EU GDPR.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
