<?php

$referralCode = strtoupper(trim((string) ($_GET['ref'] ?? '')));
$path = (string) ($_GET['path'] ?? 'register');

if ($referralCode !== '' && preg_match('/^[A-Z0-9_-]{1,64}$/', $referralCode) === 1) {
    $destination = 'https://tlstore.org/ref/'.rawurlencode($referralCode);
} else {
    $path = in_array($path, ['register', 'login'], true) ? $path : 'register';
    $destination = 'https://web.tlstore.org/'.$path;
}

header('Location: '.$destination, true, 302);
exit;
