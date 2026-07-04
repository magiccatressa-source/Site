<?php
/**
 * Проверка подписи вебхуков Prodamus/Payform.
 *
 * Алгоритм по документации Prodamus (help.prodamus.ru): рекурсивная сортировка
 * массива данных по ключам + JSON-сериализация + HMAC-SHA256 с секретным ключом
 * платёжной страницы. Подпись передаётся в заголовке запроса "Sign".
 *
 * ВНИМАНИЕ: это реконструкция алгоритма по открытой документации, а не официальный
 * файл Hmac.php от Prodamus (он не публикуется в открытом доступе). Если после
 * включения проверки в error_log и в Telegram начнут приходить отказы на реальные
 * оплаты — значит точный формат сериализации отличается, и это место надо сверить
 * с личным кабинетом Prodamus (там можно отправить тестовый вебхук и посмотреть
 * исходящий запрос) или с их поддержкой.
 */
class ProdamusHmac {
    public static function create(array $data, string $secretKey): string {
        unset($data['signature'], $data['sign']);
        $sorted = self::recursiveKsort($data);
        $json = json_encode($sorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash_hmac('sha256', (string)$json, $secretKey);
    }

    public static function verify(array $data, string $secretKey, ?string $signature): bool {
        if (!$signature) return false;
        return hash_equals(self::create($data, $secretKey), $signature);
    }

    private static function recursiveKsort($data) {
        if (is_array($data)) {
            ksort($data, SORT_REGULAR);
            foreach ($data as $key => $value) {
                $data[$key] = self::recursiveKsort($value);
            }
        }
        return $data;
    }
}
