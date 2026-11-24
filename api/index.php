<?php
/**
 * DANATRAIL — Vercel Serverless PHP Front Controller
 * Menangani routing seluruh file PHP multi-folder dan menjaga persistensi $_SESSION di serverless.
 */

class VercelCookieSessionHandler implements SessionHandlerInterface {
    private $cookieName = 'danatrail_sess';
    private $secret;

    public function __construct($secret) {
        $this->secret = hash('sha256', (string)$secret, true);
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        if (empty($_COOKIE[$this->cookieName])) {
            return '';
        }
        $raw = $_COOKIE[$this->cookieName];
        $parts = explode('.', $raw, 2);
        if (count($parts) !== 2) {
            return '';
        }
        [$payloadB64, $sigHex] = $parts;
        $expected = hash_hmac('sha256', $payloadB64, $this->secret);
        if (!hash_equals($expected, $sigHex)) {
            return '';
        }
        $decoded = base64_decode(strtr($payloadB64, '-_', '+/'), true);
        if ($decoded === false || strlen($decoded) < 17) {
            return '';
        }
        $iv = substr($decoded, 0, 16);
        $cipher = substr($decoded, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $this->secret, OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }

    public function write(string $id, string $data): bool {
        if (headers_sent()) {
            return true;
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (stripos($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '', 'https') !== false);

        if ($data === '') {
            setcookie($this->cookieName, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            return true;
        }

        $iv = random_bytes(16);
        $cipher = openssl_encrypt($data, 'AES-256-CBC', $this->secret, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return false;
        }
        $payloadB64 = rtrim(strtr(base64_encode($iv . $cipher), '+/', '-_'), '=');
        $sigHex = hash_hmac('sha256', $payloadB64, $this->secret);
        $cookieVal = $payloadB64 . '.' . $sigHex;

        setcookie($this->cookieName, $cookieVal, [
            'expires'  => time() + 86400 * 7,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$this->cookieName] = $cookieVal;
        return true;
    }

    public function destroy(string $id): bool {
        if (!headers_sent()) {
            setcookie($this->cookieName, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        unset($_COOKIE[$this->cookieName]);
        return true;
    }

    public function gc(int $max_lifetime): int|false {
        return 0;
    }
}

// Buffer output agar session cookie bisa ditulis sebelum header terkirim (termasuk saat exit/redirect)
ob_start();

$sessionSecret = getenv('APP_SECRET')
    ?: getenv('MIDTRANS_SERVER_KEY')
    ?: getenv('DB_PASS')
    ?: 'danatrail-vercel-demo-secret-v1';

$handler = new VercelCookieSessionHandler($sessionSecret);
session_set_save_handler($handler, true);

register_shutdown_function(function () {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
});

// Tentukan target file PHP berdasarkan URL request
$rootDir = realpath(dirname(__DIR__));
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$reqPath = rawurldecode($reqPath);

if ($reqPath === '/' || $reqPath === '') {
    $reqPath = '/index.php';
} elseif (substr($reqPath, -1) === '/') {
    $reqPath .= 'index.php';
}

// Blokir akses langsung ke folder sensitif
if (preg_match('#^/(config|database|api)(/|$)#i', $reqPath) || strpos($reqPath, '..') !== false) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
}

$targetFile = realpath($rootDir . $reqPath);

if (!$targetFile || strpos($targetFile, $rootDir) !== 0 || !is_file($targetFile) || strtolower(pathinfo($targetFile, PATHINFO_EXTENSION)) !== 'php') {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>404 — DANATRAIL</title></head><body style="font-family:sans-serif;text-align:center;padding:60px;"><h2>404 — Halaman Tidak Ditemukan</h2><p><a href="/">Kembali ke Beranda DANATRAIL</a></p></body></html>';
    exit;
}

// Sinkronkan environment PHP agar relative include & redirect berfungsi identik dengan Apache
$_SERVER['SCRIPT_FILENAME'] = $targetFile;
$_SERVER['SCRIPT_NAME']     = $reqPath;
$_SERVER['PHP_SELF']        = $reqPath;

chdir(dirname($targetFile));
require $targetFile;

