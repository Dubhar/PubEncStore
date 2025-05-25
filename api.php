<?php
// api.php

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$query = $_SERVER['QUERY_STRING'];
parse_str($query, $params);

// Config
$dataDir = __DIR__ . "/data";
$keyDir = __DIR__ . "/keys";

// Helper functions
function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function generateUUID() {
    return bin2hex(random_bytes(16));
}

function encryptWithPublicKey($data, $pubKeyPath) {
    if (!file_exists($pubKeyPath)) {
        return [false, "Public key not found at $pubKeyPath"];
    }

    $pubKey = file_get_contents($pubKeyPath);
    $pubKeyRes = openssl_pkey_get_public($pubKey);

    if (!$pubKeyRes) {
        return [false, "Invalid public key"];
    }

    $success = openssl_public_encrypt($data, $encrypted, $pubKeyRes);

    if (!$success) {
        return [false, "Encryption failed"];
    }

    return [true, $encrypted];
}

function decryptWithPrivateKeyContent($data, $privKeyContent) {
    $privKeyRes = openssl_pkey_get_private($privKeyContent);

    if (!$privKeyRes) {
        return [false, "Invalid private key"];
    }

    $success = openssl_private_decrypt($data, $decrypted, $privKeyRes);

    if (!$success) {
        return [false, "Decryption failed"];
    }

    return [true, $decrypted];
}

// Route: storeJson
if (isset($_POST['storeJson'])) {
    $json = $_POST['storeJson'];

    json_decode($json);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respond(["error" => "Invalid JSON"], 400);
    }

    $uuid = generateUUID();
    $publicKeyPath = "$keyDir/public.pem";
    $storagePath = "$dataDir/$uuid";

    list($success, $result) = encryptWithPublicKey($json, $publicKeyPath);

    if (!$success) {
        respond(["error" => $result], 500);
    }

    $bytesWritten = file_put_contents($storagePath, $result);
    if ($bytesWritten === false) {
        respond(["error" => "Failed to write encrypted data"], 500);
    }

    respond([
        "message" => "JSON stored successfully",
        "uuid" => $uuid
    ]);
}

// Route: listStored
if (isset($_POST['listStored'])) {
    $files = array_diff(scandir($dataDir), ['.', '..', 'public.pem']);
    $uuids = array_values(array_filter($files, function($f) use ($dataDir) {
        return is_file("$dataDir/$f");
    }));

    respond($uuids);
}

// Route: fetchStored=UUID and privKey via POST
if (isset($_POST['fetchStored']) && isset($_FILES['privKey'])) {
    $uuid = $_POST['fetchStored'];
    $privKeyContent = file_get_contents($_FILES['privKey']['tmp_name']);

    $privKeyRes = openssl_pkey_get_private($privKeyContent);
    if (!$privKeyRes) {
        respond(["error" => "Invalid private key"], 400);
    }

    $privKeyRes = openssl_pkey_get_private($privKeyContent);
    if (!$privKeyRes) {
        respond(["error" => "Invalid private key"], 400);
    }

    // Single file
    if (!empty($uuid)) {
        $filePath = "$dataDir/$uuid";
        if (!file_exists($filePath)) {
            respond(["error" => "File not found: $uuid"], 404);
        }

        $encryptedData = file_get_contents($filePath);
        list($success, $decrypted) = decryptWithPrivateKeyContent($encryptedData, $privKeyContent);

        if (!$success) {
            respond(["error" => $decrypted], 500);
        }

        respond(["uuid" => $uuid, "data" => json_decode($decrypted, true)]);
    }

    // All files
    $files = array_diff(scandir($dataDir), ['.', '..', 'public.pem']);
    $results = [];

    foreach ($files as $file) {
        $filePath = "$dataDir/$file";
        if (!is_file($filePath)) continue;

        $encryptedData = file_get_contents($filePath);
        list($success, $decrypted) = decryptWithPrivateKeyContent($encryptedData, $privKeyContent);

        if ($success) {
            $results[] = [
                "uuid" => $file,
                "data" => json_decode($decrypted, true)
            ];
        } else {
            $results[] = [
                "uuid" => $file,
                "error" => $decrypted
            ];
        }
    }

    respond($results);
}

// Fallback
respond(["error" => "Invalid endpoint or parameters".print_r($_FILES).print_r($_POST).print_r($params)], 400);

