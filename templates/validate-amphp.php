<?php
// Серверная проверка токена Yandex SmartCaptcha в event loop (AMPHP v3).
// Подробности: ../docs/server-validation.md (раздел «Асинхронные рантаймы»).
//
// Зависимости (composer require): amphp/http-client:^5
// Требования: PHP 8.1+, revolt/event-loop (ставится транзитивно).
//
// Чем отличается от ../templates/validate.php (cURL):
// - curl_exec блокирует весь процесс и недопустим внутри event loop;
//   amphp/http-client выполняет запрос неблокирующе (Fibers) — код остаётся линейным;
// - семантику CURLOPT_TIMEOUT («весь запрос за N секунд») воспроизводит ЕДИНЫЙ
//   TimeoutCancellation, переданный и в request(), и в buffer() — таймаут-бюджет
//   покрывает соединение, заголовки и чтение тела;
// - ветки ошибок cURL → исключения: сетевые (HttpException/StreamException),
//   таймаут (CancelledException), битый JSON (JsonException) — все дают fail-open.
//
// Семантика (по официальной документации, как у синхронного шаблона):
// - POST https://smartcaptcha.yandexcloud.net/validate (x-www-form-urlencoded);
// - fail-open: HTTP != 200, сетевая ошибка, таймаут, неразборчивый JSON → true + лог;
// - решение только по полю status ("ok" / "failed"); message — только для логов;
// - токен одноразовый, живёт 5 минут.

declare(strict_types=1);

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\TimeoutCancellation;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

const SMARTCAPTCHA_VALIDATE_URL = 'https://smartcaptcha.yandexcloud.net/validate';

/**
 * Проверяет токен SmartCaptcha неблокирующе. Возвращает true — пропустить пользователя.
 *
 * Вызывается из любого места внутри event loop (обработчик amphp/http-server,
 * Amp\async(...) и т.п.) — текущий фибер приостанавливается, loop продолжает
 * обслуживать другие запросы.
 *
 * @param string          $token   Одноразовый токен (поле smart-token).
 * @param string|null     $ip      IP пользователя; передавай достоверный или null
 *                                 (лучше null, чем IP собственного прокси).
 * @param string|null     $secret  Ключ сервера; по умолчанию — env SMARTCAPTCHA_SERVER_KEY.
 * @param float           $timeout Таймаут-бюджет на весь запрос, секунды.
 * @param LoggerInterface $logger  PSR-3 логгер (в AMPHP-приложении — amphp/log,
 *                                 синхронная запись в файл блокирует loop).
 *
 * @return bool true — status === "ok" или сервис недоступен (fail-open);
 *              false — это робот (status === "failed").
 */
function check_captcha_async(
    string $token,
    ?string $ip = null,
    ?string $secret = null,
    float $timeout = 1.0,
    LoggerInterface $logger = new NullLogger(),
): bool {
    static $client = null;
    /** @var HttpClient $client — один клиент на процесс: пул соединений, keep-alive */
    $client ??= HttpClientBuilder::buildDefault();

    $secret ??= getenv('SMARTCAPTCHA_SERVER_KEY') ?: '';
    if ($secret === '') {
        // Ошибка конфигурации должна падать громко: пустой/неверный secret даёт
        // "status": "failed" всем пользователям — тихий отказ всем живым людям.
        throw new RuntimeException('SMARTCAPTCHA_SERVER_KEY не задан: укажите ключ сервера (ysc2_...)');
    }

    $fields = ['secret' => $secret, 'token' => $token];
    if ($ip !== null && $ip !== '') {
        $fields['ip'] = $ip;
    }

    $request = new Request(SMARTCAPTCHA_VALIDATE_URL, 'POST');
    $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
    $request->setBody(http_build_query($fields));

    // Единый бюджет на соединение + заголовки + чтение тела (аналог CURLOPT_TIMEOUT).
    $cancellation = new TimeoutCancellation($timeout);

    try {
        $response = $client->request($request, $cancellation);
        // Тело обязательно дочитываем (buffer) — незакрытые тела блокируют
        // переиспользование соединений; cancellation передаём и сюда.
        $body = $response->getBody()->buffer($cancellation);
    } catch (CancelledException $e) {
        $logger->error('SmartCaptcha fail-open: таймаут запроса к /validate', ['exception' => $e]);
        return true;
    } catch (HttpException|\Amp\ByteStream\StreamException $e) {
        $logger->error('SmartCaptcha fail-open: сетевая ошибка', ['exception' => $e]);
        return true;
    }

    if ($response->getStatus() !== 200) {
        $logger->error('SmartCaptcha fail-open: HTTP {code}', ['code' => $response->getStatus(), 'body' => $body]);
        return true;
    }

    try {
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        $logger->error('SmartCaptcha fail-open: неразборчивый JSON', ['body' => $body]);
        return true;
    }

    $status = $data['status'] ?? null;
    if ($status !== 'ok') {
        // message — только в лог; решения по нему не принимаем.
        $logger->info('SmartCaptcha: отказ', ['status' => $status, 'message' => $data['message'] ?? '']);
    }

    return $status === 'ok';
}
