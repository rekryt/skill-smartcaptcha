<?php
// Локальный мок эндпоинта /validate Yandex SmartCaptcha для юнит- и смоук-тестов.
// Подробности: ../docs/server-validation.md (раздел «Локальное тестирование»).
//
// Запуск:  php -S 127.0.0.1:8099 mock-validate.php
// В тестируемом коде подмени URL https://smartcaptcha.yandexcloud.net/validate
// на http://127.0.0.1:8099/validate (URL проверки сделай конфигурируемым).
//
// Сценарий выбирается ЗНАЧЕНИЕМ параметра token (формы ответов — дословно из
// официальной документации, см. ../references/validate-api.md):
//   token-ok         → 200 {"status":"ok","message":"","host":"example.com"}
//   token-ok-port    → 200 {"status":"ok","message":"","host":"example.com:8080"}
//   token-ok-nohost  → 200 {"status":"ok","message":"","host":""}
//   token-robot      → 200 {"status":"failed","message":""}
//   token-expired    → 200 {"status":"failed","message":"Invalid or expired Token."}
//   token-http-500   → 500 без JSON            (ветка fail-open)
//   token-bad-json   → 200 с не-JSON телом      (ветка fail-open)
//   token-timeout    → ответ через 3 секунды    (ветка fail-open по таймауту)
//   любой другой     → 200 {"status":"failed","message":"Invalid or expired Token."}
// Запрос без secret  → 200 {"status":"failed","message":"Authentication failed. Secret has not provided."}

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Мок принимает и POST-форму (как реальный сервис), и query-параметры (для удобства curl).
$secret = $_POST['secret'] ?? $_GET['secret'] ?? '';
$token  = $_POST['token'] ?? $_GET['token'] ?? '';

function respond(int $httpCode, string $body): never
{
    http_response_code($httpCode);
    echo $body;
    exit;
}

if ($secret === '') {
    respond(200, '{"status": "failed", "message": "Authentication failed. Secret has not provided."}');
}

switch ($token) {
    case 'token-ok':
        respond(200, '{"status": "ok", "message": "", "host": "example.com"}');
    case 'token-ok-port':
        respond(200, '{"status": "ok", "message": "", "host": "example.com:8080"}');
    case 'token-ok-nohost':
        respond(200, '{"status": "ok", "message": "", "host": ""}');
    case 'token-robot':
        respond(200, '{"status": "failed", "message": ""}');
    case 'token-http-500':
        header('Content-Type: text/plain; charset=utf-8');
        respond(500, 'Internal Server Error');
    case 'token-bad-json':
        header('Content-Type: text/html; charset=utf-8');
        respond(200, '<html>это не JSON</html>');
    case 'token-timeout':
        sleep(3); // дольше типового таймаута 1 с — проверка ветки fail-open
        respond(200, '{"status": "ok", "message": "", "host": "example.com"}');
    case 'token-expired':
    default:
        respond(200, '{"status": "failed", "message": "Invalid or expired Token."}');
}
