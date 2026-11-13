<?php
// Migration 0070: Align campaigns with 'noreply@axiora.in' to verified SMTP sender

try {
    $db->exec("UPDATE campaigns_broadcast 
               SET reply_to = CASE 
                   WHEN reply_to IS NULL OR reply_to = '' THEN from_email 
                   ELSE reply_to 
               END,
               from_email = 'contacts@axioraglobalsolutions.com'
               WHERE from_email = 'noreply@axiora.in'");
} catch (Throwable $e) {}
