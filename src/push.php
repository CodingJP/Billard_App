<?php

declare(strict_types=1);

/**
 * Web-Push-Benachrichtigungen.
 * Benötigt: composer require minishlink/web-push
 * Konfiguration via .env: VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT
 */
function notifyUser(int $userId, string $title, string $body): void
{
    static $vendorLoaded = null;

    if ($vendorLoaded === null) {
        $autoload   = dirname(__DIR__) . '/vendor/autoload.php';
        $vendorLoaded = file_exists($autoload);
        if ($vendorLoaded) {
            require_once $autoload;
        }
    }

    if (!$vendorLoaded || !class_exists('Minishlink\WebPush\WebPush')) {
        return;
    }

    $pubKey  = (string)(env('VAPID_PUBLIC_KEY', '') ?? '');
    $privKey = (string)(env('VAPID_PRIVATE_KEY', '') ?? '');

    if ($pubKey === '' || $privKey === '') {
        return;
    }

    $stmt = db()->prepare(
        'SELECT endpoint, p256dh, auth_key FROM push_subscriptions WHERE user_id = :uid'
    );
    $stmt->execute(['uid' => $userId]);
    $subscriptions = $stmt->fetchAll();

    if (empty($subscriptions)) {
        return;
    }

    $auth = [
        'VAPID' => [
            'subject'    => (string)(env('VAPID_SUBJECT', 'mailto:admin@billardliga.local') ?? ''),
            'publicKey'  => $pubKey,
            'privateKey' => $privKey,
        ],
    ];

    try {
        $webPush = new \Minishlink\WebPush\WebPush($auth);
        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'icon'  => 'assets/icons/icon-192.svg',
        ]);

        $staleEndpoints = [];

        foreach ($subscriptions as $sub) {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $sub['endpoint'],
                'keys'     => [
                    'p256dh' => $sub['p256dh'],
                    'auth'   => $sub['auth_key'],
                ],
            ]);

            $report = $webPush->sendOneNotification($subscription, $payload);

            if (method_exists($report, 'isSubscriptionExpired') && $report->isSubscriptionExpired()) {
                $staleEndpoints[] = $sub['endpoint'];
            }
        }

        foreach ($staleEndpoints as $ep) {
            db()->prepare('DELETE FROM push_subscriptions WHERE endpoint = :ep')
                ->execute(['ep' => $ep]);
        }
    } catch (Throwable) {
        // Push-Fehler sind nicht kritisch
    }
}
