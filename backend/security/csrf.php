<?php
/**
 * UPTEC - Sistema de Control de Cursos v2.0
 * Proteccion CSRF - OWASP Synchronizer Token Pattern
 */

declare(strict_types=1);

if (!defined('UPTEC_ACCESS')) {
    define('UPTEC_ACCESS', true);
}

class CSRF
{
    private const TOKEN_NAME = '_csrf_token';
    private const FORM_FIELD = '_csrf_token';
    private const HEADER_NAME = 'X-CSRF-Token';
    private const TOKEN_LIFETIME = 3600; // 1 hora

    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
                    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
            ini_set('session.cookie_secure', $isHttps ? '1' : '0');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_samesite', 'Lax');
            session_start();
        }

        if (!self::hasValidToken()) {
            self::generateToken();
        }
    }

    public static function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));

        $_SESSION[self::TOKEN_NAME] = [
            'token' => $token,
            'time' => time()
        ];

        return $token;
    }

    public static function getToken(): ?string
    {
        self::init();
        return $_SESSION[self::TOKEN_NAME]['token'] ?? null;
    }

    public static function hasValidToken(): bool
    {
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }

        $tokenData = $_SESSION[self::TOKEN_NAME];

        if (!isset($tokenData['time']) || (time() - $tokenData['time']) > self::TOKEN_LIFETIME) {
            return false;
        }

        return isset($tokenData['token']) && strlen($tokenData['token']) === 64;
    }

    public static function validate(?string $token = null): bool
    {
        self::init();

        if ($token === null) {
            $token = self::getSubmittedToken();
        }

        if (!self::hasValidToken() || $token === null) {
            return false;
        }

        return hash_equals($_SESSION[self::TOKEN_NAME]['token'], $token);
    }

    public static function getSubmittedToken(): ?string
    {
        if (isset($_POST[self::FORM_FIELD])) {
            return $_POST[self::FORM_FIELD];
        }

        if (isset($_GET[self::FORM_FIELD])) {
            return $_GET[self::FORM_FIELD];
        }

        $headers = getallheaders();
        if (isset($headers[self::HEADER_NAME])) {
            return $headers[self::HEADER_NAME];
        }

        $headerLower = strtolower(self::HEADER_NAME);
        foreach ($headers as $name => $value) {
            if (strtolower($name) === $headerLower) {
                return $value;
            }
        }

        return null;
    }

    public static function field(bool $echo = true): string
    {
        $token = self::getToken();
        $html = sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::FORM_FIELD,
            htmlspecialchars($token ?? '', ENT_QUOTES, 'UTF-8')
        );

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public static function metaTag(bool $echo = true): string
    {
        $token = self::getToken();
        $html = sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($token ?? '', ENT_QUOTES, 'UTF-8')
        );

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public static function verify(bool $jsonResponse = false): void
    {
        if (!self::validate()) {
            error_log("[UPTEC] Posible ataque CSRF desde: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

            if ($jsonResponse || self::isAjaxRequest()) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Token CSRF invalido',
                    'code' => 'CSRF_INVALID'
                ]);
            } else {
                http_response_code(403);
                echo '<h1>403 Forbidden</h1><p>Token de seguridad invalido.</p>';
            }

            exit;
        }
    }

    private static function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public static function invalidate(): void
    {
        unset($_SESSION[self::TOKEN_NAME]);
    }

    public static function regenerate(): string
    {
        self::invalidate();
        return self::generateToken();
    }
}

function csrf_field(): void { CSRF::field(); }
function csrf_meta(): void { CSRF::metaTag(); }
function csrf_check(): bool { return CSRF::validate(); }
function csrf_token(): ?string { return CSRF::getToken(); }
