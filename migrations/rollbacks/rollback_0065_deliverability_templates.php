<?php
/**
 * Raptor CRM: Migration-Specific Rollback for Migration 0065
 * 
 * Safely rolls back changes introduced by migration 0065:
 * 1. Deletes only the 4 newly seeded prebuilt operational templates.
 * 2. Restores the original Workshop Invite template content (subject, html, plain text).
 * 3. Leaves all customer-created templates and operational data untouched.
 * 4. Removes migration 0065 from schema_migrations table if tracked.
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';

echo "Running Migration-Specific Rollback for 0065...\n";

try {
    $db = Database::getInstance()->getConnection();

    // 1. Delete only the 4 new prebuilt templates
    $newTemplates = [
        'Welcome & Account Registration',
        'Password Reset & OTP Verification',
        'Order & Payment Confirmation',
        'Admin & System Notification'
    ];

    $inClause = implode(',', array_map(function($t) use ($db) {
        return $db->quote($t);
    }, $newTemplates));

    $deleted = $db->exec("DELETE FROM campaign_templates WHERE name IN ({$inClause}) AND is_prebuilt = 1");
    echo "  + Removed {$deleted} prebuilt transactional template(s).\n";

    // 2. Restore original Workshop Invite template content
    $origJsonFile = __DIR__ . '/original_workshop_template_backup.json';
    if (file_exists($origJsonFile)) {
        $orig = json_decode(file_get_contents($origJsonFile), true);
    } else {
        // Embedded baseline fallback if json file is absent
        $orig = [
            'subject_default' => "Startup & Business Workshop – Want to Start or Grow a Business?",
            'html_content' => <<<'HTML'
<title>FREE Startup &amp; Business Workshop – Want to Start or Grow a Business?</title>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tbody><tr><td align="left" style="padding:16px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">
<tbody><tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#222222;">
<p style="margin:0 0 14px 0;">Dear Aspiring Founder / Entrepreneur / Business Owner,</p>
<p style="margin:0 0 14px 0;">Every great business begins with a dream. But without the right direction, even a great idea can remain just a dream.</p>
<p style="margin:18px 0 8px 0;font-size:17px;font-weight:bold;">Are you facing any of these challenges?</p>
<ul style="margin:0 0 14px 0;padding-left:22px;">
<li style="margin:0 0 6px 0;">Want to start a business but don't know how to begin?</li>
<li style="margin:0 0 6px 0;">Have a business idea but unsure whether it will work?</li>
<li style="margin:0 0 6px 0;">Worried about investing your hard-earned money?</li>
<li style="margin:0 0 6px 0;">Running a business but struggling to attract customers &amp; grow?</li>
<li style="margin:0 0 6px 0;">Looking for the right guidance to move forward?</li>
</ul>
<p style="margin:0 0 14px 0;">If YES, this Workshop is for you!</p>
<p style="margin:0 0 14px 0;">Axiora Global Solutions invites you to our Startup &amp; Business Workshop – From Idea Validation to Scale.</p>
<p style="margin:0 0 14px 0;">Every startup &amp; business journey has 10 critical stages. Do you know which stage you're in and what comes next?</p>
<p style="margin:18px 0 8px 0;font-size:17px;font-weight:bold;">What Will You Learn?</p>
<ol style="margin:0 0 14px 0;padding-left:24px;">
<li style="margin:0 0 6px 0;">Validate your startup/business idea before investing.</li>
<li style="margin:0 0 6px 0;">Understand your market, customers and competitors.</li>
<li style="margin:0 0 6px 0;">Plan your finances, capital and business model.</li>
<li style="margin:0 0 6px 0;">Turn your idea into an actionable business plan.</li>
<li style="margin:0 0 6px 0;">Explore ways to attract customers and grow your business.</li>
<li style="margin:0 0 6px 0;">Identify your current business stage and the next steps to consider.</li>
</ol>
<p style="margin:0 0 14px 0;">Whether you're a Student, Working Professional, Aspiring Entrepreneur, Startup Founder or Business Owner, this workshop will introduce a practical roadmap to help you make more informed business decisions.</p>
<p style="margin:0 0 14px 0;">You don't need to have all the answers. You just need to know how to start.</p>
<p style="margin:0 0 14px 0;">👉 REGISTER NOW:<br>
<a href="https://forms.office.com/r/zGPmq1YAaJ" style="color:#1a5fb4;">https://forms.office.com/r/zGPmq1YAaJ</a></p>
<p style="margin:18px 0 8px 0;font-size:17px;font-weight:bold;">Someone you know might be waiting for the right guidance.</p>
<p style="margin:0 0 14px 0;">Share this invitation with your friends, colleagues, future entrepreneurs, startup founders &amp; business owners. Your simple forward could inspire someone to take their next step.</p>
<p style="margin:18px 0 8px 0;font-size:17px;font-weight:bold;">Join Our Founders WhatsApp Community</p>
<p style="margin:0 0 8px 0;">Get entrepreneurship insights, business learning and future workshop updates.</p>
<p style="margin:0 0 14px 0;"><a href="https://chat.whatsapp.com/LXAaBAkSmNy15ri3bRgNhM" style="color:#1a5fb4;">https://chat.whatsapp.com/LXAaBAkSmNy15ri3bRgNhM</a></p>
<p style="margin:0 0 14px 0;font-weight:bold;">Your Dream. Your Business. Your Next Step Starts Here.</p>
<p style="margin:0 0 4px 0;"><strong>Email:</strong> <a href="mailto:contact@axioraglobalsolutions.com" style="color:#1a5fb4;">contact@axioraglobalsolutions.com</a></p>
<p style="margin:0 0 24px 0;"><strong>Phone:</strong> +91 77310 89602</p>
<p style="margin:0 0 4px 0;font-size:12px;line-height:1.5;color:#777777;">Axiora Global Solutions Pvt. Ltd.<br>If you would rather not receive emails like this, you can <a href="{{unsubscribe_url}}" style="color: rgb(119, 119, 119);">unsubscribe here</a>.</p>
</td></tr>
</tbody></table>
</td></tr>
</tbody></table>
HTML
,
            'plain_text_content' => <<<'TXT'
FREE Startup & Business Workshop – Want to Start or Grow a Business?

Dear Aspiring Founder / Entrepreneur / Business Owner,

Every great business begins with a dream. But without the right direction, even a great idea can remain just a dream.

Are you facing any of these challenges?

• Want to start a business but don't know how to begin?
• Have a business idea but unsure whether it will work?
• Worried about investing your hard-earned money?
• Running a business but struggling to attract customers & grow?
• Looking for the right guidance to move forward?

If YES, this Workshop is for you!

Axiora Global Solutions invites you to our Startup & Business Workshop – From Idea Validation to Scale.

Every startup & business journey has 10 critical stages. Do you know which stage you're in and what comes next?

What Will You Learn?

• Validate your startup/business idea before investing.
• Understand your market, customers and competitors.
• Plan your finances, capital and business model.
• Turn your idea into an actionable business plan.
• Explore ways to attract customers and grow your business.
• Identify your current business stage and the next steps to consider.

Whether you're a Student, Working Professional, Aspiring Entrepreneur, Startup Founder or Business Owner, this workshop will introduce a practical roadmap to help you make more informed business decisions.

You don't need to have all the answers. You just need to know how to start.

👉 REGISTER NOW:

https://forms.office.com/r/zGPmq1YAaJ

Someone you know might be waiting for the right guidance.

Share this invitation with your friends, colleagues, future entrepreneurs, startup founders & business owners. Your simple forward could inspire someone to take their next step.

Join Our Founders WhatsApp Community

Get entrepreneurship insights, business learning and future workshop updates.

https://chat.whatsapp.com/LXAaBAkSmNy15ri3bRgNhM

Your Dream. Your Business. Your Next Step Starts Here.

Email: contact@axioraglobalsolutions.com (mailto:contact@axioraglobalsolutions.com)

Phone: +91 77310 89602

Axiora Global Solutions Pvt. Ltd.
If you would rather not receive emails like this, you can unsubscribe here ({{unsubscribe_url}}).
TXT
        ];
    }

    if (!empty($orig['html_content'])) {
        $stmtRestore = $db->prepare("UPDATE campaign_templates 
                                      SET subject_default = :subj, 
                                          html_content = :html, 
                                          plain_text_content = :plain 
                                      WHERE name LIKE '%Workshop%' OR template_id = 3");
        $stmtRestore->execute([
            ':subj'  => $orig['subject_default'],
            ':html'  => $orig['html_content'],
            ':plain' => $orig['plain_text_content']
        ]);
        echo "  + Restored original Workshop Invite template content (subject, html, plain text).\n";
    }

    // 3. Revert migration record if schema_migrations table exists
    $chk = $db->query("SHOW TABLES LIKE 'schema_migrations'")->fetch();
    if ($chk) {
        $db->exec("DELETE FROM schema_migrations WHERE version LIKE '%0065%'");
        echo "  + Migration 0065 tracking entry removed from schema_migrations.\n";
    }

    echo "Migration 0065 rollback completed safely and surgically.\n";
} catch (Throwable $e) {
    echo "  ! Rollback error: " . $e->getMessage() . "\n";
    exit(1);
}
