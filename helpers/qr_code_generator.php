<?php
// helpers/qr_code_generator.php
require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

function generate_vcard_qr_code($name, $phone, $email) {
    // Check if we have at least one piece of contact information
    if (empty($name) && empty($phone) && empty($email)) {
        error_log("QR Code Generation: No contact information provided");
        return '';
    }

    // Sanitize inputs
    $name = trim($name ?? '');
    $phone = trim($phone ?? '');
    $email = trim($email ?? '');

    // Create vCard string
    $vCard = "BEGIN:VCARD\r\n";
    $vCard .= "VERSION:3.0\r\n";
    
    if ($name) {
        $vCard .= "N:" . $name . "\r\n";
        $vCard .= "FN:" . $name . "\r\n";
    }
    if ($phone) {
        $vCard .= "TEL;TYPE=CELL:" . $phone . "\r\n";
    }
    if ($email) {
        $vCard .= "EMAIL:" . $email . "\r\n";
    }
    $vCard .= "END:VCARD";

    try {
        // Check if the QR code library is available
        if (!class_exists('Endroid\QrCode\QrCode')) {
            error_log("QR Code Generation Failed: Endroid QR Code library not found");
            file_put_contents('logs/qr_code_error.log', date('Y-m-d H:i:s') . " - QR Code Generation Failed: Endroid QR Code library not found\n", FILE_APPEND);
            return '';
        }

        error_log("QR Code Generation: Library found, creating QrCode object");
        $qrCode = new QrCode($vCard);
        
        error_log("QR Code Generation: QrCode object created, creating PngWriter");
        $writer = new PngWriter();
        
        error_log("QR Code Generation: PngWriter created, writing QR code");
        $result = $writer->write($qrCode);
        
        error_log("QR Code Generation: QR code written, getting data URI");
        // Return data URI to embed directly in HTML
        $dataUri = $result->getDataUri();
        
        if (empty($dataUri)) {
            error_log("QR Code Generation Failed: Empty data URI returned");
            file_put_contents('logs/qr_code_error.log', date('Y-m-d H:i:s') . " - QR Code Generation Failed: Empty data URI returned\n", FILE_APPEND);
            return '';
        }
        
        error_log("QR Code Generation: Success! Data URI length: " . strlen($dataUri));
        return $dataUri;

    } catch (Exception $e) {
        // Log detailed error information
        $errorMsg = "QR Code Generation Failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
        error_log($errorMsg);
        file_put_contents('logs/qr_code_error.log', date('Y-m-d H:i:s') . " - " . $errorMsg . "\n", FILE_APPEND);
        file_put_contents('logs/qr_code_error.log', date('Y-m-d H:i:s') . " - vCard content: " . $vCard . "\n", FILE_APPEND);
        
        // Try alternative method
        error_log("QR Code Generation: Trying alternative method");
        return generate_qr_code_alternative($vCard);
    }
}

function generate_qr_code_alternative($data) {
    try {
        error_log("QR Code Alternative: Using QR Server API");
        // Use QR Server API as fallback
        $encodedData = urlencode($data);
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . $encodedData;
        
        // Test if the URL is accessible
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET'
            ]
        ]);
        
        $response = @file_get_contents($qrUrl, false, $context);
        
        if ($response !== false) {
            // Convert to data URI
            $base64 = base64_encode($response);
            $dataUri = "data:image/png;base64," . $base64;
            error_log("QR Code Alternative: Success with QR Server API");
            return $dataUri;
        } else {
            error_log("QR Code Alternative: QR Server API failed, trying Google Charts");
            return generate_qr_code_simple($data);
        }
        
    } catch (Exception $e) {
        error_log("QR Code Alternative Failed: " . $e->getMessage());
        return generate_qr_code_simple($data);
    }
}

function generate_qr_code_simple($data) {
    try {
        error_log("QR Code Simple: Using Google Charts API");
        // Use Google Charts API as final fallback
        $encodedData = urlencode($data);
        $qrUrl = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . $encodedData;
        
        // Test if the URL is accessible
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET'
            ]
        ]);
        
        $response = @file_get_contents($qrUrl, false, $context);
        
        if ($response !== false) {
            // Convert to data URI
            $base64 = base64_encode($response);
            $dataUri = "data:image/png;base64," . $base64;
            error_log("QR Code Simple: Success with Google Charts API");
            return $dataUri;
        } else {
            error_log("QR Code Simple: Google Charts API failed");
            return '';
        }
        
    } catch (Exception $e) {
        error_log("QR Code Simple Failed: " . $e->getMessage());
        return '';
    }
} 