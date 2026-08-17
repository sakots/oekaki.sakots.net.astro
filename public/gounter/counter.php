<?php
declare(strict_types=1);

// がうんたー by さこつ

// 設定
const FILE_NAME = 'count.db'; // カウントを保存するデータベース名
const DATABASE_DIRECTORY = 'data'; // データベースを保存するディレクトリ名
const DATABASE_DIRECTORY_PERMISSIONS = 0700; // Unix系のディレクトリ権限
const DATABASE_FILE_PERMISSIONS = 0600; // Unix系のデータベースファイル権限
const TOTAL_MINIMUM_DIGITS = 6; // 累計カウントの桁数の最小値
const DAILY_MINIMUM_DIGITS = 3; // 今日・昨日カウントの桁数の最小値
const CUSTOM_MAXIMUM_DIGITS = 16; // 指定できる数字の最大桁数
const IMAGE_DIRECTORY = 'images'; // 数字画像を保存したディレクトリ

/**
 * 指定された期間のカウンター画像を出力する。
 */
function outputCounter(string $period): void
{
  try {
    $increment = match ($period) {
      'total' => true,
      'today', 'yesterday' => false,
      default => throw new InvalidArgumentException('Unknown counter period.'),
    };

    $pdo = init();
    $counts = updateCounters($pdo, $increment);

    $value = match ($period) {
      'total' => $counts['total'],
      'today' => $counts['today'],
      'yesterday' => $counts['yesterday'],
    };
    $minimumDigits = $period === 'total'
      ? TOTAL_MINIMUM_DIGITS
      : DAILY_MINIMUM_DIGITS;

    outputCounterImage($value, $minimumDigits);
  } catch (Throwable $exception) {
    error_log('counter: ' . $exception->getMessage());

    if (!headers_sent()) {
      http_response_code(500);
      header('Content-Type: text/plain; charset=UTF-8');
      header('Cache-Control: no-store');
    }

    echo 'Failed to create the access counter image.';
  }
}

/**
 * URLで指定された数字をカウンター画像として出力する。
 */
function outputNumber(string $number): void
{
  if (!preg_match('/\A[0-9]+\z/D', $number) || strlen($number) > CUSTOM_MAXIMUM_DIGITS) {
    if (!headers_sent()) {
      http_response_code(400);
      header('Content-Type: text/plain; charset=UTF-8');
      header('Cache-Control: no-store');
    }

    echo 'Specify a number with 1 to ' . CUSTOM_MAXIMUM_DIGITS . ' digits.';
    return;
  }

  try {
    outputCounterImage($number, strlen($number));
  } catch (Throwable $exception) {
    error_log('counter: ' . $exception->getMessage());

    if (!headers_sent()) {
      http_response_code(500);
      header('Content-Type: text/plain; charset=UTF-8');
      header('Cache-Control: no-store');
    }

    echo 'Failed to create the number image.';
  }
}

/**
 * データベースを初期化して接続を返す。
 */
function init(): PDO {
  $databasePath = prepareDatabasePath();
  $pdo = new PDO('sqlite:' . $databasePath);
  applyUnixPermissions($databasePath, DATABASE_FILE_PERMISSIONS);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $pdo->exec('PRAGMA busy_timeout = 5000');

  $pdo->exec(
    'CREATE TABLE IF NOT EXISTS counts (
      id INTEGER PRIMARY KEY AUTOINCREMENT, -- ID
      total INTEGER NOT NULL DEFAULT 0, -- 累計カウンター
      today INTEGER NOT NULL DEFAULT 0, -- 今日の
      yesterday INTEGER NOT NULL DEFAULT 0, -- 昨日の
      host TEXT NOT NULL DEFAULT \'\', -- 連続カウント防止用host
      last_date TEXT NOT NULL DEFAULT \'\' -- 昨日の日付
    )'
  );

  $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
  try {
    migrateDatabase($pdo);

    $rowExists = (bool) $pdo->query('SELECT 1 FROM counts LIMIT 1')->fetchColumn();
    if (!$rowExists) {
      $statement = $pdo->prepare(
        'INSERT INTO counts (total, today, yesterday, host, last_date) VALUES (0, 0, 0, :host, :last_date)'
      );
      $statement->execute([
        ':host' => '',
        ':last_date' => date('Y-m-d'),
      ]);
    }

    $pdo->exec('COMMIT');
  } catch (Throwable $exception) {
    $pdo->exec('ROLLBACK');
    throw $exception;
  }

  return $pdo;
}

/**
 * データベース保存ディレクトリを用意して保存先を返す。
 */
function prepareDatabasePath(): string
{
  $databaseDirectory = __DIR__ . DIRECTORY_SEPARATOR . DATABASE_DIRECTORY;

  if (!is_dir($databaseDirectory)) {
    if (!@mkdir($databaseDirectory, DATABASE_DIRECTORY_PERMISSIONS, true)
      && !is_dir($databaseDirectory)) {
      throw new RuntimeException('Failed to create the database directory.');
    }
  }

  applyUnixPermissions($databaseDirectory, DATABASE_DIRECTORY_PERMISSIONS);

  if (!is_writable($databaseDirectory)) {
    throw new RuntimeException('The database directory is not writable.');
  }

  $databasePath = $databaseDirectory . DIRECTORY_SEPARATOR . FILE_NAME;

  if (is_file($databasePath)) {
    applyUnixPermissions($databasePath, DATABASE_FILE_PERMISSIONS);
  }

  return $databasePath;
}

/**
 * Unix系環境だけファイルモードを設定する。
 * WindowsではchmodがNTFS ACLを設定できないため、web.configでHTTPアクセスを防ぐ。
 */
