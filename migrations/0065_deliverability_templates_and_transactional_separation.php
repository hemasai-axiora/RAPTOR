<?php
/**
 * Raptor CRM Migration: 0065_deliverability_templates_and_transactional_separation.php
 * 
 * 1. Seeds dedicated transactional email templates (Welcome/Registration, Password Reset OTP, Order Confirmation, Admin Alerts)
 * 2. Corrects existing marketing templates to eliminate spam keywords ("FREE") and fix URL display mismatch (phishing prevention)
 * 3. Enforces intent separation between operational and promotional emails
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Model.php';
require_once dirname(__DIR__) . '/app/models/CampaignTemplate.php';

echo "Running Migration 0065: Deliverability Templates & Transactional Separation...\n";

try {
    $templateModel = new CampaignTemplate();
    $templateModel->ensurePrebuiltTemplates();
    echo "  + Prebuilt transactional and clean marketing templates seeded successfully.\n";
    echo "Migration 0065 completed successfully.\n";
} catch (Throwable $e) {
    echo "  ! Migration 0065 error: " . $e->getMessage() . "\n";
    exit(1);
}

