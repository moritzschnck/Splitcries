<?php
// app/auth.php

function require_login(): void {
  if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
  }
}

function current_user_id(): int {
  return (int)($_SESSION['user_id'] ?? 0);
}

function current_username(): string {
  return (string)($_SESSION['username'] ?? '');
}