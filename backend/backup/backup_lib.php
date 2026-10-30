<?php
function car_sales_backup_dir(): string {
    $dir = __DIR__ . '/../backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function create_database_backup(mysqli $conn): array {
    $dir = car_sales_backup_dir();
    $file = 'car_sales_system_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $path = $dir . '/' . $file;

    $tables = [];
    $res = $conn->query('SHOW TABLES');
    if (!$res) {
        return ['file' => '', 'path' => '', 'error' => 'Could not read database tables.'];
    }

    while ($row = $res->fetch_array()) {
        $tables[] = $row[0];
    }

    $out = "-- Car Sales System Backup
";
    $out .= "-- Generated: " . date('Y-m-d H:i:s') . "
";
    $out .= "SET FOREIGN_KEY_CHECKS=0;

";

    foreach ($tables as $table) {
        $safeTable = str_replace('`', '``', $table);
        $createResult = $conn->query("SHOW CREATE TABLE `$safeTable`");
        if (!$createResult) {
            continue;
        }

        $cr = $createResult->fetch_assoc();
        if (!$cr || empty($cr['Create Table'])) {
            continue;
        }

        $out .= "DROP TABLE IF EXISTS `$safeTable`;
" . $cr['Create Table'] . ";

";

        $data = $conn->query("SELECT * FROM `$safeTable`");
        if ($data) {
            while ($r = $data->fetch_assoc()) {
                $cols = array_map(function ($c) {
                    return '`' . str_replace('`', '``', $c) . '`';
                }, array_keys($r));

                $vals = array_map(function ($v) use ($conn) {
                    return $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
                }, array_values($r));

                $out .= 'INSERT INTO `' . $safeTable . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");
";
            }
        }
        $out .= "
";
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;
";
    @file_put_contents($path, $out);
    return ['file' => $file, 'path' => $path];
}
?>
