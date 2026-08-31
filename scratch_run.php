<?php
require 'config.php';
$db = getDB();
$sql = file_get_contents('database/ai_limits.sql');
$db->exec($sql);
echo 'Success';
