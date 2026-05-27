<?php

declare(strict_types=1);

namespace App;

class FreedomPay
{
    private string $merchantId;
    private string $secretKey;
    private string $initUrl;

    public function __construct(string $merchantId = 'TEST_MERCHANT', string $secretKey = 'test_secret', string $initUrl = 'https://api.freedompay.uz/init_payment.php')
    {
        $this->merchantId = $merchantId;
        $this->secretKey = $secretKey;
        $this->initUrl = $initUrl;
    }

    /**
     * Генерация платежной ссылки
     *
     * @param string $orderId Уникальный ID заказа
     * @param int $amount Сумма в UZS
     * @param string $description Описание платежа
     * @return string Сгенерированный URL
     */
    public function generatePaymentLink(string $orderId, int $amount, string $description = 'German Lessons'): string
    {
        // Соль для безопасности подписи (рандомная строка)
        $salt = bin2hex(random_bytes(8));
        
        // Обязательные параметры для запроса (до подписи)
        $params = [
            'pg_amount' => $amount,
            'pg_currency' => 'UZS',
            'pg_description' => $description,
            'pg_merchant_id' => $this->merchantId,
            'pg_order_id' => $orderId,
            'pg_salt' => $salt,
        ];

        // Для создания подписи (sig) FreedomPay требует сортировки параметров по алфавиту ключа
        ksort($params);
        
        // Имя скрипта добавляется в начало массива
        array_unshift($params, 'init_payment.php');
        
        // Секретный ключ мерчанта добавляется в конец
        $params[] = $this->secretKey;

        // Генерация MD5 подписи
        $sigString = implode(';', $params);
        $sig = md5($sigString);

        // Формируем финальный массив данных с подписью
        $queryData = [
            'pg_merchant_id' => $this->merchantId,
            'pg_amount' => $amount,
            'pg_currency' => 'UZS',
            'pg_order_id' => $orderId,
            'pg_description' => $description,
            'pg_salt' => $salt,
            'pg_sig' => $sig,
        ];

        // Возвращаем полный URL с параметрами
        return $this->initUrl . '?' . http_build_query($queryData);
    }
}
