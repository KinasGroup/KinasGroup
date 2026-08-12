<?php
/**
 * KINAS GROUP — Solar Calculator API (REBUILT)
 *
 * POST /api/solar/calculate.php
 *
 * Replaces the old hardcoded estimator with a product-driven quotation
 * engine:
 *   - Uses REAL appliance hours (no fixed 8h assumption).
 *   - Reads admin-controlled settings from solar_calculator_settings.
 *   - Matches REAL KINAS VOLT products (power stations / panels /
 *     inverters / batteries) with LIVE listing prices.
 *   - Falls back to clearly-labelled reference pricing only when a
 *     product type is not configured, and emits a warning.
 *   - Saves the quotation to solar_proposals + solar_proposal_items.
 *   - Generates the PDF and sends customer + admin emails.
 *
 * Requires FILE 1 (SQL tables). Self-contained — no other rebuild file
 * is required for this endpoint to work.
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/solar-pdf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ============================================================
// HELPERS
// ============================================================

/**
 * Load admin-controlled calculator settings with safe defaults.
 */
function solar_calc_settings(PDO $db): array
{
    $defaults = [
        'sun_hours'               => 5.0,
        'load_margin_pct'         => 10.0,
        'pv_eff'                  => 0.80,
        'battery_dod_pct'         => 90.0,
        'battery_eff_pct'         => 95.0,
        'inverter_factor'         => 1.25,
        'default_panel_wattage'   => 550.0,
        'default_panel_price'     => 450000.0,
        'default_inverter_price'  => 3500000.0,
        'default_battery_price'   => 2800000.0,
        'installation_cost'       => 1500000.0,
        'cabling_cost'            => 500000.0,
        'mounting_cost'           => 400000.0,
        'transport_cost'          => 200000.0,
        'tariff'                  => 225.0,
        'co2_factor'              => 0.85,
    ];

    $map = [];
    try {
        $rows = $db->query("SELECT setting_key, setting_value FROM solar_calculator_settings")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $map[$r['setting_key']] = (float)$r['setting_value'];
        }
    } catch (Throwable $e) {
        $map = [];
    }

    $keymap = [
        'sun_hours_default'      => 'sun_hours',
        'load_margin_pct'        => 'load_margin_pct',
        'pv_performance_ratio'   => 'pv_eff',
        'battery_dod_pct'        => 'battery_dod_pct',
        'battery_efficiency_pct' => 'battery_eff_pct',
        'inverter_safety_factor' => 'inverter_factor',
        'default_panel_wattage'  => 'default_panel_wattage',
        'default_panel_price'    => 'default_panel_price',
        'default_inverter_price' => 'default_inverter_price',
        'default_battery_price'  => 'default_battery_price',
        'installation_cost
