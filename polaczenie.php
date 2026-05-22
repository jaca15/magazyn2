<?php
// polaczenie.php - połączenie z bazą danych (TCP na porcie 3307)
// Dostosuj user/haslo jeśli potrzeba
function load_db_env_from_file_if_missing(): void {
    $hasDbName = getenv('DB_NAME');
    $hasMysqlDatabase = getenv('MYSQL_DATABASE');
    if (($hasDbName !== false && $hasDbName !== '') || ($hasMysqlDatabase !== false && $hasMysqlDatabase !== '')) {
        return;
    }

    $envCandidates = [__DIR__ . '/.env', __DIR__ . '/env'];
    foreach ($envCandidates as $envPath) {
        if (!is_readable($envPath)) {
            continue;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            continue;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }
            if (strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }

            $existing = getenv($key);
            if ($existing !== false && $existing !== '') {
                continue;
            }

            $valueLength = strlen($value);
            if ($valueLength >= 2) {
                $first = $value[0];
                $last = $value[$valueLength - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $hasDbName = getenv('DB_NAME');
        $hasMysqlDatabase = getenv('MYSQL_DATABASE');
        if (($hasDbName !== false && $hasDbName !== '') || ($hasMysqlDatabase !== false && $hasMysqlDatabase !== '')) {
            break;
        }
    }
}

load_db_env_from_file_if_missing();

$host = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: '127.0.0.1'; // użyj 127.0.0.1 zamiast 'localhost' aby wymusić TCP
$port = (int)(getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: 3307); // ustawiony port
$baza = getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: '';
$user = getenv('DB_USER') ?: getenv('MYSQL_USER') ?: '';
$haslo = getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: ''; // ustaw hasło
$charset = 'utf8mb4';

if ($baza === '') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Błąd konfiguracji bazy danych: brak nazwy bazy. Ustaw DB_NAME lub MYSQL_DATABASE (opcjonalnie także DB_HOST, DB_PORT, DB_USER, DB_PASSWORD).";
    exit;
}

$dsn = "mysql:host={$host};port={$port};dbname={$baza};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $haslo, $options);
} catch (PDOException $e) {
    error_log('Błąd połączenia z bazą danych: ' . $e->getMessage());
    echo 'Błąd połączenia z bazą danych. Skontaktuj się z administratorem.';
    exit;
}
?>
