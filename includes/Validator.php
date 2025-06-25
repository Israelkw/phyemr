<?php

class Validator {
    private $data;
    private $fields = [];
    private $errors = [];

    /**
     * Constructor.
     * @param array $data The data to validate (e.g., $_POST).
     */
    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Adds a field and its validation rules.
     * @param string $fieldName The name of the field.
     * @param string|array $rules Validation rules (e.g., 'required|email' or ['required', 'minLength:5']).
     * @param array $customMessages Custom error messages for this field.
     */
    public function addField($fieldName, $rules, $customMessages = []) {
        $this->fields[$fieldName] = [
            'rules' => is_string($rules) ? explode('|', $rules) : $rules,
            'customMessages' => $customMessages,
            'value' => isset($this->data[$fieldName]) ? $this->data[$fieldName] : null,
        ];
    }

    /**
     * Validates the added fields.
     * @return bool True if all validations pass, false otherwise.
     */
    public function validate() {
        $this->errors = []; // Reset errors
        foreach ($this->fields as $fieldName => $field) {
            $value = $field['value'];
            foreach ($field['rules'] as $rule) {
                $ruleParts = explode(':', $rule);
                $ruleName = $ruleParts[0];
                $ruleParam = isset($ruleParts[1]) ? $ruleParts[1] : null;

                if (method_exists($this, $ruleName)) {
                    $isValid = $ruleParam !== null ? $this->$ruleName($value, $ruleParam) : $this->$ruleName($value);
                    if (!$isValid) {
                        $this->addError($fieldName, $ruleName, $ruleParam, $field['customMessages']);
                    }
                } else {
                    // Potentially log a warning for an undefined validation rule
                    error_log("Validator: Unknown validation rule '{$ruleName}' for field '{$fieldName}'.");
                }
            }
        }
        return empty($this->errors);
    }

    /**
     * Adds an error message for a field.
     */
    private function addError($fieldName, $ruleName, $ruleParam, $customMessages) {
        if (isset($customMessages[$ruleName])) {
            $message = $customMessages[$ruleName];
        } else {
            // Default error messages
            switch ($ruleName) {
                case 'required':
                    $message = "The {$fieldName} field is required.";
                    break;
                case 'email':
                    $message = "The {$fieldName} field must be a valid email address.";
                    break;
                case 'minLength':
                    $message = "The {$fieldName} field must be at least {$ruleParam} characters long.";
                    break;
                case 'maxLength':
                    $message = "The {$fieldName} field must not exceed {$ruleParam} characters.";
                    break;
                case 'numeric':
                    $message = "The {$fieldName} field must be numeric.";
                    break;
                case 'date':
                    $format = $ruleParam ?: 'Y-m-d';
                    $message = "The {$fieldName} field must be a valid date in the format {$format}.";
                    break;
                case 'regex':
                    $message = "The {$fieldName} field format is invalid.";
                    break;
                default:
                    $message = "The {$fieldName} field is invalid.";
            }
        }
        $this->errors[$fieldName][] = $message;
    }

    /**
     * Gets all error messages.
     * @return array An array of error messages, structured by field name.
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Gets the first error message for a specific field or the first error overall.
     * @param string|null $fieldName The name of the field.
     * @return string|null The error message or null if no errors.
     */
    public function getFirstError($fieldName = null) {
        if ($fieldName !== null) {
            return isset($this->errors[$fieldName][0]) ? $this->errors[$fieldName][0] : null;
        }
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        return null;
    }

    // --- Validation Methods ---

    private function required($value) {
        if (is_string($value)) {
            return trim($value) !== '';
        }
        return !empty($value);
    }

    private function email($value) {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function minLength($value, $length) {
        return mb_strlen(trim((string)$value)) >= $length;
    }

    private function maxLength($value, $length) {
        return mb_strlen(trim((string)$value)) <= $length;
    }

    private function numeric($value) {
        return is_numeric($value);
    }

    private function date($value, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $value);
        return $d && $d->format($format) === $value;
    }

    private function regex($value, $pattern) {
        return preg_match($pattern, $value) === 1;
    }

    /**
     * Checks if a password meets basic strength requirements.
     * For example, minimum length. More complex rules can be added here.
     * @param string $password The password to check.
     * @param int $minLength Minimum length requirement.
     * @return bool True if the password meets requirements, false otherwise.
     */
    public static function isPasswordStrongEnough($password, $minLength = 8) {
        if (empty($password)) {
            return false; // Should be caught by 'required' if used, but good to have.
        }
        if (mb_strlen((string)$password) < $minLength) {
            return false;
        }
        // Add more rules here if needed (e.g., requires numbers, uppercase, special chars)
        // preg_match('/[A-Z]/', $password) // Has uppercase
        // preg_match('/[a-z]/', $password) // Has lowercase
        // preg_match('/[0-9]/', $password) // Has number
        // preg_match('/[\W_]/', $password) // Has special character (non-alphanumeric)
        return true;
    }
}
?>
