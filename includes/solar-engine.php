<?php
/**
 * KINAS GROUP — Solar Calculation Engine v2 (DUAL-OPTION, STRICT MODE)
 *
 * Option A: All-in-One Solar Generator (power station + panels within its max PV input cap)
 * Option B: Custom-Built System (panels + inverter + ONE battery)
 *
 * STRICT MODE: a generator is only quotable when max_pv_input_w > 0.
 * No fake reference panels, no reference prices, ever.
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
        }
        return $settings;
    }
}

if (!function_exists('kinas_solar_get_products')) {
    function kinas_solar_get_products(PDO $db, array $settings = []): array
    {
        $settings = array_merge(kinas_solar_default_settings(), $settings);
        $dod = max(0.1, min(1.0, (float)$settings['battery_dod_pct'] / 100));
        $battEff = max(0.1, min(1.0, (float)$settings['battery_efficiency_pct'] / 100));

        $products = [];

        // ---- configured calculator products (with max_pv_input_w) ----
        try {
            $stmt = $db->query("
                SELECT
                    p.id, p.listing_id, p.product_type, p.panel_wattage_w, p.inverter_capacity_kva,
                    p.continuous_kw, p.battery_capacity_kwh, p.usable_battery_kwh, p.battery_voltage_v,
                    p.expandable, p.priority, p.max_pv_input_w,
                    l.title, l.brand, l.price, l.max_pv_input_w AS listing_max_pv_input_w
                FROM solar_calculator_products p
                JOIN solar_listings l ON l.id = p.listing_id
                WHERE p.active = 1
                  AND l.status = 'active'
                  AND l.price IS NOT NULL AND l.price > 0
                ORDER BY p.priority ASC, l.price ASC
            ");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            // Fallback if the max_pv_input_w migration has not run yet.
            try {
                $stmt = $db->query("
                    SELECT
                        p.id, p.listing_id, p.product_type, p.panel_wattage_w, p.inverter_capacity_kva,
                        p.continuous_kw, p.battery_capacity_kwh, p.usable_battery_kwh, p.battery_voltage_v,
                        p.expandable, p.priority, NULL AS max_pv_input_w,
                        l.title, l.brand, l.price, NULL AS listing_max_pv_input_w
                    FROM solar_calculator_products p
                    JOIN solar_listings l ON l.id = p.listing_id
                    WHERE p.active = 1
                      AND l.status = 'active'
                      AND l.price IS NOT NULL AND l.price > 0
                    ORDER BY p.priority ASC, l.price ASC
                ");
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e2) {
                $products = [];
            }
        }

        foreach ($products as &$product) {
            $product['price'] = (float)($product['price'] ?? 0);
            $product['priority'] = (int)($product['priority'] ?? 99);
            $product['panel_wattage_w'] = (float)($product['panel_wattage_w'] ?? 0);
            $product['continuous_kw'] = (float)($product['continuous_kw'] ?? 0);
            $product['battery_capacity_kwh'] = (float)($product['battery_capacity_kwh'] ?? 0);
            $product['usable_battery_kwh'] = (float)($product['usable_battery_kwh'] ?? 0);

            $maxPv = (float)($product['max_pv_input_w'] ?? 0);
            if ($maxPv <= 0) {
                $maxPv = (float)($product['listing_max_pv_input_w'] ?? 0);
            }
            $product['max_pv_input_w'] = $maxPv;

            if (($product['product_type'] ?? '') === 'generator'
                && $product['usable_battery_kwh'] <= 0
                && $product['battery_capacity_kwh'] > 0) {
                $product['usable_battery_kwh'] = round($product['battery_capacity_kwh'] * $dod * $battEff, 2);
            }
        }
        unset($product);

        // ---- synthesize any active hardware listing not yet configured ----
        try {
            $stmt = $db->query("
                SELECT id, title, brand, price, hardware_type,
                       panel_watts, inverter_kva, battery_kwh, max_pv_input_w
                FROM solar_listings
                WHERE status = 'active'
                  AND price IS NOT NULL AND price > 0
                  AND hardware_type IS NOT NULL
            ");

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $represented = false;
                foreach ($products as $p) {
                    if ((int)$p['listing_id'] === (int)$row['id']) { $represented = true; break; }
                }
                if ($represented) continue;

                $base = [
                    'id' => null,
                    'listing_id' => (int)$row['id'],
                    'title' => (string)$row['title'],
                    'brand' => (string)($row['brand'] ?? ''),
                    'price' => (float)$row['price'],
                    'priority' => 99,
                    'expandable' => 0,
                    'battery_voltage_v' => null,
                    'inverter_capacity_kwa' => null,
                    'battery_capacity_kwh' => null,
                    'max_pv_input_w' => (float)($row['max_pv_input_w'] ?? 0),
                ];

                $t = strtolower((string)$row['hardware_type']);

                if ($t === 'solar_panel') {
                    $w = (float)$row['panel_watts'];
                    if ($w <= 0) continue;
                    $products[] = array_merge($base, ['product_type'=>'panel','panel_wattage_w'=>$w,'continuous_kw'=>null,'usable_battery_kwh'=>null]);
                } elseif ($t === 'inverter') {
                    $kw = (float)$row['inverter_kva'];
                    if ($kw <= 0) continue;
                    $products[] = array_merge($base, ['product_type'=>'inverter','panel_wattage_w'=>null,'continuous_kw'=>$kw,'usable_battery_kwh'=>0]);
                } elseif ($t === 'battery') {
                    $kwh = (float)$row['battery_kwh'];
                    if ($kwh <= 0) continue;
                    $products[] = array_merge($base, ['product_type'=>'battery','panel_wattage_w'=>null,'continuous_kw'=>0,'usable_battery_kwh'=>round($kwh*$dod*$battEff,2)]);
                } elseif ($t === 'power_station') {
                    $kw = (float)$row['inverter_kva'];
                    $kwh = (float)$row['battery_kwh'];
                    if ($kw <= 0 || $kwh <= 0) continue;
                    $products[] = array_merge($base, ['product_type'=>'generator','panel_wattage_w'=>null,'continuous_kw'=>$kw,'usable_battery_kwh'=>round($kwh*$dod*$battEff,2)]);
                }
            }
        } catch (Throwable $e) {
        }

        return $products;
    }
}

if (!function_exists('kinas_solar_calculate')) {
    function kinas_solar_calculate(PDO $db, array $input): array
    {
        $settings = kinas_solar_get_settings($db);
        $products = kinas_solar_get_products($db, $settings);

        // ---------------- parse appliances ----------------
        $appliances = [];
        $raw = $input['appliances'] ?? [];
        if (is_string($raw)) { $raw = json_decode($raw, true) ?: []; }
        foreach ($raw as $ap) {
            if (!is_array($ap)) continue;
            $name = trim((string)($ap['name'] ?? ''));
            $qty = (int)round((float)($ap['quantity'] ?? $ap['qty'] ?? 1));
            $watts = (float)($ap['watts'] ?? $ap['watt'] ?? 0);
            $hours = (float)($ap['hours'] ?? 0);
            if ($name === '' || $qty < 1 || $watts <= 0) continue;
            $hours = max(0, min(24, $hours));
            $appliances[] = ['name'=>$name,'quantity'=>$qty,'watts'=>$watts,'hours'=>$hours];
        }
        if (empty($appliances)) {
            return ['success'=>false,'error'=>'Please add at least one appliance with a valid wattage.'];
        }

        $backupHours = max(1, min(120, (int)round((float)($input['backup_hours'] ?? 24))));

        $totalLoadW = 0.0; $dailyWh = 0.0;
        foreach ($appliances as $ap) {
            $totalLoadW += $ap['quantity'] * $ap['watts'];
            $dailyWh   += $ap['quantity'] * $ap['watts'] * $ap['hours'];
        }
        if ($totalLoadW <= 0 || $dailyWh <= 0) {
            return ['success'=>false,'error'=>'Total load calculation failed.'];
        }

        $dailyKwh = $dailyWh / 1000;
        $designDailyKwh = $dailyKwh * (1 + ((float)$settings['load_margin_pct'] / 100));
        $sunHours = max(1.0, (float)$settings['sun_hours_default']);
        $pr = max(0.1, min(1.0, (float)$settings['pv_performance_ratio']));
        $requiredPvKw = $designDailyKwh / ($sunHours * $pr);
        $requiredInverterKw = ($totalLoadW * max(0.1, (float)$settings['inverter_safety_factor'])) / 1000;
        $dod = max(0.1, min(1.0, (float)$settings['battery_dod_pct'] / 100));
        $battEff = max(0.1, min(1.0, (float)$settings['battery_efficiency_pct'] / 100));
        $requiredBatteryKwh = ($dailyKwh * ($backupHours / 24)) / ($dod * $battEff);
        $requiredPvW = max(1.0, $requiredPvKw * 1000);

        // ---------------- product pools ----------------
        $panelCandidates = array_values(array_filter($products, function ($p) {
            return ($p['product_type'] ?? '') === 'panel' && (float)($p['panel_wattage_w'] ?? 0) > 0;
        }));
        if (empty($panelCandidates)) {
            return ['success'=>false,'error'=>'No active solar panel product is available. Please ensure KINAS VOLT panel listings are active and have Panel Capacity (W) set.'];
        }

        $generators = array_values(array_filter($products, function ($p) {
            return ($p['product_type'] ?? '') === 'generator'
                && (float)($p['continuous_kw'] ?? 0) > 0
                && (float)($p['usable_battery_kwh'] ?? 0) > 0;
        }));
        $inverters = array_values(array_filter($products, function ($p) {
            return ($p['product_type'] ?? '') === 'inverter' && (float)($p['continuous_kw'] ?? 0) > 0;
        }));
        $batteries = array_values(array_filter($products, function ($p) {
            return ($p['product_type'] ?? '') === 'battery' && (float)($p['usable_battery_kwh'] ?? 0) > 0;
        }));

        // Best panel fit, optionally constrained by a generator's PV input cap.
        $bestPanel = function (?float $cap) use ($panelCandidates, $requiredPvW) {
            $best = null;
            foreach ($panelCandidates as $p) {
                $w = (float)$p['panel_wattage_w'];
                if ($w <= 0) continue;
                if ($cap !== null && $w > $cap) continue;          // one panel already exceeds cap
                $qty = (int)max(1, ceil($requiredPvW / $w));
                $actual = $qty * $w;
                if ($cap !== null && $actual > $cap) continue;      // array exceeds cap
                $score = [$actual - $requiredPvW, $qty, (float)$p['price'], (int)$p['priority']];
                if ($best === null || $score < $best['score']) {
                    $best = ['product'=>$p,'qty'=>$qty,'actual_w'=>$actual,'score'=>$score];
                }
            }
            return $best;
        };

        // Financials for a given actual PV size and hardware total.
        $fin = function (float $actualPvKw, float $grandTotal) use ($sunHours, $pr, $dailyKwh, $settings) {
            $monthlyGeneration = $actualPvKw * $sunHours * 30 * $pr;
            $monthlyConsumption = $dailyKwh * 30;
            $billable = min($monthlyGeneration, $monthlyConsumption);
            $monthlySavings = $billable * max(0, (float)$settings['electricity_tariff_ngn']);
            $annual = $monthlySavings * 12;
            $payback = $annual > 0 ? $grandTotal / $annual : 0;
            $roi = $grandTotal > 0 ? (($annual * 20) / $grandTotal) * 100 : 0;
            $co2 = ($dailyKwh * 365 * max(0, (float)$settings['co2_kg_per_kwh'])) / 1000;
            return [
                'monthly_generation_kwh' => round($monthlyGeneration, 1),
                'monthly_consumption_kwh' => round($monthlyConsumption, 1),
                'monthly_savings' => round($monthlySavings),
                'annual_savings' => round($annual),
                'payback_years' => round($payback, 1),
                'roi_20_years' => round($roi, 1),
                'co2_tons_year' => round($co2, 2),
            ];
        };

        $emptyOption = function (string $key, string $label) {
            return [
                'key'=>$key,'label'=>$label,'available'=>false,'reason'=>null,
                'items'=>[],'grand_total'=>0.0,'panels_qty'=>0,'panel_wattage_w'=>0.0,
                'panel_description'=>'','power_source_label'=>'','max_pv_input_w'=>null,
                'recommended_pv_kw'=>0.0,'recommended_inverter_kw'=>0.0,'recommended_battery_kwh'=>0.0,
                'monthly_generation_kwh'=>0.0,'monthly_savings'=>0.0,'annual_savings'=>0.0,
                'payback_years'=>0.0,'roi_20_years'=>0.0,'co2_tons_year'=>0.0,'warnings'=>[],
            ];
        };

        // ============================================================
        // OPTION A — ALL-IN-ONE SOLAR GENERATOR (STRICT MODE)
        // ============================================================
        $optionA = $emptyOption('generator', 'All-in-One Solar Generator');
        $candidatesA = [];
        $capConfigured = 0;

        foreach ($generators as $g) {
            $cap = (float)($g['max_pv_input_w'] ?? 0);
            if ($cap <= 0) continue;                 // STRICT: unconfigured = not quotable
            $capConfigured++;
            if ((float)$g['continuous_kw'] < $requiredInverterKw) continue;
            if ((float)$g['usable_battery_kwh'] < $requiredBatteryKwh) continue;

            $fit = $bestPanel($cap);
            if ($fit === null) continue;             // no panel array fits inside the cap

            $totalPrice = ($fit['qty'] * (float)$fit['product']['price']) + (float)$g['price'];
            $candidatesA[] = ['generator'=>$g,'fit'=>$fit,'total_price'=>$totalPrice];
        }

        if (!empty($candidatesA)) {
            usort($candidatesA, function ($a, $b) {
                $ga = $a['generator']; $gb = $b['generator'];
                return [
                    (float)$ga['usable_battery_kwh'],
                    (float)$ga['continuous_kw'],
                    $a['total_price'],
                    (int)$ga['priority'],
                ] <=> [
                    (float)$gb['usable_battery_kwh'],
                    (float)$gb['continuous_kw'],
                    $b['total_price'],
                    (int)$gb['priority'],
                ];
            });

            $pick = $candidatesA[0];
            $g = $pick['generator'];
            $fit = $pick['fit'];
            $panel = $fit['product'];

            $items = [
                ['type'=>'panel','listing_id'=>(int)$panel['listing_id'],'description'=>(string)$panel['title'],
                 'qty'=>$fit['qty'],'unit_price'=>(float)$panel['price'],'line_total'=>$fit['qty']*(float)$panel['price']],
                ['type'=>'generator','listing_id'=>(int)$g['listing_id'],'description'=>(string)$g['title'],
                 'qty'=>1,'unit_price'=>(float)$g['price'],'line_total'=>(float)$g['price']],
            ];
            $total = $pick['total_price'];
            $actualPvKw = $fit['actual_w'] / 1000;

            $optionA = array_merge($optionA, [
                'available'=>true,
                'items'=>$items,
                'grand_total'=>round($total),
                'panels_qty'=>$fit['qty'],
                'panel_wattage_w'=>(float)$panel['panel_wattage_w'],
                'panel_description'=>(string)$panel['title'],
                'power_source_label'=>(string)$g['title'],
                'max_pv_input_w'=>(float)$g['max_pv_input_w'],
                'recommended_pv_kw'=>round($actualPvKw,2),
                'recommended_inverter_kw'=>(float)$g['continuous_kw'],
                'recommended_battery_kwh'=>(float)$g['usable_battery_kwh'],
            ], $fin($actualPvKw, $total));
        } else {
            $optionA['reason'] = ($capConfigured === 0)
                ? 'No all-in-one Solar Generator has Max Panel Input (W) configured yet.'
                : 'No all-in-one Solar Generator meets your load, backup and panel-input requirements.';
        }

        // ============================================================
        // OPTION B — CUSTOM-BUILT SYSTEM (panels + inverter + ONE battery)
        // ============================================================
        $optionB = $emptyOption('custom', 'Custom-Built System');
        $fitB = $bestPanel(null);

        $eligibleInv = array_values(array_filter($inverters, fn($p) => (float)$p['continuous_kw'] >= $requiredInverterKw));
        usort($eligibleInv, function ($a, $b) {
            return [(float)$a['continuous_kw'], (float)$a['price'], (int)$a['priority']]
                <=> [(float)$b['continuous_kw'], (float)$b['price'], (int)$b['priority']];
        });

        $eligibleBat = array_values(array_filter($batteries, fn($p) => (float)$p['usable_battery_kwh'] >= $requiredBatteryKwh));
        usort($eligibleBat, function ($a, $b) {
            return [(float)$a['usable_battery_kwh'], (float)$a['price'], (int)$a['priority']]
                <=> [(float)$b['usable_battery_kwh'], (float)$b['price'], (int)$b['priority']];
        });

        if ($fitB === null) {
            $optionB['reason'] = 'No solar panel product is available.';
        } elseif (empty($eligibleInv)) {
            $optionB['reason'] = 'No single inverter can handle your total load.';
        } elseif (empty($eligibleBat)) {
            $optionB['reason'] = 'No single battery meets your backup requirement (multiple batteries are not quoted automatically).';
        } else {
            $panel = $fitB['product'];
            $inv = $eligibleInv[0];
            $bat = $eligibleBat[0];

            $items = [
                ['type'=>'panel','listing_id'=>(int)$panel['listing_id'],'description'=>(string)$panel['title'],
                 'qty'=>$fitB['qty'],'unit_price'=>(float)$panel['price'],'line_total'=>$fitB['qty']*(float)$panel['price']],
                ['type'=>'inverter','listing_id'=>(int)$inv['listing_id'],'description'=>(string)$inv['title'],
                 'qty'=>1,'unit_price'=>(float)$inv['price'],'line_total'=>(float)$inv['price']],
                ['type'=>'battery','listing_id'=>(int)$bat['listing_id'],'description'=>(string)$bat['title'],
                 'qty'=>1,'unit_price'=>(float)$bat['price'],'line_total'=>(float)$bat['price']],
            ];
            $total = array_sum(array_column($items, 'line_total'));
            $actualPvKw = $fitB['actual_w'] / 1000;

            $optionB = array_merge($optionB, [
                'available'=>true,
                'items'=>$items,
                'grand_total'=>round($total),
                'panels_qty'=>$fitB['qty'],
                'panel_wattage_w'=>(float)$panel['panel_wattage_w'],
                'panel_description'=>(string)$panel['title'],
                'power_source_label'=>(string)$inv['title'] . ' + ' . (string)$bat['title'],
                'recommended_pv_kw'=>round($actualPvKw,2),
                'recommended_inverter_kw'=>(float)$inv['continuous_kw'],
                'recommended_battery_kwh'=>(float)$bat['usable_battery_kwh'],
            ], $fin($actualPvKw, $total));
        }

        // ============================================================
        // Result
        // ============================================================
        if (!$optionA['available'] && !$optionB['available']) {
            return [
                'success'=>false,
                'error'=>'We could not match a complete system to your requirements. '
                    . 'Generator: ' . ($optionA['reason'] ?? 'unavailable')
                    . ' Custom: ' . ($optionB['reason'] ?? 'unavailable')
                    . ' Please contact KINAS VOLT for a tailored quote.',
            ];
        }

        $primary = $optionA['available'] ? $optionA : $optionB;

        return [
            'success'=>true,
            'appliances'=>$appliances,
            'backup_hours'=>$backupHours,
            'total_load_w'=>(int)round($totalLoadW),
            'daily_kwh'=>round($dailyKwh,2),
            'design_daily_kwh'=>round($designDailyKwh,2),
            'required_pv_kw'=>round($requiredPvKw,2),
            'required_inverter_kw'=>round($requiredInverterKw,2),
            'required_battery_kwh'=>round($requiredBatteryKwh,2),
            'options'=>[
                'generator'=>$optionA,
                'custom'=>$optionB,
            ],
            // ---- legacy single-bundle keys (primary option) for old UI/PDF ----
            'recommended_pv_kw'=>$primary['recommended_pv_kw'],
            'panels_qty'=>$primary['panels_qty'],
            'panel_wattage_w'=>$primary['panel_wattage_w'],
            'panel_description'=>$primary['panel_description'],
            'recommended_inverter_kw'=>$primary['recommended_inverter_kw'],
            'recommended_battery_kwh'=>$primary['recommended_battery_kwh'],
            'power_source_label'=>$primary['power_source_label'],
            'items'=>$primary['items'],
            'grand_total'=>$primary['grand_total'],
            'monthly_generation_kwh'=>$primary['monthly_generation_kwh'],
            'monthly_consumption_kwh'=>$primary['monthly_consumption_kwh'],
            'monthly_savings'=>$primary['monthly_savings'],
            'annual_savings'=>$primary['annual_savings'],
            'payback_years'=>$primary['payback_years'],
            'roi_20_years'=>$primary['roi_20_years'],
            'co2_tons_year'=>$primary['co2_tons_year'],
            'warnings'=>[],
            'settings_used'=>$settings,
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
                    reference, full_name, phone, email, city_state, property_type, backup_hours, user_id,
                    total_load_w, daily_kwh, required_pv_kw, panels_recommended,
                    required_inverter_kw, required_battery_kwh, total_cost, monthly_savings,
                    payback_years, co2_tons_year, status, created_at
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'new', NOW())
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
                        proposal_id, item_type, listing_id, description, qty, unit_price, line_total
                    ) VALUES (?,?,?,?,?,?,?)
                ");

                foreach (($calc['options'] ?? []) as $optionKey => $option) {
                    if (empty($option['available'])) continue;
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
            }

            return $reference;
        } catch (Throwable $e) {
            error_log('kinas_solar_save_proposal error: ' . $e->getMessage());
            return null;
        }
    }
}
