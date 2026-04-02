<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect(string $url): void
{
    header("Location: {$url}");
    exit;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (($_SESSION['user']['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Access denied. Admins only.');
    }
}

function refreshSessionUser(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare('SELECT user_id, first_name, last_name, email, username, user_role, created FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user'] = $user;
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
