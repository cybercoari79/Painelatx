<?php
require_once __DIR__.'/includes/bootstrap.php';
redirect(isLoggedIn()?'/atx/index.php':'/admin/login.php');