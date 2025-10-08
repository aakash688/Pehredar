<?php
// helpers/notifications.php

/**
 * Sends a push notification to a specific device using Firebase Cloud Messaging (FCM).
 *
 * @param string $fcmToken The FCM registration token of the target device.
 * @param string $title The title of the notification.
 * @param string $body The main text body of the notification.
 * @param array $data Optional data payload to send with the notification.
 * @return bool True on success, false on failure.
 */
function send_fcm_notification($fcmToken, $title, $body, $data = []) {
    // IMPORTANT: Get this from your Firebase project settings.
    // Go to Project Settings -> Cloud Messaging -> Manage API (in Google Cloud Console) -> Enable, then go to Service Accounts and generate a new private key. Or get it from the "Cloud Messaging API (Legacy)" if enabled.
    $serverKey = 'YOUR_FCM_SERVER_KEY'; // <--- REPLACE WITH YOUR ACTUAL SERVER KEY

    if ($serverKey === 'YOUR_FCM_SERVER_KEY') {
        // Log an error or handle it, but don't try to send.
        // For now, we'll just return false.
        error_log("FCM Server Key is not configured in helpers/notifications.php");
        return false;
    }

    $url = 'https://fcm.googleapis.com/fcm/send';

    $payload = [
        'to' => $fcmToken,
        'notification' => [
            'title' => $title,
            'body' => $body,
            'sound' => 'default'
        ],
        'data' => $data
    ];

    $headers = [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Not recommended for production
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $result = curl_exec($ch);
    if ($result === FALSE) {
        // die('Curl failed: ' . curl_error($ch));
        error_log("FCM Curl failed: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    
    // You can decode the result and check for success/failure if needed
    // $resultData = json_decode($result, true);
    // if ($resultData['success'] == 1) { ... }

    return true;
} 