<?php
/**
 * KINAS GROUP — Solar Bundle Calculation Engine (ALIGNED)
 *
 * Single source of truth for the product-driven solar quotation:
 *  - Loads admin-controlled settings (solar_calculator_settings).
 *  - Loads calculator-enabled products with LIVE listing prices
 *    (solar_calculator_products joined to solar_listings).
 *  - Sizes PV / inverter / battery from real appliance hours + backup.
 *  - Matches real power-station bundles (inverter+battery) and panels.
 *  - Builds an itemised quotation (hardware only — no service costs).
 *  - Computes savings / payback / ROI / CO2.
 *  - Saves proposals + line items for the admin audit trail.
 *
 * All functions are guarded so this file can be safely included
 * alongside older code without redeclaration errors.
 */

// ============================================================
// SETTINGS
// ============================================================
if (!function_exists('kinas_solar_default_settings')) {
    function kinas_solar_default_settings(): array
    {
        return [
            'sun_hours_default'      => 5.0,
            'load_margin_pct'        => 10.0,
            'pv_performance_ratio'   => 0.80,
            'battery_dod_pct'        => 90.0,
            'battery_efficiency_pct' => 95.0,
            'inverter_safety_factor' => 1.25,
            'default_panel_wattage'  => 550.0,
            'co2_kg_per_kwh'         => 0.85,
            'default_panel_price'    => 450000.0,
            'electricity_tariff_ngn' => 225.0,
        ];
    }
}

if (!function_exists('kinas_solar_get_settings')) {
    /** @return array settings keyed by setting_key (floats) */
    function kinas_solar_get_settings(PDO $db): array
    {
        $settings = kinas_solar_default_settings();
        try {
            $rows = $db->query("SELECT setting_key, setting_value FROM solar_calculator_settings")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $key = (string)($row['setting_key'] ?? '');
                if (array_key_exists($key, $settings)) {
                    $settings[$key] = (float)$row['setting_value'];
                }
            }
        } catch (Throwable $e) {
            // Keep defaults if the settings table is missing.
        }
        return $settings;
    }
}

