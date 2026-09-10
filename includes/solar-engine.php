<?php
/**
 * KINAS GROUP — Solar Calculation Engine (OPTION B — DUAL-OPTION REBUILD)
 *
 * Every calculation now returns TWO quotable options:
 *
 *   options.generator : All-in-One Solar Generator
 *                       (power station + panels fitted inside the unit's
 *                        Max PV Input cap; panel cost included in the total)
 *   options.custom    : Custom-Built System
 *                       (panels + inverter + ONE battery; no battery banks)
 *
 * Rules locked with the client:
 *  - STRICT MODE: a generator is only quotable when max_pv_input_w > 0.
 *    Units without a configured cap are excluded (never guessed).
 *  - Generator option price INCLUDES its panels.
 *  - Custom option uses a single battery only.
 *  - NO fake/reference panel and NO reference price — ever.
 *  - All prices come live from solar_listings.
 *  - Legacy top-level keys are kept (from the primary option) so older
 *    UI/PDF/email code keeps working during rollout.
 */

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
            'co2_kg_per_kwh'         => 0.85,
            'electricity_tariff_ngn' => 225.0,
        ];
    }
}

if (!function_exists('kinas_solar_get_settings')) {
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
            // Keep defaults.
        }
        return $settings;
    }
}

if (!function_exists('kinas_solar_get_products')) {
    function kinas_solar_get_products(PDO $db, array $settings = []): array
    {
        $settings = array_merge(kinas_solar_default_settings(), $settings);
        $dod = max(0.1, min(1.0, (float)$settings['battery_dod_pct'] / 100));
        $batteryEfficiency = max(0.1, min(1.0, (float)$settings['battery_efficiency_pct'] / 100));

        $products = [];

        // ------------------------------------------------------------
        // 1. Configured calculator products (with Max PV Input cap)
        // ------------------------------------------------------------
        $rows = [];
        $hasMax = true;
        try {
            $stmt = $db->query("
                SELECT
                    p.id,
                    p.listing_id,
                    p.product_type,
                    p.panel_wattage_w,
                    p.inverter_capacity_kva,
                    p.continuous_kw,
                    p.battery_capacity_kwh,
                    p.usable_battery_kwh,
                    p.battery_voltage_v,
                    p.expandable,
                    p.priority,
                    p.max_pv_input_w,
                    l.title,
                    l.brand,
                    l.price,
                    l.max_pv_input_w AS listing_max_pv_input_w
                FROM solar_calculator_products p
                JOIN solar_listings l ON l.id = p.listing_id
                WHERE p.active = 1
                  AND l.status = 'active'
                  AND l.price IS NOT NULL
                  AND l.price > 0
                ORDER BY p.priority ASC, l.price ASC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $hasMax = false;
            try {
                $stmt = $db->query("
                    SELECT
                        p.id,
                        p.listing_id,
                        p.product_type,
                        p.panel_wattage_w,
                        p.inverter_capacity_kva,
                        p.continuous_kw,
                        p.battery_capacity_kwh,
                        p.usable_battery_kwh,
                        p.battery_voltage_v,
                        p.expandable,
                        p.priority,
                        l.title,
                        l.brand,
                        l.price
                    FROM solar_calculator_products p
                    JOIN solar_listings l ON l.id = p.listing_id
                    WHERE p.active = 1
                      AND l.status = 'active'
                      AND l.price IS NOT NULL
                      AND l.price > 0
                    ORDER BY p.priority ASC, l.price ASC
                ");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e2) {
                $rows = [];
            }
        }

        foreach ($rows as $row) {
            if (!$hasMax) {
                $row['max_pv_input_w'] = 0;
                $row['listing_max_pv_input_w'] = 0;
            }

            $product = $row;
            $product['price'] = (float)($row['price'] ?? 0);
            $product['priority'] = (int)($row['priority'] ?? 99);
            $product['panel_wattage_w'] = (float)($row['panel_wattage_w'] ?? 0);
            $product['inverter_capacity_kva'] = (float)($row['inverter_capacity_kva'] ?? 0);
            $product['continuous_kw'] = (float)($row['continuous_kw'] ?? 0);
            $product['battery_capacity_kwh'] = (float)($row['battery_capacity_kwh'] ?? 0);
            $product['usable_battery_kwh'] = (float)($row['usable_battery_kwh'] ?? 0);

            $maxPv = (float)($row['max_pv_input_w'] ?? 0);
            if ($maxPv <= 0) {
                $maxPv = (float)($row['listing_max_pv_input_w'] ?? 0);
            }
            $product['max_pv_input_w'] = $maxPv;

            if (
                ($product['product_type'] ?? '') === 'generator'
                && $product['usable_battery_kwh'] <= 0
                && $product['battery_capacity_kwh'] > 0
            ) {
                $product['usable_battery_kwh'] = round(
                    $product['battery_capacity_kwh'] * $dod * $batteryEfficiency,
                    2
                );
            }

            $products[] = $product;
        }

        // ------------------------------------------------------------
        // 2. Synthesize products from partitioned solar_listings that
        //    are not yet represented in solar_calculator_products.
        // ------------------------------------------------------------
        $listingRows = [];
        $synthHasMax = true;
        try {
            $stmt = $db->query("
                SELECT
                    id,
                    title,
                    brand,
                    price,
                    hardware_type,
                    panel_watts,
                    inverter_kva,
                    battery_kwh,
                    max_pv_input_w
                FROM solar_listings
                WHERE status = 'active'
                  AND price IS NOT NULL
                  AND price > 0
                  AND hardware_type IS NOT NULL
            ");
            $listingRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $synthHasMax = false;
            try {
                $stmt = $db->query("
                    SELECT
                        id,
                        title,
                        brand,
                        price,
                        hardware_type,
                        panel_watts,
                        inverter_kva,
                        battery_kwh
                    FROM solar_listings
                    WHERE status = 'active'
                      AND price IS NOT NULL
                      AND price > 0
                      AND hardware_type IS NOT NULL
                ");
                $listingRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e2) {
                $listingRows = [];
            }
        }

        foreach ($listingRows as $row) {
            $represented = false;
            foreach ($products as $product) {
                if ((int)$product['listing_id'] === (int)$row['id']) {
                    $represented = true;
                    break;
                }
            }
            if ($represented) {
                continue;
            }

            $base = [
                'id' => null,
                'listing_id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'brand' => (string)($row['brand'] ?? ''),
                'price' => (float)$row['price'],
                'priority' => 99,
                'expandable' => 0,
                'battery_voltage_v' => null,
                'inverter_capacity_kva' => null,
                'battery_capacity_kwh' => null,
                'max_pv_input_w' => $synthHasMax ? (float)($row['max_pv_input_w'] ?? 0) : 0.0,
            ];

            $hardwareType = strtolower((string)$row['hardware_type']);

            if ($hardwareType === 'solar_panel') {
                $panelWatts = (float)$row['panel_watts'];
                if ($panelWatts <= 0) {
                    continue;
                }
                $products[] = array_merge($base, [
                    'product_type' => 'panel',
                    'panel_wattage_w' => $panelWatts,
                    'continuous_kw' => null,
                    'usable_battery_kwh' => null,
                ]);
            } elseif ($hardwareType === 'inverter') {
                $inverterKw = (float)$row['inverter_kva'];
                if ($inverterKw <= 0) {
                    continue;
                }
                $products[] = array_merge($base, [
                    'product_type' => 'inverter',
                    'panel_wattage_w' => null,
                    'continuous_kw' => $inverterKw,
                    'usable_battery_kwh' => 0,
                ]);
            } elseif ($hardwareType === 'battery') {
                $batteryKwh = (float)$row['battery_kwh'];
                if ($batteryKwh <= 0) {
                    continue;
                }
                $products[] = array_merge($base, [
                    'product_type' => 'battery',
                    'panel_wattage_w' => null,
                    'continuous_kw' => 0,
                    'usable_battery_kwh' => round($batteryKwh * $dod * $batteryEfficiency, 2),
                ]);
            } elseif ($hardwareType === 'power_station') {
                $continuousKw = (float)$row['inverter_kva'];
                $batteryKwh = (float)$row['battery_kwh'];
                if ($continuousKw <= 0 || $batteryKwh <= 0) {
                    continue;
                }
                $products[] = array_merge($base, [
                    'product_type' => 'generator',
                    'panel_wattage_w' => null,
                    'continuous_kw' => $continuousKw,
                    'usable_battery_kwh' => round($batteryKwh * $dod * $batteryEfficiency, 2),
                ]);
            }
        }

        return $products;
    }
}

if (!function_exists('kinas_solar_calculate')) {
    function kinas_solar_calculate(PDO $db, array $input): array
    {
        $settings = kinas_solar_get_settings($db);
        $products = kinas_solar_get_products($db, $settings);

        // ------------------------------------------------------------
        // Parse appliances
        // ------------------------------------------------------------
        $appliances = [];
        $rawAppliances = $input['appliances'] ?? [];
        if (is_string($rawAppliances)) {
            $rawAppliances = json_decode($rawAppliances, true) ?: [];
        }
        foreach ($rawAppliances as $appliance) {
            if (!is_array($appliance)) {
                continue;
            }
            $name = trim((string)($appliance['name'] ?? ''));
            $quantity = (int)round((float)($appliance['quantity'] ?? $appliance['qty'] ?? 1));
            $watts = (float)($appliance['watts'] ?? $appliance['watt'] ?? 0);
            $hours = (float)($appliance['hours'] ?? 0);
            if ($name === '' || $quantity < 1 || $watts <= 0) {
                continue;
            }
            $hours = max(0, min(24, $hours));
            $appliances[] = [
                'name' => $name,
                'quantity' => $quantity,
                'watts' => $watts,
                'hours' => $hours,
            ];
        }
        if (empty($appliances)) {
            return [
                'success' => false,
                'error' => 'Please add at least one appliance with a valid wattage.',
            ];
        }

        $backupHours = max(1, min(120, (int)round((float)($input['backup_hours'] ?? 24))));

        $totalLoadW = 0.0;
        $dailyWh = 0.0;
        foreach ($appliances as $appliance) {
            $totalLoadW += $appliance['quantity'] * $appliance['watts'];
            $dailyWh += $appliance['quantity'] * $appliance['watts'] * $appliance['hours'];
        }
        if ($totalLoadW <= 0 || $dailyWh <= 0) {
            return [
                'success' => false,
                'error' => 'Total load calculation failed.',
            ];
        }

        // ------------------------------------------------------------
        // Requirements
        // ------------------------------------------------------------
        $dailyKwh = $dailyWh / 1000;
        $designDailyKwh = $dailyKwh * (1 + ((float)$settings['load_margin_pct'] / 100));
        $sunHours = max(1.0, (float)$settings['sun_hours_default']);
        $performanceRatio = max(0.1, min(1.0, (float)$settings['pv_performance_ratio']));
        $requiredPvKw = $designDailyKwh / ($sunHours * $performanceRatio);
        $requiredInverterKw = ($totalLoadW * max(0.1, (float)$settings['inverter_safety_factor'])) / 1000;
        $dod = max(0.1, min(1.0, (float)$settings['battery_dod_pct'] / 100));
        $batteryEfficiency = max(0.1, min(1.0, (float)$settings['battery_efficiency_pct'] / 100));
        $requiredBatteryKwh = ($dailyKwh * ($backupHours / 24)) / ($dod * $batteryEfficiency);
        $requiredPvW = max(1.0, (float)$requiredPvKw * 1000);

        // ------------------------------------------------------------
        // Panel pool + cap-aware best-fit helper
        // ------------------------------------------------------------
        $panelCandidates = array_values(array_filter($products, function ($product) {
            return ($product['product_type'] ?? '') === 'panel'
                && (float)($product['panel_wattage_w'] ?? 0) > 0;
        }));
        if (empty($panelCandidates)) {
            return [
                'success' => false,
                'error' => 'No active solar panel product is available. Please ensure KINAS VOLT panel listings are active and have Panel Capacity (W) set.',
            ];
        }

        $bestPanel = function (?float $cap) use ($panelCandidates, $requiredPvW) {
            $best = null;
            foreach ($panelCandidates as $p) {
                $w = (float)$p['panel_wattage_w'];
                if ($w <= 0) {
                    continue;
                }
                if ($cap !== null && $w > $cap) {
                    continue; // a single panel already exceeds the unit's input cap
                }
                $qty = (int)max(1, ceil($requiredPvW / $w));
                $actual = $qty * $w;
                if ($cap !== null && $actual > $cap) {
                    continue; // the array would exceed the unit's input cap
                }
                $score = [$actual - $requiredPvW, $qty, (float)$p['price'], (int)$p['priority']];
                if ($best === null || $score < $best['score']) {
                    $best = [
                        'product' => $p,
                        'qty' => $qty,
                        'actual_w' => $actual,
                        'score' => $score,
                    ];
                }
            }
            return $best;
        };

        // ------------------------------------------------------------
        // Per-option financials
        // ------------------------------------------------------------
        $fin = function (float $actualPvKw, float $grandTotal) use ($sunHours, $performanceRatio, $dailyKwh, $settings): array {
            $monthlyGenerationKwh = $actualPvKw * $sunHours * 30 * $performanceRatio;
            $monthlyConsumptionKwh = $dailyKwh * 30;
            $billableKwh = min($monthlyGenerationKwh, $monthlyConsumptionKwh);
            $monthlySavings = $billableKwh * max(0, (float)$settings['electricity_tariff_ngn']);
            $annualSavings = $monthlySavings * 12;
            $paybackYears = ($annualSavings > 0) ? $grandTotal / $annualSavings : 0;
            $roi20Years = ($grandTotal > 0) ? (($annualSavings * 20) / $grandTotal) * 100 : 0;
            $co2TonsYear = ($dailyKwh * 365 * max(0, (float)$settings['co2_kg_per_kwh'])) / 1000;
            return [
                'monthly_generation_kwh' => round($monthlyGenerationKwh, 1),
                'monthly_consumption_kwh' => round($monthlyConsumptionKwh, 1),
                'monthly_savings' => round($monthlySavings),
                'annual_savings' => round($annualSavings),
                'payback_years' => round($paybackYears, 1),
                'roi_20_years' => round($roi20Years, 1),
                'co2_tons_year' => round($co2TonsYear, 2),
            ];
        };

        $emptyOption = function (string $key, string $label): array {
            return [
                'key' => $key,
                'label' => $label,
                'available' => false,
                'reason' => null,
                'items' => [],
                'grand_total' => 0.0,
                'panels_qty' => 0,
                'panel_wattage_w' => 0.0,
                'panel_description' => '',
                'power_source_label' => '',
                'max_pv_input_w' => null,
                'recommended_pv_kw' => 0.0,
                'recommended_inverter_kw' => 0.0,
                'recommended_battery_kwh' => 0.0,
                'monthly_generation_kwh' => 0.0,
                'monthly_consumption_kwh' => 0.0,
                'monthly_savings' => 0.0,
                'annual_savings' => 0.0,
                'payback_years' => 0.0,
                'roi_20_years' => 0.0,
                'co2_tons_year' => 0.0,
            ];
        };

        // ============================================================
        // OPTION 1 — ALL-IN-ONE SOLAR GENERATOR (STRICT MODE)
        // ============================================================
        $optionA = $emptyOption('generator', 'All-in-One Solar Generator');

        $generators = array_values(array_filter($products, function ($product) {
            return ($product['product_type'] ?? '') === 'generator'
                && (float)($product['continuous_kw'] ?? 0) > 0
                && (float)($product['usable_battery_kwh'] ?? 0) > 0;
        }));

        $candidatesA = [];
        $capConfigured = 0;

        foreach ($generators as $g) {
            $cap = (float)($g['max_pv_input_w'] ?? 0);
            if ($cap <= 0) {
                continue; // STRICT MODE: unconfigured cap = not quotable
            }
            $capConfigured++;
            if ((float)$g['continuous_kw'] < $requiredInverterKw) {
                continue;
            }
            if ((float)$g['usable_battery_kwh'] < $requiredBatteryKwh) {
                continue;
            }
            $fit = $bestPanel($cap);
            if ($fit === null) {
                continue; // no panel array fits inside this unit's input cap
            }
            $total = ($fit['qty'] * (float)$fit['product']['price']) + (float)$g['price'];
            $candidatesA[] = ['generator' => $g, 'fit' => $fit, 'total' => $total];
        }

        if (!empty($candidatesA)) {
            usort($candidatesA, function ($a, $b) {
                $ga = $a['generator'];
                $gb = $b['generator'];
                return [
                    (float)$ga['usable_battery_kwh'],
                    (float)$ga['continuous_kw'],
                    $a['total'],
                    (int)$ga['priority'],
                ] <=> [
                    (float)$gb['usable_battery_kwh'],
                    (float)$gb['continuous_kw'],
                    $b['total'],
                    (int)$gb['priority'],
                ];
            });

            $pick = $candidatesA[0];
            $g = $pick['generator'];
            $fit = $pick['fit'];
            $panel = $fit['product'];
            $actualPvKw = $fit['actual_w'] / 1000;

            $items = [
                [
                    'type' => 'panel',
                    'listing_id' => (int)$panel['listing_id'],
                    'description' => (string)$panel['title'],
                    'qty' => $fit['qty'],
                    'unit_price' => (float)$panel['price'],
                    'line_total' => $fit['qty'] * (float)$panel['price'],
                ],
                [
                    'type' => 'generator',
                    'listing_id' => (int)$g['listing_id'],
                    'description' => (string)$g['title'],
                    'qty' => 1,
                    'unit_price' => (float)$g['price'],
                    'line_total' => (float)$g['price'],
                ],
            ];

            $optionA = array_merge($optionA, [
                'available' => true,
                'reason' => null,
                'items' => $items,
                'grand_total' => round($pick['total']),
                'panels_qty' => $fit['qty'],
                'panel_wattage_w' => (float)$panel['panel_wattage_w'],
                'panel_description' => (string)$panel['title'],
                'power_source_label' => (string)$g['title'],
                'max_pv_input_w' => (float)$g['max_pv_input_w'],
                'recommended_pv_kw' => round($actualPvKw, 2),
                'recommended_inverter_kw' => (float)$g['continuous_kw'],
                'recommended_battery_kwh' => (float)$g['usable_battery_kwh'],
            ], $fin($actualPvKw, $pick['total']));
        } else {
            $optionA['reason'] = ($capConfigured === 0)
                ? 'No all-in-one Solar Generator has Max Panel Input (W) configured yet.'
                : 'No all-in-one Solar Generator meets your load, backup and panel-input requirements.';
        }

        // ============================================================
        // OPTION 2 — CUSTOM-BUILT SYSTEM (panels + inverter + ONE battery)
        // ============================================================
        $optionB = $emptyOption('custom', 'Custom-Built System');

        $fitB = $bestPanel(null);

        $inverters = array_values(array_filter($products, function ($product) {
            return ($product['product_type'] ?? '') === 'inverter'
                && (float)($product['continuous_kw'] ?? 0) > 0;
        }));
        $batteries = array_values(array_filter($products, function ($product) {
            return ($product['product_type'] ?? '') === 'battery'
                && (float)($product['usable_battery_kwh'] ?? 0) > 0;
        }));

        $eligibleInverters = array_values(array_filter($inverters, function ($product) use ($requiredInverterKw) {
            return (float)$product['continuous_kw'] >= $requiredInverterKw;
        }));
        $eligibleBatteries = array_values(array_filter($batteries, function ($product) use ($requiredBatteryKwh) {
            return (float)$product['usable_battery_kwh'] >= $requiredBatteryKwh;
        }));

        usort($eligibleInverters, function ($a, $b) {
            return [
                (float)$a['continuous_kw'],
                (float)$a['price'],
                (int)$a['priority'],
            ] <=> [
                (float)$b['continuous_kw'],
                (float)$b['price'],
                (int)$b['priority'],
            ];
        });

        usort($eligibleBatteries, function ($a, $b) {
            return [
                (float)$a['usable_battery_kwh'],
                (float)$a['price'],
                (int)$a['priority'],
            ] <=> [
                (float)$b['usable_battery_kwh'],
                (float)$b['price'],
                (int)$b['priority'],
            ];
        });

        if ($fitB === null) {
            $optionB['reason'] = 'No solar panel product is available.';
        } elseif (empty($eligibleInverters)) {
            $optionB['reason'] = 'No single inverter can handle your total load.';
        } elseif (empty($eligibleBatteries)) {
            $optionB['reason'] = 'No single battery meets your backup requirement (multiple batteries are not quoted automatically).';
        } else {
            $panel = $fitB['product'];
            $inv = $eligibleInverters[0];
            $bat = $eligibleBatteries[0];
            $actualPvKw = $fitB['actual_w'] / 1000;

            $items = [
                [
                    'type' => 'panel',
                    'listing_id' => (int)$panel['listing_id'],
                    'description' => (string)$panel['title'],
                    'qty' => $fitB['qty'],
                    'unit_price' => (float)$panel['price'],
                    'line_total' => $fitB['qty'] * (float)$panel['price'],
                ],
                [
                    'type' => 'inverter',
                    'listing_id' => (int)$inv['listing_id'],
                    'description' => (string)$inv['title'],
                    'qty' => 1,
                    'unit_price' => (float)$inv['price'],
                    'line_total' => (float)$inv['price'],
                ],
                [
                    'type' => 'battery',
                    'listing_id' => (int)$bat['listing_id'],
                    'description' => (string)$bat['title'],
                    'qty' => 1,
                    'unit_price' => (float)$bat['price'],
                    'line_total' => (float)$bat['price'],
                ],
            ];

            $total = 0.0;
            foreach ($items as $item) {
                $total += (float)$item['line_total'];
            }

            $optionB = array_merge($optionB, [
                'available' => true,
                'reason' => null,
                'items' => $items,
                'grand_total' => round($total),
                'panels_qty' => $fitB['qty'],
                'panel_wattage_w' => (float)$panel['panel_wattage_w'],
                'panel_description' => (string)$panel['title'],
                'power_source_label' => (string)$inv['title'] . ' + ' . (string)$bat['title'],
                'recommended_pv_kw' => round($actualPvKw, 2),
                'recommended_inverter_kw' => (float)$inv['continuous_kw'],
                'recommended_battery_kwh' => (float)$bat['usable_battery_kwh'],
            ], $fin($actualPvKw, $total));
        }

        // ============================================================
        // Result
        // ============================================================
        if (!$optionA['available'] && !$optionB['available']) {
            return [
                'success' => false,
                'error' => 'We could not match a complete system to your requirements. '
                    . 'Generator: ' . ($optionA['reason'] ?? 'unavailable')
                    . ' Custom: ' . ($optionB['reason'] ?? 'unavailable')
                    . ' Please contact KINAS VOLT for a tailored quote.',
            ];
        }

        $primary = $optionA['available'] ? $optionA : $optionB;

        return [
            'success' => true,
            'appliances' => $appliances,
            'backup_hours' => $backupHours,
            'total_load_w' => (int)round($totalLoadW),
            'daily_kwh' => round($dailyKwh, 2),
            'design_daily_kwh' => round($designDailyKwh, 2),
            'required_pv_kw' => round($requiredPvKw, 2),
            'required_inverter_kw' => round($requiredInverterKw, 2),
            'required_battery_kwh' => round($requiredBatteryKwh, 2),
            'options' => [
                'generator' => $optionA,
                'custom' => $optionB,
            ],
            // ---- legacy single-bundle keys (primary option) for old UI/PDF ----
            'recommended_pv_kw' => $primary['recommended_pv_kw'],
            'panels_qty' => $primary['panels_qty'],
            'panel_wattage_w' => $primary['panel_wattage_w'],
            'panel_description' => $primary['panel_description'],
            'recommended_inverter_kw' => $primary['recommended_inverter_kw'],
            'recommended_battery_kwh' => $primary['recommended_battery_kwh'],
            'power_source_label' => $primary['power_source_label'],
            'items' => $primary['items'],
            'grand_total' => $primary['grand_total'],
            'monthly_generation_kwh' => $primary['monthly_generation_kwh'],
            'monthly_consumption_kwh' => $primary['monthly_consumption_kwh'],
            'monthly_savings' => $primary['monthly_savings'],
            'annual_savings' => $primary['annual_savings'],
            'payback_years' => $primary['payback_years'],
            'roi_20_years' => $primary['roi_20_years'],
            'co2_tons_year' => $primary['co2_tons_year'],
            'warnings' => [],
            'settings_used' => $settings,
        ];
    }
}

if (!function_exists('kinas_solar_make_reference')) {
    function kinas_solar_make_reference(): string
    {
        return 'SOL-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}

if (!function_exists('kinas_solar_save_proposal')) {
    function kinas_solar_save_proposal(PDO $db, array $calc, array $customer): ?string
    {
        $reference = kinas_solar_make_reference();

        try {
            $db->prepare("
                INSERT INTO solar_proposals (
                    reference,
                    full_name,
                    phone,
                    email,
                    city_state,
                    property_type,
                    backup_hours,
                    user_id,
                    total_load_w,
                    daily_kwh,
                    required_pv_kw,
                    panels_recommended,
                    required_inverter_kw,
                    required_battery_kwh,
                    total_cost,
                    monthly_savings,
                    payback_years,
                    co2_tons_year,
                    status,
                    created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW()
                )
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
                    INSERT INTO solar_proposal_items (
                        proposal_id,
                        item_type,
                        listing_id,
                        description,
                        qty,
                        unit_price,
                        line_total
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $options = $calc['options'] ?? [];

                if (!empty($options) && is_array($options)) {
                    // Dual-option: store every available option's line items,
                    // prefixed so the admin can tell them apart.
                    foreach ($options as $optionKey => $option) {
                        if (empty($option['available'])) {
                            continue;
                        }
                        foreach (($option['items'] ?? []) as $item) {
                            $itemStmt->execute([
                                $proposalId,
                                $optionKey . '_' . (string)($item['type'] ?? ''),
                                $item['listing_id'] !== null ? (int)$item['listing_id'] : null,
                                (string)($item['description'] ?? ''),
                                (int)($item['qty'] ?? 1),
                                (float)($item['unit_price'] ?? 0),
                                (float)($item['line_total'] ?? 0),
                            ]);
                        }
                    }
                } else {
                    foreach (($calc['items'] ?? []) as $item) {
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
            }

            return $reference;
        } catch (Throwable $e) {
            error_log('kinas_solar_save_proposal error: ' . $e->getMessage());
            return null;
        }
    }
}
