<?php

namespace Services;

class Session
{
    private function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function set($key, $value)
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function get($key, $default = null)
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function unset($key)
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function destroy()
    {
        $this->start();

        $_SESSION = [];

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            true,
            true
        );

        session_destroy();
    }

    public function regenerate()
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function setFlash($type, $message)
    {
        $this->start();

        if (is_array($message)) {
            $message = implode("\n", $message);
        }

        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    public function getFlash()
    {
        $this->start();

        $flash = $_SESSION['flash'] ?? null;

        if (!is_array($flash)) {
            return null;
        }

        unset($_SESSION['flash']);

        return [
            'type' => $flash['type'] ?? null,
            'message' => $flash['message'] ?? null,
        ];
    }

    public function getCsrf()
    {
        $this->start();

        $token = $_SESSION['csrf'] ?? null;

        if (is_string($token)) {
            return $token;
        }

        $token = bin2hex(random_bytes(32));

        $_SESSION['csrf'] = $token;

        return $token;
    }

    public function validateCsrf($token)
    {
        $this->start();

        $stored = $_SESSION['csrf'] ?? null;

        if (!is_string($stored)) {
            return false;
        }

        return hash_equals($stored, (string) $token);
    }
}
