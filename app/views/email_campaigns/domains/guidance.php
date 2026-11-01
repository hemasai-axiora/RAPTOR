<?php
// Raptor CRM - Deliverability Tooling & Reputation Guidance View
?>
<div class="container-fluid px-0">
    <!-- Header & Subnav -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-graduation-cap text-primary me-2"></i>Deliverability Tooling & Reputation Guidance</h3>
            <p class="text-secondary small mb-0">Official procedures to register sending domains with Google Postmaster Tools, Microsoft SNDS, and run seed list inbox testing.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=email_campaigns/domains" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-shield-halved me-1"></i>Sending Domains
            </a>
            <a href="index.php?route=email_campaigns/healthReport" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-heart-pulse me-1"></i>Daily Health Report
            </a>
        </div>
    </div>

    <!-- Sub-navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3">
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/campaigns">
                <i class="fa-solid fa-paper-plane me-1"></i>Campaigns
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/analytics">
                <i class="fa-solid fa-chart-pie me-1"></i>Analytics
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/domains">
                <i class="fa-solid fa-shield-halved me-1"></i>Domains & DNS
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active fw-medium" href="index.php?route=email_campaigns/deliverabilityGuidance">
                <i class="fa-solid fa-book-bookmark me-1"></i>Deliverability Guidance
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/healthReport">
                <i class="fa-solid fa-heart-pulse me-1"></i>Daily Health Report
            </a>
        </li>
    </ul>

    <!-- Guide 1: Google Postmaster Tools -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 fw-semibold">
                <i class="fa-brands fa-google text-danger me-2"></i>1. Google Postmaster Tools (Gmail Inbox Placement)
            </h5>
            <a href="https://postmaster.google.com/" target="_blank" class="btn btn-outline-danger btn-sm">
                <i class="fa-solid fa-external-link me-1"></i>Open Postmaster Tools &rarr;
            </a>
        </div>
        <div class="card-body">
            <p class="small text-secondary mb-3">
                Google Postmaster Tools is the single most authoritative source for monitoring your Gmail sender reputation, spam rate, IP reputation, domain reputation, and feedback loop complaints.
            </p>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded border h-100">
                        <div class="fw-bold small text-dark mb-1">Step 1: Add Domain</div>
                        <p class="small text-muted mb-0">Navigate to <a href="https://postmaster.google.com/" target="_blank">postmaster.google.com</a>, log in with your Google Admin account, click <strong>Add Domain (+)</strong>, and enter your dedicated sending subdomain (e.g. <code>mail.company.com</code>).</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded border h-100">
                        <div class="fw-bold small text-dark mb-1">Step 2: Add DNS TXT Record</div>
                        <p class="small text-muted mb-0">Google will supply a unique verification TXT string (e.g. <code>google-site-verification=...</code>). Add this TXT record at your DNS host root for the subdomain, then click <strong>Verify</strong>.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded border h-100">
                        <div class="fw-bold small text-dark mb-1">Step 3: Monitor Weekly</div>
                        <p class="small text-muted mb-0">Ensure your <strong>Spam Rate remains strictly below 0.10%</strong>. Google flags any sender with &gt; 0.30% spam rate for bulk delivery throttling or automatic spam foldering.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Guide 2: Microsoft SNDS & JERP -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 fw-semibold">
                <i class="fa-brands fa-microsoft text-primary me-2"></i>2. Microsoft SNDS & Junk Email Reporting Program (JERP)
            </h5>
            <a href="https://sendersupport.olc.protection.outlook.com/snds/" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-external-link me-1"></i>Open Microsoft SNDS &rarr;
            </a>
        </div>
        <div class="card-body">
            <p class="small text-secondary mb-3">
                Microsoft Smart Network Data Services (SNDS) provides reputation metrics for emails delivered to Outlook.com, Hotmail, Live, and MSN accounts.
            </p>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded border h-100">
                        <div class="fw-bold small text-dark mb-1"><i class="fa-solid fa-network-wired text-primary me-1"></i>Smart Network Data Services (SNDS)</div>
                        <p class="small text-muted mb-2">If you send through a dedicated sending IP (from AWS SES or SendGrid), request access in SNDS to monitor your IP reputation status (Green, Yellow, or Red).</p>
                        <a href="https://sendersupport.olc.protection.outlook.com/snds/FAQ.aspx" target="_blank" class="small fw-medium">SNDS FAQ & Request Access &rarr;</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded border h-100">
                        <div class="fw-bold small text-dark mb-1"><i class="fa-solid fa-bullhorn text-warning me-1"></i>Junk Email Reporting Program (JERP)</div>
                        <p class="small text-muted mb-2">Enroll your sending IP in JERP to receive automated feedback loop notifications when Outlook users click "Report as Spam", automatically routing them to Raptor's webhook suppression.</p>
                        <a href="https://sendersupport.olc.protection.outlook.com/snds/JMRP.aspx" target="_blank" class="small fw-medium">Enroll in JERP &rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Guide 3: Seed List & Inbox Placement Testing Protocol -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-semibold">
                <i class="fa-solid fa-vial-circle-check text-success me-2"></i>3. Seed List Inbox Placement Testing Protocol
            </h5>
        </div>
        <div class="card-body">
            <p class="small text-secondary mb-3">
                Before launching a major marketing campaign or onboarding a new sending domain, always execute a seed-list test to confirm that inbox placement is working across all major mailbox providers.
            </p>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle small mb-3">
                    <thead class="table-light">
                        <tr>
                            <th>Mailbox Provider</th>
                            <th>Recommended Test Seed Accounts</th>
                            <th>Critical Verification Checks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-medium"><i class="fa-brands fa-google text-danger me-1"></i>Gmail & Google Workspace</td>
                            <td>2 Personal Gmail accounts + 1 Google Workspace corporate account</td>
                            <td>Verify delivery to <strong>Primary tab</strong> (not Promotions or Spam); inspect headers for <code>spf=pass, dkim=pass, dmarc=pass</code>.</td>
                        </tr>
                        <tr>
                            <td class="fw-medium"><i class="fa-brands fa-microsoft text-primary me-1"></i>Outlook & Microsoft 365</td>
                            <td>1 Personal Outlook.com account + 1 Office 365 tenant account</td>
                            <td>Verify delivery to <strong>Focused tab</strong> (not Other or Junk); check that sender name and TLS encryption padlocks display cleanly.</td>
                        </tr>
                        <tr>
                            <td class="fa-brands fa-yahoo text-purple me-1 fw-medium">Yahoo & AOL</td>
                            <td>1 Yahoo Mail account + 1 AOL Mail account</td>
                            <td>Verify RFC 8058 <strong>One-Click Unsubscribe button</strong> is rendered at the top of the webmail interface.</td>
                        </tr>
                        <tr>
                            <td class="fw-medium"><i class="fa-brands fa-apple text-dark me-1"></i>Apple Mail & iCloud</td>
                            <td>1 iCloud.com account on iOS/macOS Mail app</td>
                            <td>Confirm responsive email formatting on mobile screen; verify plain-text alternative renders if images are blocked.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="alert alert-warning border-0 small mb-0">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                <strong>Pre-Send Mandate:</strong> In Raptor CRM, every campaign draft includes a <strong>"Send Test to Self"</strong> button on the pre-send checklist. Campaign launching is locked until at least one test send has been executed and confirmed by the sender.
            </div>
        </div>
    </div>
</div>
