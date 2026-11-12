<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {http_response_code(405);header('Allow: GET');exit(json_encode(['error'=>'Method not allowed']));}
if (session_status() !== PHP_SESSION_ACTIVE) {
  ini_set('session.cookie_httponly','1');ini_set('session.cookie_samesite','Lax');
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')ini_set('session.cookie_secure','1');
  session_start();
}
if (empty($_SESSION['mephys_assistant_csrf']))$_SESSION['mephys_assistant_csrf']=bin2hex(random_bytes(24));
echo json_encode(['csrf_token'=>$_SESSION['mephys_assistant_csrf']]);