// ============================================================
// PRODUCTS (live prices)
// ============================================================
if (!function_exists('kinas_solar_get_products')) {
    /** @return array calculator-enabled products ordered by priority, price */
    function kinas_solar_get_products(PDO $db): array
    {
        try {
            $stmt = $db->query("
                SELECT p.id, p.listing_id, p.product_type, p.panel_wattage_w, p.inverter_capacity_kva,
                       p.continuous_kw, p.battery_capacity_kwh, p.usable_battery_kwh, p.battery_voltage_v,
                       p.expandable, p.priority,
                       l.title, l.brand, l.price
                FROM solar_calculator_products p
                JOIN solar_listings l ON l.id = p.listing_id
                WHERE p.active = 1
                  AND l.status = 'active'
                  AND l.price IS NOT NULL
                  AND l.price > 0
                ORDER BY p.priority ASC, l.price ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

// ============================================================
// CALCULATION
// ============================================================
if (!function_exists('kinas_solar_calculate')) {
    /**
     * Run the full bundle-aware calculation.
     *
     * @param array $input {
     *   appliances:   array|JSON-string of {name, quantity, watts, hours}
     *   backup_hours: int (default 24)
     * }
     * @return array result (success + sizing + items + financials + warnings)
     */
    function kinas_solar_calculate(PDO $db, array $input): array
    {
        $settings = kinas_solar_get_settings($db);
        $products = kinas_solar_get_products($db);

        // ----------------------------------------------------
        // 1. Normalize appliances
        // ----------------------------------------------------
        $appliances = [];
        $raw = $input['appliances'] ?? [];
        if (is_string($raw)) { $raw = json_decode($raw, true) ?: []; }
        foreach ($raw as $ap) {
            if (!is_array($ap)) continue;
            $name  = trim((string)($ap['name'] ?? ''));
            $qty   = (int)round((float)($ap['quantity'] ?? $ap['qty'] ?? 1));
            $watts = (float)($ap['watts'] ?? $ap['watt'] ?? 0);
            $hours = (float)($ap['hours'] ?? 0);
            if ($name === '' || $qty < 1 || $watts <= 0) continue;
            $hours = max(0, min(24, $hours));
            $appliances[] = ['name' => $name, 'quantity' => $qty, 'watts' => $watts, 'hours' => $hours];
        }
        if (empty($appliances)) {
            return ['success' => false, 'error' => 'Please add at least one appliance with a valid wattage.'];
        }

        $backupHours = max(1, min(120, (int)round((float)($input['backup_hours'] ?? 24))));

        // ----------------------------------------------------
        // 2. Load + energy (real hours, not a fixed assumption)
        // ----------------------------------------------------
        $totalLoadW = 0.0;
        $dailyWh    = 0.0;
        foreach ($appliances as $ap) {
            $totalLoadW += $ap['quantity'] * $ap['watts'];
            $dailyWh    += $ap['quantity'] * $ap['watts'] * $ap['hours'];
        }
        if ($totalLoadW <= 0 || $dailyWh <= 0) {
            return ['success' => false, 'error' => 'Total load calculation failed. Please check your appliance wattages and hours.'];
        }

        $dailyKwh       = $dailyWh / 1000;
        $designDailyKwh = $dailyKwh * (1 + ($settings['load_margin_pct'] / 100));

        $sunHours = max(1.0, (float)$settings['sun_hours_default']);
        $pr       = max(0.1, min(1.0, (float)$settings['pv_performance_ratio']));

        // PV array
        $requiredPvKw = $designDailyKwh / ($sunHours * $pr);

        // Inverter (peak-load driven)
        $requiredInverterKw = ($totalLoadW * max(0.1, (float)$settings['inverter_safety_factor'])) / 1000;

        // Battery (backup-hours driven)
        $dod     = max(0.1, min(1.0, (float)$settings['battery_dod_pct'] / 100));
        $battEff = max(0.1, min(1.0, (float)$settings['battery_efficiency_pct'] / 100));
        $requiredBatteryKwh = ($dailyKwh * ($backupHours / 24)) / ($dod * $battEff);

        $warnings = [];
        $items    = [];

        // ----------------------------------------------------
        // 3. Panels (live product, or labelled fallback)
        // ----------------------------------------------------
        $panelProduct = null;
        foreach ($products as $p) {
            if ($p['product_type'] === 'panel' && (float)$p['panel_wattage_w'] > 0) { $panelProduct = $p; break; }
        }

        if ($panelProduct !== null) {
            $panelW         = (float)$panelProduct['panel_wattage_w'];
            $panelQty       = (int)max(1, ceil(($requiredPvKw * 1000) / $panelW));
            $panelUnitPrice = (float)$panelProduct['price'];
            $panelDesc      = (string)$panelProduct['title'];
            $panelListingId = (int)$panelProduct['listing_id'];
        } else {
            $panelW         = max(1.0, (float)$settings['default_panel_wattage']);
            $panelQty       = (int)max(1, ceil(($requiredPvKw * 1000) / $panelW));
            $panelUnitPrice = (float)$settings['default_panel_price'];
            $panelDesc      = ((int)$panelW) . 'W Monocrystalline Solar Panel (reference price — add a panel product in admin for live pricing)';
            $panelListingId = null;
            $warnings[]     = 'No solar panel product is configured. A reference panel price was used.';
        }
        $actualPvKw = ($panelQty * $panelW) / 1000;

        $items[] = [
            'type'        => 'panel',
            'listing_id'  => $panelListingId,
            'description' => $panelDesc,
            'qty'         => $panelQty,
            'unit_price'  => $panelUnitPrice,
            'line_total'  => $panelQty * $panelUnitPrice,
        ];

        // ----------------------------------------------------
        // 4. Power system — prefer a real bundle (generator)
        // ----------------------------------------------------
        $generators = array_values(array_filter($products, fn($p) => $p['product_type'] === 'generator'));

        $chosenGenerator     = null;
        $recommendedInvKw    = 0.0;
        $recommendedBattKwh  = 0.0;
        $powerSourceLabel    = '';

        // 4a. A bundle that satisfies BOTH inverter and battery.
        foreach ($generators as $g) {
            if ((float)$g['continuous_kw'] >= $requiredInverterKw
                && (float)$g['usable_battery_kwh'] >= $requiredBatteryKwh) {
                $chosenGenerator = $g;
                break;
            }
        }

        if ($chosenGenerator !== null) {
            $recommendedInvKw   = (float)$chosenGenerator['continuous_kw'];
            $recommendedBattKwh = (float)$chosenGenerator['usable_battery_kwh'];
            $powerSourceLabel   = (string)$chosenGenerator['title'];
            $items[] = [
                'type'        => 'generator',
                'listing_id'  => (int)$chosenGenerator['listing_id'],
                'description' => $powerSourceLabel,
                'qty'         => 1,
                'unit_price'  => (float)$chosenGenerator['price'],
                'line_total'  => (float)$chosenGenerator['price'],
            ];
        } else {
            // 4b. A bundle that covers the inverter but not the battery.
            $bestGen = null;
            foreach ($generators as $g) {
                if ((float)$g['continuous_kw'] >= $requiredInverterKw) {
                    if ($bestGen === null || (float)$g['usable_battery_kwh'] > (float)$bestGen['usable_battery_kwh']) {
                        $bestGen = $g;
                    }
                }
            }

            if ($bestGen !== null) {
                $chosenGenerator    = $bestGen;
                $recommendedInvKw   = (float)$bestGen['continuous_kw'];
                $recommendedBattKwh = (float)$bestGen['usable_battery_kwh'];
                $powerSourceLabel   = (string)$bestGen['title'];
                $items[] = [
                    'type'        => 'generator',
                    'listing_id'  => (int)$bestGen['listing_id'],
                    'description' => $powerSourceLabel,
                    'qty'         => 1,
                    'unit_price'  => (float)$bestGen['price'],
                    'line_total'  => (float)$bestGen['price'],
                ];
                if ($recommendedBattKwh < $requiredBatteryKwh) {
                    $warnings[] = 'The selected power station\'s usable battery ('
                        . number_format($recommendedBattKwh, 2)
                        . ' kWh) is below the estimated requirement ('
                        . number_format($requiredBatteryKwh, 2)
                        . ' kWh) for ' . $backupHours . 'h backup. Consider expansion batteries, shorter backup, or a custom quote.';
                }
            } elseif (!empty($generators)) {
                // 4c. No bundle meets the inverter — quote the largest and flag it.
                $largest = $generators[0];
                foreach ($generators as $g) {
                    if ((float)$g['continuous_kw'] > (float)$largest['continuous_kw']) $largest = $g;
                }
                $chosenGenerator    = $largest;
                $recommendedInvKw   = (float)$largest['continuous_kw'];
                $recommendedBattKwh = (float)$largest['usable_battery_kwh'];
                $powerSourceLabel   = (string)$largest['title'];
                $items[] = [
                    'type'        => 'generator',
                    'listing_id'  => (int)$largest['listing_id'],
                    'description' => $powerSourceLabel,
                    'qty'         => 1,
                    'unit_price'  => (float)$largest['price'],
                    'line_total'  => (float)$largest['price'],
                ];
                $warnings[] = 'The requirement exceeds our largest listed power station. A custom solution is recommended.';
            } else {
                $warnings[] = 'No inverter/battery products are configured. The estimate covers panels only — contact KINAS VOLT for the full system price.';
            }
        }

        // ----------------------------------------------------
        // 5. Totals + financials (hardware only — no service costs)
        // ----------------------------------------------------
        $grandTotal = 0.0;
        foreach ($items as $it) $grandTotal += $it['line_total'];

        $monthlyGenerationKwh  = $actualPvKw * $sunHours * 30 * $pr;
        $monthlyConsumptionKwh = $dailyKwh * 30;
        $billableKwh           = min($monthlyGenerationKwh, $monthlyConsumptionKwh);
        $monthlySavings        = $billableKwh * max(0, (float)$settings['electricity_tariff_ngn']);
        $annualSavings         = $monthlySavings * 12;
        $paybackYears          = ($annualSavings > 0) ? $grandTotal / $annualSavings : 0;
        $roi20                 = ($grandTotal > 0) ? (($annualSavings * 20) / $grandTotal) * 100 : 0;
        $co2TonsYear           = ($dailyKwh * 365 * max(0, (float)$settings['co2_kg_per_kwh'])) / 1000;

        return [
            'success'                 => true,
            'appliances'              => $appliances,
            'backup_hours'            => $backupHours,
            'total_load_w'            => (int)round($totalLoadW),
            'daily_kwh'               => round($dailyKwh, 2),
            'design_daily_kwh'        => round($designDailyKwh, 2),
            'required_pv_kw'          => round($requiredPvKw, 2),
            'recommended_pv_kw'       => round($actualPvKw, 2),
            'panels_qty'              => $panelQty,
            'panel_wattage_w'         => $panelW,
            'required_inverter_kw'    => round($requiredInverterKw, 2),
            'recommended_inverter_kw' => round($recommendedInvKw, 2),
            'required_battery_kwh'    => round($requiredBatteryKwh, 2),
            'recommended_battery_kwh' => round($recommendedBattKwh, 2),
            'power_source_label'      => $powerSourceLabel,
            'items'                   => $items,
            'grand_total'             => round($grandTotal),
            'monthly_generation_kwh'  => round($monthlyGenerationKwh, 1),
            'monthly_consumption_kwh' => round($monthlyConsumptionKwh, 1),
            'monthly_savings'         => round($monthlySavings),
            'annual_savings'          => round($annualSavings),
            'payback_years'           => round($paybackYears, 1),
            'roi_20_years'            => round($roi20, 1),
            'co2_tons_year'           => round($co2TonsYear, 2),
            'warnings'                => $warnings,
            'settings_used'           => $settings,
        ];
    }
}

// ============================================================
// PROPOSAL PERSISTENCE
// ============================================================
if (!function_exists('kinas_solar_make_reference')) {
    function kinas_solar_make_reference(): string
    {
        return 'SOL-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}

if (!function_exists('kinas_solar_save_proposal')) {
    /**
     * Store the calculation as a proposal + line items.
     * @return string|null the reference, or null on failure.
     */
    function kinas_solar_save_proposal(PDO $db, array $calc, array $customer): ?string
    {
        $reference = kinas_solar_make_reference();
        try {
            $db->prepare("
                INSERT INTO solar_proposals
                (reference, full_name, phone, email, city_state, property_type, backup_hours, user_id,
                 total_load_w, daily_kwh, required_pv_kw, panels_recommended, required_inverter_kw,
                 required_battery_kwh, total_cost, monthly_savings, payback_years, co2_tons_year,
                 status, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'new', NOW())
            ")->execute([
                $reference,
                trim((string)($customer['full_name'] ?? '')),
                trim((string)($customer['phone'] ?? '')),
                trim((string)($customer['email'] ?? '')),
                trim((string)($customer['city_state'] ?? '')),
                trim((string)($customer['property_type'] ?? '')),
                (int)($calc['backup_hours'] ?? 24),
                isset($customer['user_id']) ? (int)$customer['user_id'] : null,
                (int)($calc['total_load_w'] ?? 0),
                (float)($calc['daily_kwh'] ?? 0),
                (float)($calc['required_pv_kw'] ?? 0),
                (int)($calc['panels_qty'] ?? 0),
                (float)($calc['required_inverter_kw'] ?? 0),
                (float)($calc['required_battery_kwh'] ?? 0),
                (float)($calc['grand_total'] ?? 0),
                (float)($calc['monthly_savings'] ?? 0),
                (float)($calc['payback_years'] ?? 0),
                (float)($calc['co2_tons_year'] ?? 0),
            ]);
            $proposalId = (int)$db->lastInsertId();

            if ($proposalId > 0) {
                $itemStmt = $db->prepare("
                    INSERT INTO solar_proposal_items
                    (proposal_id, item_type, listing_id, description, qty, unit_price, line_total)
                    VALUES (?,?,?,?,?,?,?)
                ");
                foreach ($calc['items'] as $item) {
                    $itemStmt->execute([
                        $proposalId,
                        (string)($item['type'] ?? ''),
                        $item['listing_id'] !== null ? (int)$item['listing_id'] : null,
                        (string)($item['description'] ?? ''),
                        (int)($item['qty'] ?? 1),
                        (float)($item['unit_price'] ?? 0),
                        (float)($item['line_total'] ?? 0),
                    ]);
                }
            }
            return $reference;
        } catch (Throwable $e) {
            error_log('kinas_solar_save_proposal error: ' . $e->getMessage());
            return null;
        }
    }
}
