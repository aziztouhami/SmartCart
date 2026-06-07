<?php
/**
 * JWT Key Generator
 * 
 * This script generates RSA key pairs for JWT authentication
 */

$jwtDir = __DIR__ . '/config/jwt';

// Create directory if it doesn't exist
if (!is_dir($jwtDir)) {
    mkdir($jwtDir, 0755, true);
}

// Configuration for key generation
$config = [
    'digest_alg' => 'sha256',
    'private_key_bits' => 4096,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];

try {
    // Generate new key pair
    $res = openssl_pkey_new($config);
    
    if ($res === false) {
        throw new Exception('Failed to generate OpenSSL key: ' . openssl_error_string());
    }
    
    // Extract private key
    if (!openssl_pkey_export($res, $privKey)) {
        throw new Exception('Failed to export private key: ' . openssl_error_string());
    }
    
    // Extract public key
    $pubKeyData = openssl_pkey_get_details($res);
    if ($pubKeyData === false) {
        throw new Exception('Failed to get public key details: ' . openssl_error_string());
    }
    $pubKey = $pubKeyData['key'];
    
    // Write keys to files
    $privateKeyPath = $jwtDir . '/private.pem';
    $publicKeyPath = $jwtDir . '/public.pem';
    
    if (!file_put_contents($privateKeyPath, $privKey)) {
        throw new Exception("Failed to write private key to $privateKeyPath");
    }
    
    if (!file_put_contents($publicKeyPath, $pubKey)) {
        throw new Exception("Failed to write public key to $publicKeyPath");
    }
    
    // Set proper permissions
    chmod($privateKeyPath, 0600);
    chmod($publicKeyPath, 0644);
    
    echo "✓ JWT Keys generated successfully!\n";
    echo "  Private key: $privateKeyPath\n";
    echo "  Public key:  $publicKeyPath\n";
    echo "\nNext steps:\n";
    echo "1. Add 'config/jwt/private.pem' to .gitignore\n";
    echo "2. Verify keys exist in config/jwt/\n";
    echo "3. Your JWT authentication is ready to use!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
