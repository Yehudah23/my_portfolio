<?php
// Root shim for admin-config — forwards to php/admin-config.php
// Keeps configuration centralized in php/ while allowing root scripts
// that expect /admin-config.php to function.
require_once __DIR__ . '/php/admin-config.php';
