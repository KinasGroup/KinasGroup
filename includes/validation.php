<?php
// KINAS GROUP - Input Validation
// SECURED: Added table/column whitelisting to prevent SQL injection

class Validator {
    private array $errors = [];
    private array $data = [];

    /** @var array Whitelist of allowed database tables for unique validation */
    private const ALLOWED_TABLES = [
        'users',
        'blog_posts',
        'marketplace_listings',
        'car_listings',
        'property_listings',
        'solar_listings'
    ];

    /** @var array Whitelist of allowed columns for unique validation */
    private const ALLOWED_COLUMNS = [
        'email',
        'title',
        'name',
        'phone',
        'slug'
    ];

    public function validate(array $data, array $rules): bool {
        $this->data = $data;
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $fieldRules = explode('|', $fieldRules);

            foreach ($fieldRules as $rule) {
                $params = [];

                if (strpos($rule, ':') !== false) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $methodName = 'validate' . ucfirst($rule);

                if (method_exists($this, $methodName)) {
                    $this->$methodName($field, $data[$field] ?? null, $params);
                }
            }
        }

        return empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function getFirstError(): string {
        return reset($this->errors) ?: '';
    }

    private function addError(string $field, string $message): void {
        $this->errors[$field][] = $message;
    }

    private function validateRequired(string $field, mixed $value, array $params): void {
        if (is_null($value) || (is_string($value) && trim($value) === '') || (is_array($value) && empty($value))) {
            $this->addError($field, ucfirst($field) . ' is required');
        }
    }

    private function validateEmail(string $field, mixed $value, array $params): void {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'Please enter a valid email address');
        }
    }

    private function validateMin(string $field, mixed $value, array $params): void {
        $min = (int)($params[0] ?? 0);
        if (!empty($value) && strlen((string)$value) < $min) {
            $this->addError($field, ucfirst($field) . " must be at least $min characters");
        }
    }

    private function validateMax(string $field, mixed $value, array $params): void {
        $max = (int)($params[0] ?? 255);
        if (!empty($value) && strlen((string)$value) > $max) {
            $this->addError($field, ucfirst($field) . " must not exceed $max characters");
        }
    }

    private function validateNumeric(string $field, mixed $value, array $params): void {
        if (!empty($value) && !is_numeric($value)) {
            $this->addError($field, ucfirst($field) . ' must be a number');
        }
    }

    private function validateInteger(string $field, mixed $value, array $params): void {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, ucfirst($field) . ' must be an integer');
        }
    }

    private function validateUrl(string $field, mixed $value, array $params): void {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'Please enter a valid URL');
        }
    }

    private function validatePhone(string $field, mixed $value, array $params): void {
        if (!empty($value) && !preg_match('/^\+?[\d\s\-\(\)]{7,15}$/', (string)$value)) {
            $this->addError($field, 'Please enter a valid phone number');
        }
    }

    private function validateAlpha(string $field, mixed $value, array $params): void {
        if (!empty($value) && !ctype_alpha(str_replace(' ', '', (string)$value))) {
            $this->addError($field, ucfirst($field) . ' may only contain letters');
        }
    }

    private function validateConfirmed(string $field, mixed $value, array $params): void {
        $confirmationField = $field . '_confirmation';
        if ($value !== ($this->data[$confirmationField] ?? '')) {
            $this->addError($field, ucfirst($field) . ' confirmation does not match');
        }
    }

    /**
     * SECURED: SQL injection prevention via table/column whitelisting
     */
    private function validateUnique(string $field, mixed $value, array $params): void {
        $table = $params[0] ?? '';
        $column = $params[1] ?? $field;
        $exceptId = $params[2] ?? null;

        if (empty($value) || empty($table)) {
            return;
        }

        // Whitelist validation - reject if table or column not in whitelist
        if (!in_array($table, self::ALLOWED_TABLES, true)) {
            error_log("Validator: Unauthorized table access attempt: $table");
            return;
        }

        if (!in_array($column, self::ALLOWED_COLUMNS, true)) {
            error_log("Validator: Unauthorized column access attempt: $column");
            return;
        }

        // Sanitize column name (extra safety measure)
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);

        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT COUNT(*) as count FROM $table WHERE $column = :value";
            $bindParams = [':value' => $value];

            if ($exceptId !== null) {
                $sql .= " AND id != :except_id";
                $bindParams[':except_id'] = (int)$exceptId;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($bindParams);
            $result = $stmt->fetch();

            if (($result['count'] ?? 0) > 0) {
                $this->addError($field, 'This ' . $field . ' is already taken');
            }
        } catch (\Exception $e) {
            error_log('Validator unique check error: ' . $e->getMessage());
        }
    }

    private function validateFile(string $field, mixed $value, array $params): void {
        $allowedTypes = $params[0] ?? 'jpg,jpeg,png,pdf';
        $maxSize = (int)($params[1] ?? 5); // MB

        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$field];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExtensions = array_map('trim', explode(',', $allowedTypes));

            if (!in_array($extension, $allowedExtensions, true)) {
                $this->addError($field, 'File type not allowed. Accepted: ' . $allowedTypes);
            }

            if ($file['size'] > $maxSize * 1024 * 1024) {
                $this->addError($field, "File size must be less than $maxSize MB");
            }
        }
    }

    /**
     * Validate password strength
     */
    private function validatePassword(string $field, mixed $value, array $params): void {
        if (empty($value)) {
            return;
        }

        $value = (string)$value;
        $minLength = (int)($params[0] ?? 8);
        $requireUppercase = in_array('uppercase', $params, true);
        $requireNumber = in_array('number', $params, true);
        $requireSpecial = in_array('special', $params, true);

        if (strlen($value) < $minLength) {
            $this->addError($field, "Password must be at least $minLength characters");
            return;
        }

        if ($requireUppercase && !preg_match('/[A-Z]/', $value)) {
            $this->addError($field, 'Password must contain at least one uppercase letter');
            return;
        }

        if ($requireNumber && !preg_match('/[0-9]/', $value)) {
            $this->addError($field, 'Password must contain at least one number');
            return;
        }

        if ($requireSpecial && !preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $value)) {
            $this->addError($field, 'Password must contain at least one special character');
        }
    }

    /**
     * Validate against a list of allowed values
     */
    private function validateIn(string $field, mixed $value, array $params): void {
        if (empty($value)) {
            return;
        }

        // First param is the comma-separated list of allowed values
        $allowedValues = array_map('trim', explode(',', $params[0] ?? ''));

        if (!in_array($value, $allowedValues, true)) {
            $this->addError($field, 'Invalid value selected');
        }
    }

    /**
     * Check if a field matches a regex pattern
     */
    private function validateRegex(string $field, mixed $value, array $params): void {
        if (empty($value) || empty($params[0])) {
            return;
        }

        $pattern = $params[0];
        if (!@preg_match($pattern, (string)$value)) {
            $this->addError($field, 'Invalid format');
        }
    }

    /**
     * Get allowed tables for external reference
     */
    public static function getAllowedTables(): array {
        return self::ALLOWED_TABLES;
    }

    /**
     * Get allowed columns for external reference
     */
    public static function getAllowedColumns(): array {
        return self::ALLOWED_COLUMNS;
    }
}
