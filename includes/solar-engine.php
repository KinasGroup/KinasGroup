<?php
/**
 * KINAS GROUP — Solar Bundle Calculation Engine
 * FIXED:
 * - No fake 550W / ₦450,000 reference panel fallback.
 * - Uses real panel products only.
 * - Chooses best-fit panel instead of first panel.
 * - Chooses smallest suitable power station/generator.
 * - Applies battery DOD/efficiency to synthesized battery capacities.
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
            'default_panel_wattage'  => 0.0,
            'co2_kg_per_kwh'         => 0.85,
            'default_panel_price'    => 0.0,
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

            $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($products as &$product) {
                $product['price'] = (float)($product['price'] ?? 0);
                $product['priority'] = (int)($product['priority'] ?? 99);
                $product['panel_wattage_w'] = (float)($product['panel_wattage_w'] ?? 0);
                $product['inverter_capacity_kva'] = (float)($product['inverter_capacity_kva'] ?? 0);
                $product['continuous_kw'] = (float)($product['continuous_kw'] ?? 0);
                $product['battery_capacity_kwh'] = (float)($product['battery_capacity_kwh'] ?? 0);
                $product['usable_battery_kwh'] = (float)($product['usable_battery_kwh'] ?? 0);

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
            }
            unset($product);
        } catch (Throwable $e) {
            $products = [];
        }

        // Synthesize calculator products from partitioned solar_listings
        // where they are not already represented in solar_calculator_products.
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
        } catch (Throwable $e) {
            // Do not block calculation if synthesis fails.
        }

        return $products;
    }
}

if (!function_exists('kinas_solar_calculate')) {
    function kinas_solar_calculate(PDO $db, array $input): array
    {
        $settings = kinas_solar_get_settings($db);
        $products = kinas_solar_get_products($db, $settings);

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

        $dailyKwh = $dailyWh / 1000;
        $designDailyKwh = $dailyKwh * (1 + ((float)$settings['load_margin_pct'] / 100));

        $sunHours = max(1.0, (float)$settings['sun_hours_default']);
        $performanceRatio = max(0.1, min(1.0, (float)$settings['pv_performance_ratio']));

        $requiredPvKw = $designDailyKwh / ($sunHours * $performanceRatio);
        $requiredInverterKw = ($totalLoadW * max(0.1, (float)$settings['inverter_safety_factor'])) / 1000;

        $dod = max(0.1, min(1.0, (float)$settings['battery_dod_pct'] / 100));
        $batteryEfficiency = max(0.1, min(1.0, (float)$settings['battery_efficiency_pct'] / 100));

        $requiredBatteryKwh = ($dailyKwh * ($backupHours / 24)) / ($dod * $batteryEfficiency);

        $warnings = [];
        $items = [];

        // ------------------------------------------------------------
        // PANEL SELECTION
        // Choose best-fit real panel, not first panel and not fallback.
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

        $requiredPvW = max(1.0, (float)$requiredPvKw * 1000);

        usort($panelCandidates, function ($a, $b) use ($requiredPvW) {
            $aWatt = (float)$a['panel_wattage_w'];
            $bWatt = (float)$b['panel_wattage_w'];

            $aQty = (int)max(1, ceil($requiredPvW / $aWatt));
            $bQty = (int)max(1, ceil($requiredPvW / $bWatt));

            $aActual = $aQty * $aWatt;
            $bActual = $bQty * $bWatt;

            $aOvershoot = $aActual - $requiredPvW;
            $bOvershoot = $bActual - $requiredPvW;

            if ($aOvershoot != $bOvershoot) {
                return $aOvershoot <=> $bOvershoot;
            }

            if ($aQty != $bQty) {
                return $aQty <=> $bQty;
            }

            if ((float)$a['price'] != (float)$b['price']) {
                return (float)$a['price'] <=> (float)$b['price'];
            }

            return (int)$a['priority'] <=> (int)$b['priority'];
        });

        $panelProduct = $panelCandidates[0];
        $panelW = (float)$panelProduct['panel_wattage_w'];
        $panelQty = (int)max(1, ceil($requiredPvW / $panelW));
        $panelUnitPrice = (float)$panelProduct['price'];
        $panelDesc = (string)$panelProduct['title'];
        $panelListingId = (int)$panelProduct['listing_id'];

        $actualPvKw = ($panelQty * $panelW) / 1000;

        $items[] = [
            'type' => 'panel',
            'listing_id' => $panelListingId,
            'description' => $panelDesc,
            'qty' => $panelQty,
            'unit_price' => $panelUnitPrice,
            'line_total' => $panelQty * $panelUnitPrice,
        ];

        // ------------------------------------------------------------
        // GENERATOR / POWER STATION SELECTION
        // Choose smallest suitable unit, not first matching unit.
        // ------------------------------------------------------------
        $generators = array_values(array_filter($products, function ($product) {
            return ($product['product_type'] ?? '') === 'generator'
                && (float)($product['continuous_kw'] ?? 0) > 0
                && (float)($product['usable_battery_kwh'] ?? 0) > 0;
        }));

        $chosenGenerator = null;
        $recommendedInvKw = 0.0;
        $recommendedBattKwh = 0.0;
        $powerSourceLabel = '';

        $eligibleGenerators = array_values(array_filter($generators, function ($generator) use ($requiredInverterKw, $requiredBatteryKwh) {
            return (float)$generator['continuous_kw'] >= $requiredInverterKw
                && (float)$generator['usable_battery_kwh'] >= $requiredBatteryKwh;
        }));

        if (!empty($eligibleGenerators)) {
            usort($eligibleGenerators, function ($a, $b) {
                $aScore = [
                    (float)$a['usable_battery_kwh'],
                    (float)$a['continuous_kw'],
                    (float)$a['price'],
                    (int)$a['priority'],
                ];

                $bScore = [
                    (float)$b['usable_battery_kwh'],
                    (float)$b['continuous_kw'],
                    (float)$b['price'],
                    (int)$b['priority'],
                ];

                return $aScore <=> $bScore;
            });

            $chosenGenerator = $eligibleGenerators[0];
            $recommendedInvKw = (float)$chosenGenerator['continuous_kw'];
            $recommendedBattKwh = (float)$chosenGenerator['usable_battery_kwh'];
            $powerSourceLabel = (string)$chosenGenerator['title'];

            $items[] = [
                'type' => 'generator',
                'listing_id' => (int)$chosenGenerator['listing_id'],
                'description' => $powerSourceLabel,
                'qty' => 1,
                'unit_price' => (float)$chosenGenerator['price'],
                'line_total' => (float)$chosenGenerator['price'],
            ];
        } else {
            // No generator fully satisfies battery requirement.
            // Try units that can at least handle the inverter load.
            $continuousOkGenerators = array_values(array_filter($generators, function ($generator) use ($requiredInverterKw) {
                return (float)$generator['continuous_kw'] >= $requiredInverterKw;
            }));

            if (!empty($continuousOkGenerators)) {
                // Pick the one with the largest usable battery, then lowest continuous kW, then price.
                usort($continuousOkGenerators, function ($a, $b) {
                    if ((float)$a['usable_battery_kwh'] != (float)$b['usable_battery_kwh']) {
                        return (float)$b['usable_battery_kwh'] <=> (float)$a['usable_battery_kwh'];
                    }

                    if ((float)$a['continuous_kw'] != (float)$b['continuous_kw']) {
                        return (float)$a['continuous_kw'] <=> (float)$b['continuous_kw'];
                    }

                    if ((float)$a['price'] != (float)$b['price']) {
                        return (float)$a['price'] <=> (float)$b['price'];
                    }

                    return (int)$a['priority'] <=> (int)$b['priority'];
                });

                $chosenGenerator = $continuousOkGenerators[0];
                $recommendedInvKw = (float)$chosenGenerator['continuous_kw'];
                $recommendedBattKwh = (float)$chosenGenerator['usable_battery_kwh'];
                $powerSourceLabel = (string)$chosenGenerator['title'];

                $items[] = [
                    'type' => 'generator',
                    'listing_id' => (int)$chosenGenerator['listing_id'],
                    'description' => $powerSourceLabel,
                    'qty' => 1,
                    'unit_price' => (float)$chosenGenerator['price'],
                    'line_total' => (float)$chosenGenerator['price'],
                ];

                if ($recommendedBattKwh < $requiredBatteryKwh) {
                    $warnings[] = 'Selected power station battery is below the estimated requirement for ' . $backupHours . 'h backup.';
                }
            } elseif (!empty($generators)) {
                // No generator can handle the inverter load.
                // Choose largest available generator and warn.
                usort($generators, function ($a, $b) {
                    if ((float)$a['continuous_kw'] != (float)$b['continuous_kw']) {
                        return (float)$b['continuous_kw'] <=> (float)$a['continuous_kw'];
                    }

                    if ((float)$a['usable_battery_kwh'] != (float)$b['usable_battery_kwh']) {
                        return (float)$b['usable_battery_kwh'] <=> (float)$a['usable_battery_kwh'];
                    }

                    if ((float)$a['price'] != (float)$b['price']) {
                        return (float)$a['price'] <=> (float)$b['price'];
                    }

                    return (int)$a['priority'] <=> (int)$b['priority'];
                });

                $chosenGenerator = $generators[0];
                $recommendedInvKw = (float)$chosenGenerator['continuous_kw'];
                $recommendedBattKwh = (float)$chosenGenerator['usable_battery_kwh'];
                $powerSourceLabel = (string)$chosenGenerator['title'];

                $items[] = [
                    'type' => 'generator',
                    'listing_id' => (int)$chosenGenerator['listing_id'],
                    'description' => $powerSourceLabel,
                    'qty' => 1,
                    'unit_price' => (float)$chosenGenerator['price'],
                    'line_total' => (float)$chosenGenerator['price'],
                ];

                $warnings[] = 'Requirement exceeds largest listed power station. Custom solution recommended.';
            } else {
                // No power station exists. Try separate inverter + battery.
                $inverters = array_values(array_filter($products, function ($product) {
                    return ($product['product_type'] ?? '') === 'inverter'
                        && (float)($product['continuous_kw'] ?? 0) > 0;
                }));

                $batteries = array_values(array_filter($products, function ($product) {
                    return ($product['product_type'] ?? '') === 'battery'
                        && (float)($product['usable_battery_kwh'] ?? 0) > 0;
                }));

                $eligibleInverters = array_values(array_filter($inverters, function ($inverter) use ($requiredInverterKw) {
                    return (float)$inverter['continuous_kw'] >= $requiredInverterKw;
                }));

                $eligibleBatteries = array_values(array_filter($batteries, function ($battery) use ($requiredBatteryKwh) {
                    return (float)$battery['usable_battery_kwh'] >= $requiredBatteryKwh;
                }));

                usort($eligibleInverters, function ($a, $b) {
                    if ((float)$a['continuous_kw'] != (float)$b['continuous_kw']) {
                        return (float)$a['continuous_kw'] <=> (float)$b['continuous_kw'];
                    }

                    if ((float)$a['price'] != (float)$b['price']) {
                        return (float)$a['price'] <=> (float)$b['price'];
                    }

                    return (int)$a['priority'] <=> (int)$b['priority'];
                });

                usort($eligibleBatteries, function ($a, $b) {
                    if ((float)$a['usable_battery_kwh'] != (float)$b['usable_battery_kwh']) {
                        return (float)$a['usable_battery_kwh'] <=> (float)$b['usable_battery_kwh'];
                    }

                    if ((float)$a['price'] != (float)$b['price']) {
                        return (float)$a['price'] <=> (float)$b['price'];
                    }

                    return (int)$a['priority'] <=> (int)$b['priority'];
                });

                $bestInverter = $eligibleInverters[0] ?? null;
                $bestBattery = $eligibleBatteries[0] ?? null;

                if ($bestInverter !== null && $bestBattery !== null) {
                    $recommendedInvKw = (float)$bestInverter['continuous_kw'];
                    $recommendedBattKwh = (float)$bestBattery['usable_battery_kwh'];
                    $powerSourceLabel = $bestInverter['title'] . ' + ' . $bestBattery['title'];

                    $items[] = [
                        'type' => 'inverter',
                        'listing_id' => (int)$bestInverter['listing_id'],
                        'description' => $bestInverter['title'],
                        'qty' => 1,
                        'unit_price' => (float)$bestInverter['price'],
                        'line_total' => (float)$bestInverter['price'],
                    ];

                    $items[] = [
                        'type' => 'battery',
                        'listing_id' => (int)$bestBattery['listing_id'],
                        'description' => $bestBattery['title'],
                        'qty' => 1,
                        'unit_price' => (float)$bestBattery['price'],
                        'line_total' => (float)$bestBattery['price'],
                    ];
                } else {
                    $warnings[] = 'No inverter/battery products are configured. The estimate covers panels only — contact KINAS VOLT for the full system price.';
                }
            }
        }

        $grandTotal = 0.0;

        foreach ($items as $item) {
            $grandTotal += (float)$item['line_total'];
        }

        $monthlyGenerationKwh = $actualPvKw * $sunHours * 30 * $performanceRatio;
        $monthlyConsumptionKwh = $dailyKwh * 30;
        $billableKwh = min($monthlyGenerationKwh, $monthlyConsumptionKwh);

        $monthlySavings = $billableKwh * max(0, (float)$settings['electricity_tariff_ngn']);
        $annualSavings = $monthlySavings * 12;

        $paybackYears = ($annualSavings > 0) ? $grandTotal / $annualSavings : 0;
        $roi20Years = ($grandTotal > 0) ? (($annualSavings * 20) / $grandTotal) * 100 : 0;
        $co2TonsYear = ($dailyKwh * 365 * max(0, (float)$settings['co2_kg_per_kwh'])) / 1000;

        return [
            'success' => true,
            'appliances' => $appliances,
            'backup_hours' => $backupHours,
            'total_load_w' => (int)round($totalLoadW),
            'daily_kwh' => round($dailyKwh, 2),
            'design_daily_kwh' => round($designDailyKwh, 2),
            'required_pv_kw' => round($requiredPvKw, 2),
            'recommended_pv_kw' => round($actualPvKw, 2),
            'panels_qty' => $panelQty,
            'panel_wattage_w' => $panelW,
            'panel_description' => $panelDesc,
            'required_inverter_kw' => round($requiredInverterKw, 2),
            'recommended_inverter_kw' => round($recommendedInvKw, 2),
            'required_battery_kwh' => round($requiredBatteryKwh, 2),
            'recommended_battery_kwh' => round($recommendedBattKwh, 2),
            'power_source_label' => $powerSourceLabel,
            'items' => $items,
            'grand_total' => round($grandTotal),
            'monthly_generation_kwh' => round($monthlyGenerationKwh, 1),
            'monthly_consumption_kwh' => round($monthlyConsumptionKwh, 1),
            'monthly_savings' => round($monthlySavings),
            'annual_savings' => round($annualSavings),
            'payback_years' => round($paybackYears, 1),
            'roi_20_years' => round($roi20Years, 1),
            'co2_tons_year' => round($co2TonsYear, 2),
            'warnings' => $warnings,
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
