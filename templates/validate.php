<?php
// Серверная проверка токена Yandex SmartCaptcha (PHP + cURL). Подробности: ../docs/server-validation.md
//
// Переиспользуемый модуль:
//   require_once __DIR__ . '/validate.php';
//   $passed = check_captcha($_POST['smart-token'], $_SERVER['REMOTE_ADDR']);
//
// Ключ сервера берется из переменной окружения SMARTCAPTCHA_SERVER_KEY.
// Отсутствие ключа — громкая ошибка конфигурации (RuntimeException), а не тихий
// фолбэк: с плейсхолдером /validate отвечал бы failed всем пользователям.
//
// Семантика (по официальной документации):
// - POST https://smartcaptcha.yandexcloud.net/validate, формат x-www-form-urlencoded;
// - таймаут 1 секунда (CURLOPT_TIMEOUT), чтобы не задерживать обработку запроса;
// - fail-open: при HTTP-коде != 200, сетевой ошибке или неразборчивом JSON
//   пользователь пропускается (рекомендация сервиса — обрабатывать ошибки
//   HTTP-протокола как ответ "status": "ok");
// - решение принимается только по полю status ("ok" / "failed");
//   поле message — только для диагностики, не сравнивайте его в коде.
//
// Важно: токен одноразовый и живет 5 минут. Повторная валидация того же токена
// вернет "status": "failed" с сообщением "Invalid or expired Token".

const SMARTCAPTCHA_VALIDATE_URL = 'https://smartcaptcha.yandexcloud.net/validate';

/**
 * Проверяет токен SmartCaptcha. Возвращает true — пропустить пользователя.
 *
 * @param string      $token  Одноразовый токен из формы (например, $_POST['smart-token']).
 * @param string|null $ip     IP-адрес пользователя. Необязателен, но передавайте его —
 *                            это улучшает качество работы SmartCaptcha. Способ получения
 *                            зависит от вашего прокси (например, $_SERVER['REMOTE_ADDR']).
 * @param string|null $secret Ключ сервера. По умолчанию — из переменной окружения
 *                            SMARTCAPTCHA_SERVER_KEY.
 * @param int         $timeout Таймаут запроса в секундах.
 *
 * @return bool true — проверка пройдена (status === "ok") или сервис недоступен
 *              (fail-open); false — это робот (status === "failed").
 */
function check_captcha(string $token, ?string $ip = null, ?string $secret = null, int $timeout = 1): bool
{
    if ($secret === null) {
        $secret = getenv('SMARTCAPTCHA_SERVER_KEY') ?: '';
    }
    if ($secret === '') {
        // Ошибка конфигурации должна падать громко: неверный/пустой secret приводит
        // к "status": "failed" для всех пользователей — тихий отказ всем живым людям.
        throw new RuntimeException('SMARTCAPTCHA_SERVER_KEY не задан: укажите ключ сервера (ysc2_...)');
    }

    $args = [
        'secret' => $secret,
        'token'  => $token,
    ];
    if ($ip !== null && $ip !== '') {
        $args['ip'] = $ip;
    }

    $ch = curl_init(SMARTCAPTCHA_VALIDATE_URL);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $server_output = curl_exec($ch);
    $curl_error = curl_error($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($server_output === false) {
        // Сетевая ошибка или таймаут — пропускаем пользователя (fail-open),
        // чтобы сбой сервиса не блокировал реальных людей.
        error_log("Allow access due to an error: $curl_error");
        return true;
    }

    if ($httpcode !== 200) {
        error_log("Allow access due to an error: code=$httpcode; message=$server_output");
        return true;
    }

    $resp = json_decode($server_output);
    if (!is_object($resp) || !isset($resp->status)) {
        error_log("Error parsing response: $server_output");
        return true;
    }

    return $resp->status === 'ok';
}

// Мини-пример использования:
// $token = $_POST['smart-token'] ?? '<токен>';
// if (check_captcha($token, $_SERVER['REMOTE_ADDR'] ?? null)) {
//     echo "Passed\n";
// } else {
//     echo "Robot\n";
// }