function applyUnixPermissions(string $path, int $permissions): void
{
  if (PHP_OS_FAMILY === 'Windows') {
    return;
  }

  if (!@chmod($path, $permissions)) {
    throw new RuntimeException('Failed to set secure filesystem permissions.');
  }
}

/**
 * 初期版のデータベースにも日付管理用の列を追加する。
 */
function migrateDatabase(PDO $pdo): void
{
  $columns = $pdo->query('PRAGMA table_info(counts)')->fetchAll();
  $columnNames = array_column($columns, 'name');

  if (!in_array('last_date', $columnNames, true)) {
    $pdo->exec("ALTER TABLE counts ADD COLUMN last_date TEXT NOT NULL DEFAULT ''");

    // 日付情報がなかった既存カウントは、移行した当日の値として引き継ぐ。
    $statement = $pdo->prepare(
      'UPDATE counts SET last_date = :last_date WHERE last_date = :empty_date'
    );
    $statement->execute([
      ':last_date' => date('Y-m-d'),
      ':empty_date' => '',
    ]);
  }
}

/**
 * 日付を更新し、必要な場合だけアクセス数を加算する。
 * 累計表示は直前と異なる接続元だけを加算し、今日・昨日表示は加算しない。
 *
 * @return array{total: int, today: int, yesterday: int}
 */
function updateCounters(PDO $pdo, bool $increment): array
{
  $transactionStarted = false;

  try {
    // 最初から書き込みロックを取って、同時アクセス時の加算漏れを防ぐ。
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    $transactionStarted = true;

    $row = $pdo->query(
      'SELECT id, total, today, yesterday, host, last_date FROM counts ORDER BY id LIMIT 1'
    )->fetch();

    if ($row === false) {
      throw new RuntimeException('The counter row does not exist.');
    }

    $todayDate = date('Y-m-d');
    $yesterdayDate = date('Y-m-d', strtotime('-1 day'));
    $storedDate = (string) $row['last_date'];

    $total = max(0, (int) $row['total']);
    $today = max(0, (int) $row['today']);
    $yesterday = max(0, (int) $row['yesterday']);
    $lastHost = (string) $row['host'];

    if ($storedDate !== $todayDate) {
      $yesterday = $storedDate === $yesterdayDate ? $today : 0;
      $today = 0;
      $lastHost = '';
    }

    $host = getClientHost();
    if ($increment && ($host === '' || !hash_equals($lastHost, $host))) {
      ++$total;
      ++$today;
      $lastHost = $host;
    }

    $statement = $pdo->prepare(
      'UPDATE counts SET total = :total, today = :today, yesterday = :yesterday, host = :host, last_date = :last_date WHERE id = :id'
    );
    $statement->execute([
      ':total' => $total,
      ':today' => $today,
      ':yesterday' => $yesterday,
      ':host' => $lastHost,
      ':last_date' => $todayDate,
      ':id' => (int) $row['id'],
    ]);

    $pdo->exec('COMMIT');
    $transactionStarted = false;

    return [
      'total' => $total,
      'today' => $today,
      'yesterday' => $yesterday,
    ];
  } catch (Throwable $exception) {
    if ($transactionStarted) {
      $pdo->exec('ROLLBACK');
    }

    throw $exception;
  }
}

/**
 * 連続アクセス判定に使う接続元アドレスを返す。
 */
function getClientHost(): string
{
  $host = $_SERVER['REMOTE_ADDR'] ?? '';
  if (!is_string($host)) {
    return '';
  }

  return substr($host, 0, 255);
}

/**
 * カウントを数字画像として連結して出力する。
 */
function outputCounterImage(int|string $count, int $minimumDigits): void
{
  $number = is_int($count) ? (string) max(0, $count) : $count;
  if (!preg_match('/\A[0-9]+\z/D', $number) || $minimumDigits < 1) {
    throw new InvalidArgumentException('The count and minimum digits must be positive numbers.');
  }

  $digits = str_pad($number, $minimumDigits, '0', STR_PAD_LEFT);
  $images = [];
  $width = 0;
  $height = 0;

  try {
    foreach (str_split($digits) as $digit) {
      $path = __DIR__ . DIRECTORY_SEPARATOR . IMAGE_DIRECTORY
        . DIRECTORY_SEPARATOR . $digit . '.png';
      $image = imagecreatefrompng($path);

      if ($image === false) {
        throw new RuntimeException('Failed to load digit image: ' . $path);
      }

      $images[] = $image;
      $width += imagesx($image);
      $height = max($height, imagesy($image));
    }

    $counterImage = imagecreatetruecolor($width, $height);
    if ($counterImage === false) {
      throw new RuntimeException('Failed to create the counter image.');
    }

    imagealphablending($counterImage, false);
    imagesavealpha($counterImage, true);
    $transparent = imagecolorallocatealpha($counterImage, 0, 0, 0, 127);
    imagefill($counterImage, 0, 0, $transparent);
    imagealphablending($counterImage, true);

    $x = 0;
    foreach ($images as $image) {
      imagecopy(
        $counterImage,
        $image,
        $x,
        0,
        0,
        0,
        imagesx($image),
        imagesy($image)
      );
      $x += imagesx($image);
    }

    if (!headers_sent()) {
      header('Content-Type: image/png');
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('Pragma: no-cache');
    }

    if (!imagepng($counterImage)) {
      throw new RuntimeException('Failed to output the counter image.');
    }
  } finally {
    foreach ($images as $image) {
      imagedestroy($image);
    }

    if (isset($counterImage) && $counterImage instanceof GdImage) {
      imagedestroy($counterImage);
    }
  }
}
