<?php
/**
 * LOGOUT - Destroy session dan redirect ke login
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

logoutUser();
setFlash('success', 'Anda telah berhasil logout.');
redirect(BASE_URL . '/login.php');
