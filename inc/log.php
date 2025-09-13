<?php
function log_error($msg) {
    $file = __DIR__ . '/../logs/errors.log';
    $date = date("Y-m-d H:i:s");
    file_put_contents($file, "[$date] $msg\n", FILE_APPEND);
}
