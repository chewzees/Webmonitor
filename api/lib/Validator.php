<?php
declare(strict_types=1);

final class AppException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 500,
        public readonly string $errorCode = 'INTERNAL_ERROR',
        public readonly mixed $details = null,
    ) {
        parent::__construct($message);
    }
}

final class Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules  field => rule string
     * @return array<string, mixed>
     */
    public static function validate(array $data, array $rules, bool $partial = false): array
    {
        $out = [];
        $errors = [];

        foreach ($rules as $field => $ruleStr) {
            $ruleParts = explode('|', $ruleStr);
            $present = array_key_exists($field, $data);
            $value = $present ? $data[$field] : null;

            if (!$present) {
                if (in_array('required', $ruleParts, true) && !$partial) {
                    $errors[$field][] = 'Required';
                }
                continue;
            }

            if ($value === null && in_array('nullable', $ruleParts, true)) {
                $out[$field] = null;
                continue;
            }

            foreach ($ruleParts as $rule) {
                if ($rule === 'required' || $rule === 'nullable' || $rule === 'optional') {
                    continue;
                }

                if (str_starts_with($rule, 'string')) {
                    if (!is_string($value) && !is_numeric($value)) {
                        $errors[$field][] = 'Must be a string';
                        break;
                    }
                    $value = (string) $value;
                } elseif ($rule === 'bool' || $rule === 'boolean') {
                    if (!is_bool($value)) {
                        $errors[$field][] = 'Must be a boolean';
                        break;
                    }
                } elseif ($rule === 'int' || $rule === 'integer') {
                    if (!is_int($value) && !(is_string($value) && ctype_digit(ltrim((string) $value, '-')))) {
                        $errors[$field][] = 'Must be an integer';
                        break;
                    }
                    $value = (int) $value;
                } elseif (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (is_string($value) && mb_strlen($value) < $min) {
                        $errors[$field][] = "Min length {$min}";
                        break;
                    }
                    if (is_int($value) && $value < $min) {
                        $errors[$field][] = "Min value {$min}";
                        break;
                    }
                } elseif (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (is_string($value) && mb_strlen($value) > $max) {
                        $errors[$field][] = "Max length {$max}";
                        break;
                    }
                    if (is_int($value) && $value > $max) {
                        $errors[$field][] = "Max value {$max}";
                        break;
                    }
                } elseif ($rule === 'url') {
                    if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[$field][] = 'Invalid URL';
                        break;
                    }
                    if (!str_starts_with($value, 'http://') && !str_starts_with($value, 'https://')) {
                        $errors[$field][] = 'URL must start with http:// or https://';
                        break;
                    }
                } elseif ($rule === 'email') {
                    if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field][] = 'Invalid email';
                        break;
                    }
                } elseif ($rule === 'slug') {
                    if (!is_string($value) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
                        $errors[$field][] = 'Slug must be lowercase kebab-case';
                        break;
                    }
                } elseif (str_starts_with($rule, 'in:')) {
                    $allowed = explode(',', substr($rule, 3));
                    if (!in_array((string) $value, $allowed, true)) {
                        $errors[$field][] = 'Invalid value';
                        break;
                    }
                } elseif ($rule === 'array') {
                    if (!is_array($value)) {
                        $errors[$field][] = 'Must be an array';
                        break;
                    }
                } elseif ($rule === 'json_string') {
                    if ($value !== null && $value !== '') {
                        if (!is_string($value)) {
                            $errors[$field][] = 'Must be a JSON string';
                            break;
                        }
                        json_decode($value);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $errors[$field][] = 'Must be valid JSON';
                            break;
                        }
                    }
                }
            }

            if (!isset($errors[$field])) {
                $out[$field] = $value;
            }
        }

        if ($errors !== []) {
            throw new AppException('Validation failed', 400, 'VALIDATION_ERROR', $errors);
        }

        return $out;
    }
}
