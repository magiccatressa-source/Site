<?php
/**
 * Интеграция с платежной системой Prodamus
 */

class Prodamus {
    private $merchantId;
    private $apiKey;
    private $secretKey;
    private $baseUrl = 'https://api.prodamus.ru';

    public function __construct() {
        $this->merchantId = setting('prodamus_merchant_id');
        $this->apiKey = setting('prodamus_api_key');
        $this->secretKey = setting('prodamus_secret_key');
    }

    /**
     * Проверка, настроена ли интеграция
     */
    public function isConfigured(): bool {
        return !empty($this->merchantId) && !empty($this->apiKey) && !empty($this->secretKey);
    }

    /**
     * Создать заказ для оплаты
     * @param int $userId ID пользователя
     * @param int $amount Сумма в рублях
     * @param string $description Описание платежа
     * @return array|false Данные заказа или false
     */
    public function createOrder($userId, $amount, $description) {
        if (!$this->isConfigured()) {
            return false;
        }

        $orderId = 'order_' . $userId . '_' . time();

        $params = [
            'merchant_id' => $this->merchantId,
            'order_id'    => $orderId,
            'amount'      => (int)$amount,
            'currency'    => 'RUB',
            'description' => $description,
            'customer'    => [
                'email' => '', // Заполнится позже
                'phone' => '' // Заполнится позже
            ],
            'return_url'      => '/cabinet/',
            'failure_url'     => '/cabinet/',
            'notification_url' => $_SERVER['HTTP_HOST'] . '/api/webhook/prodamus'
        ];

        $signature = $this->generateSignature($params);
        $params['signature'] = $signature;

        $response = $this->request('/orders', 'POST', $params);

        return $response;
    }

    /**
     * Получить статус заказа
     */
    public function getOrderStatus($orderId) {
        if (!$this->isConfigured()) {
            return false;
        }

        $params = [
            'merchant_id' => $this->merchantId,
            'order_id'    => $orderId
        ];

        $signature = $this->generateSignature($params);
        $params['signature'] = $signature;

        return $this->request('/orders/' . $orderId, 'GET', $params);
    }

    /**
     * Генерировать подпись для запроса
     */
    private function generateSignature($params): string {
        // Сортируем параметры по ключам
        ksort($params);

        // Строим строку для подписи
        $signatureString = '';
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $signatureString .= $value;
        }

        $signatureString .= $this->secretKey;

        return hash('sha256', $signatureString);
    }

    /**
     * Проверить подпись вебхука
     */
    public function verifyWebhookSignature($data, $signature): bool {
        if (!$this->isConfigured()) {
            return false;
        }

        $calculatedSignature = $this->generateSignature($data);
        return hash_equals($calculatedSignature, $signature);
    }

    /**
     * Выполнить HTTP запрос к API
     */
    private function request($endpoint, $method = 'GET', $params = []) {
        $url = $this->baseUrl . $endpoint;

        $options = [
            'http' => [
                'method'  => $method,
                'timeout' => 10,
                'header'  => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey
                ]
            ]
        ];

        if ($method === 'POST') {
            $options['http']['content'] = json_encode($params);
        } else if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return false;
        }

        return json_decode($response, true);
    }

    /**
     * Получить ссылку на платёж
     */
    public function getPaymentLink($orderId) {
        return $this->baseUrl . '/pay/' . $orderId;
    }
}

// Инициализация глобального экземпляра
$prodamus = new Prodamus();
