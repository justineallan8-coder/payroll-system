<?php
// =============================================
// HELPER FUNCTIONS
// =============================================

/**
 * Calculate PAYE (Tanzania Tax)
 */
function calculatePAYE($gross_pay) {
    if ($gross_pay <= 270000) {
        return 0;
    } elseif ($gross_pay <= 520000) {
        return ($gross_pay - 270000) * 0.08;
    } elseif ($gross_pay <= 760000) {
        return 20000 + ($gross_pay - 520000) * 0.20;
    } elseif ($gross_pay <= 1000000) {
        return 68000 + ($gross_pay - 760000) * 0.25;
    } else {
        return 128000 + ($gross_pay - 1000000) * 0.30;
    }
}

/**
 * Calculate NHIF
 */
function calculateNHIF($gross_pay) {
    if ($gross_pay <= 400000) {
        return $gross_pay * 0.03;
    } else {
        return 12000 + ($gross_pay - 400000) * 0.03;
    }
}

/**
 * Calculate NSSF (10% of basic)
 */
function calculateNSSF($basic_salary) {
    return $basic_salary * 0.10;
}

/**
 * Calculate all deductions
 */
function calculateDeductions($basic, $allowances) {
    $gross = $basic + $allowances;
    $paye = calculatePAYE($gross);
    $nhif = calculateNHIF($gross);
    $nssf = calculateNSSF($basic);
    
    return [
        'gross_pay' => $gross,
        'paye' => $paye,
        'nhif' => $nhif,
        'nssf' => $nssf,
        'total_deductions' => $paye + $nhif + $nssf,
        'net_pay' => $gross - ($paye + $nhif + $nssf)
    ];
}

/**
 * Format currency
 */
function formatMoney($amount) {
    return 'TSh ' . number_format($amount, 2);
}

/**
 * Redirect helper
 */
function redirect($url) {
    if (headers_sent()) {
        echo "<script>window.location.href = " . json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) . ";</script>";
    } else {
        header("Location: $url");
    }
    exit;
}

/**
 * Flash message helper
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
?>