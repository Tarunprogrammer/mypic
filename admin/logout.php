<?php
/**
 * Admin Logout Redirector
 */
require_once __DIR__ . '/../config/config.php';
header('Location: ' . BASE_URL . '/api/auth.php?action=logout&redirect=1');
exit;
