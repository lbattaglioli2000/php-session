<?php

class Session {
    
    private const ENCRYPTION_KEY = "83c5bec96c09706...";
    private const VALID_DURATION = 7200;
    
    public function __construct(
        private string $session_id,
        private array $session_data,
        private string $session_expiry,
        private string $created_at
    ) {}
    
    public static function encrypt($plaintext): string {
        // Generate a random initialization vector (IV)
        $initialization_vector = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

        // Encrypt the plaintext using AES-256 in CBC mode
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-cbc',
            self::ENCRYPTION_KEY,
            OPENSSL_RAW_DATA,
            $initialization_vector
        );

        // Combine the IV and ciphertext into a single string
        $encrypted = $initialization_vector . $ciphertext;

        // Encode the encrypted data in base64 format
        return base64_encode($encrypted);
    }
  
    public static function decrypt($encrypted): bool|string {
        // Decode the base64-encoded encrypted data
        $encrypted = base64_decode($encrypted);

        // Extract the initialization vector (IV) from the encrypted data
        $initialization_vector_length = openssl_cipher_iv_length('aes-256-cbc');
        $initialization_vector = substr($encrypted, 0, $initialization_vector_length);

        // Extract the ciphertext from the encrypted data
        $ciphertext = substr($encrypted, $initialization_vector_length);

        // Decrypt the ciphertext using AES-256 in CBC mode
        return openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',
            self::ENCRYPTION_KEY,
            OPENSSL_RAW_DATA,
            $initialization_vector
        );
    }

    public static function start(): Session {
        $session_id = self::generate_session_id();

        // Set the session ID as a cookie, and expire is in 2 hours
        setcookie('pssid', $session_id, time() + self::VALID_DURATION, '/');

        // Add a new row to the database with the session ID
        global $wpdb;
        $table_name = $wpdb->prefix . 'db_backed_sessions';

        $result = $wpdb->insert($table_name, [
            'session_id' => $session_id,
            'session_data' => self::encrypt(json_encode([])),
            'session_expiry' => date('Y-m-d H:i:s', time() + self::VALID_DURATION),
            'created_at' => current_time('mysql')
        ]);

        // Return the session data
        return new Session(
            $session_id,
            [],
            date('Y-m-d H:i:s', time() + self::VALID_DURATION),
            current_time('mysql')
        );
    }
    
    public static function destroy(string $session): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'db_backed_sessions';
        $wpdb->delete($table_name, ['session_id' => $session]);

        // Delete the session cookie
        setcookie('pssid', '', time() - self::VALID_DURATION, '/');
    }
    
    public static function generate_session_id(): string {
        $session_id = '';
        $session_id_length = 32;
        $session_id_available_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $session_id_available_chars_length = strlen($session_id_available_chars);
        for ($i = 0; $i < $session_id_length; $i++) {
            $session_id .= $session_id_available_chars[rand(0, $session_id_available_chars_length - 1)];
        }
        return $session_id;
    }
    
    public static function retrieve(string $session): ?Session {
        global $wpdb;
        $table_name = $wpdb->prefix . 'db_backed_sessions';

        $result = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s",
            $session
        ));

        if (count($result) === 0) {
            return null;
        }

        $session_data = json_decode(self::decrypt($result[0]->session_data), true);

        // Check if the session has expired
        if (time() > strtotime($result[0]->session_expiry)) {
            // Delete the session from the database
            $wpdb->delete($table_name, ['session_id' => $session]);

            // Return an empty array
            return null;
        }

        // Return the session data
        return new Session(
            $session,
            $session_data,
            $result[0]->session_expiry,
            $result[0]->created_at
        );
    }
    
    public static function update(string $session, array $session_data): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'db_backed_sessions';
        $wpdb->update($table_name, [
            'session_data' => self::encrypt(json_encode($session_data))
        ], [
            'session_id' => $session
        ]);
    }
    
    public function get(string $key): mixed {
        return $this->session_data[$key];
    }
    
    public function set(string $key, mixed $value): void {
        $this->session_data[$key] = $value;
    }
    
    public function has(string $key): bool {
        return ! empty($this->session_data[$key]);
    }
    
    public function delete(string $key): void {
        unset($this->session_data[$key]);

        // Update the session data in the database
        global $wpdb;
        $table_name = $wpdb->prefix . 'db_backed_sessions';

        $wpdb->update($table_name, [
            'session_data' => self::encrypt(json_encode($this->session_data))
        ], [
            'session_id' => $this->session_id
        ]);
    }
    
    public function save(): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'db_backed_sessions';

        $result = $wpdb->update($table_name, [
            'session_data' => self::encrypt(json_encode($this->session_data))
        ], [
            'session_id' => $this->session_id
        ]);

        return $result !== false;
    }
    
    public function expires_at(): string {
        return $this->session_expiry;
    }
    
    public function created_at(): string {
        return $this->created_at;
    }
}
