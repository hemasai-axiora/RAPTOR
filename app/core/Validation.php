<?php
/**
 * Centralized Input Validation Rules
 */
class Validation {
    // DOB must satisfy minimum age of 18 and be a past date
    public static function validateDob($dob): bool {
        if (empty($dob)) return false;
        $dobDate = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$dobDate) return false;
        $today = new DateTime('today');
        $age = $today->diff($dobDate)->y;
        return $age >= 18 && $dobDate < $today;
    }

    // Require min 3 characters, must contain at least one letter
    public static function validateManagerComment($comment): bool {
        $comment = trim($comment);
        if (strlen($comment) < 3) return false;
        return preg_match('/[A-Za-z]/', $comment) === 1;
    }

    // Validate valid well-formed JSON
    public static function validateJson($json): bool {
        if (empty($json)) return false;
        if ($json === 'null') return true;
        $decoded = json_decode($json);
        return (json_last_error() === JSON_ERROR_NONE) && ($decoded !== null);
    }

    // Alphabetic characters and spaces only, min 3 chars, must start with letter
    public static function validateNameWithSpaces($name): bool {
        $name = trim($name);
        return preg_match('/^[A-Za-z][A-Za-z\s]{2,}$/', $name) === 1;
    }

    // Meaningful address: min 5 chars, contains letters, allowed characters only
    public static function validateAddress($address): bool {
        $address = trim($address);
        if (strlen($address) < 5) return false;
        if (preg_match('/[A-Za-z]/', $address) !== 1) return false;
        return preg_match('/^[A-Za-z0-9\s,.\#\-]+$/', $address) === 1;
    }

    // Full name: alphabetic, spaces, hyphens, and apostrophes, min 2 chars
    public static function validateFullName($name): bool {
        $name = trim($name);
        return preg_match('/^[A-Za-z\s\'-]{2,}$/', $name) === 1;
    }

    // Email address validation (with valid TLD check)
    public static function validateEmail($email): bool {
        $email = trim($email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) && preg_match('/@[^.]+\.[A-Za-z]{2,}/', $email) === 1;
    }

    // Phone number: exactly 10 digits starting with 6-9
    public static function validatePhoneNumber($phone): bool {
        $phone = trim($phone);
        return preg_match('/^[6-9]\d{9}$/', $phone) === 1;
    }

    // Job title: min 2, contains letters, allowed chars (spaces, &, /)
    public static function validateJobTitle($title): bool {
        $title = trim($title);
        if (strlen($title) < 2) return false;
        if (preg_match('/[A-Za-z]/', $title) !== 1) return false;
        return preg_match('/^[A-Za-z\s&\/]+$/', $title) === 1;
    }

    // Department must be from predefined set
    public static function validateDepartment($dept): bool {
        $allowed = ['Sales', 'Marketing', 'Engineering', 'HR', 'Finance', 'Operations', 'Executive'];
        return in_array($dept, $allowed, true);
    }

    // Bank name: min 2, contains letters, allowed characters (spaces, &, .)
    public static function validateBankName($bank): bool {
        $bank = trim($bank);
        if (strlen($bank) < 2) return false;
        if (preg_match('/[A-Za-z]/', $bank) !== 1) return false;
        return preg_match('/^[A-Za-z\s&.]+$/', $bank) === 1;
    }

    // Account number: numeric only
    public static function validateAccountNumber($acc): bool {
        $acc = trim($acc);
        return preg_match('/^\d+$/', $acc) === 1;
    }

    // IFSC code standard Indian format
    public static function validateIfscCode($ifsc): bool {
        $ifsc = trim($ifsc);
        return preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc) === 1;
    }

    // Branch name/address: min 3, letters required, allowed characters only
    public static function validateBankBranch($branch): bool {
        $branch = trim($branch);
        if (strlen($branch) < 3) return false;
        if (preg_match('/[A-Za-z]/', $branch) !== 1) return false;
        return preg_match('/^[A-Za-z0-9\s,.\#\-]+$/', $branch) === 1;
    }

    // PAN Number standard format
    public static function validatePanNumber($pan): bool {
        $pan = strtoupper(trim($pan));
        return preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan) === 1;
    }

    // Aadhaar number: exactly 12 digits
    public static function validateAadhaarNumber($aadhaar): bool {
        $aadhaar = trim($aadhaar);
        return preg_match('/^\d{12}$/', $aadhaar) === 1;
    }

    // Salary: positive numeric > 0
    public static function validateSalary($salary): bool {
        $salary = trim($salary);
        return is_numeric($salary) && (float)$salary > 0;
    }

    // UAN: exactly 12 digits
    public static function validateUan($uan): bool {
        $uan = trim($uan);
        return preg_match('/^\d{12}$/', $uan) === 1;
    }

    // ESIC number: exactly 17 digits
    public static function validateEsicNumber($esic): bool {
        $esic = trim($esic);
        return preg_match('/^\d{17}$/', $esic) === 1;
    }

    // Pay Grade must be empty or in predefined set
    public static function validatePayGrade($grade): bool {
        $allowed = ['Band A', 'Band B', 'Band C', 'Band D', 'Band E'];
        return empty($grade) || in_array($grade, $allowed, true);
    }
}
