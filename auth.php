<?php
// Root shim for auth — forwards to php/auth.php implementation
// This allows requests to /myportfolio/auth.php to work while keeping
// the canonical implementation in the php/ subfolder.
require_once __DIR__ . '/php/auth.php';
