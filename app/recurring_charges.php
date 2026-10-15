<?php
declare(strict_types=1);

/**
 * Genera los cargos recurrentes que correspondan hasta el mes actual.
 * Las plantillas de inquilino sustituyen a las de su unidad cuando coinciden
 * en tipo y concepto. Nunca modifica ni elimina cargos ya existentes.
 */
function sync_recurring_charges(PDO $pdo): int {
    $templates = $pdo->query("SELECT * FROM cargos_recurrentes WHERE activo=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $byUnit = [];
    $byTenant = [];
    foreach ($templates as $template) {
        if (!empty($template['unidad_id'])) $byUnit[(int)$template['unidad_id']][] = $template;
        if (!empty($template['inquilino_id'])) $byTenant[(int)$template['inquilino_id']][] = $template;
    }

    $tenants = $pdo->query("SELECT id, unidad_id, fecha_entrada, fecha_salida, alquiler_mensual FROM inquilinos WHERE fecha_entrada IS NOT NULL AND (fecha_salida IS NULL OR fecha_salida >= fecha_entrada)")->fetchAll(PDO::FETCH_ASSOC);
    $currentMonth = new DateTimeImmutable(date('Y-m-01'));
    $exists = $pdo->prepare("SELECT id FROM cargos WHERE inquilino_id=? AND tipo=? AND mes=? AND (LOWER(TRIM(concepto))=LOWER(TRIM(?)) OR (recurrente_id=? AND periodo_recurrente=?)) LIMIT 1");
    $existsRent = $pdo->prepare("SELECT id FROM cargos WHERE inquilino_id=? AND tipo='Alquiler' AND mes=? LIMIT 1");
    $rentConfiguredQ = $pdo->prepare("SELECT COUNT(*) FROM cargos_recurrentes WHERE tipo='Alquiler' AND (unidad_id=? OR inquilino_id=?)");
    $excluded = $pdo->prepare('SELECT id FROM cargos_recurrentes_exclusiones WHERE inquilino_id=? AND recurrente_id=? AND periodo_recurrente=? LIMIT 1');
    $insert = $pdo->prepare("INSERT IGNORE INTO cargos(fecha,tipo,concepto,inquilino_id,importe,mes,pagado_por,recurrente_id,periodo_recurrente) VALUES(?,?,?,?,?,?,?,?,?)");
    $created = 0;

    foreach ($tenants as $tenant) {
        $tenantId = (int)$tenant['id'];
        $unitId = (int)($tenant['unidad_id'] ?? 0);
        $tenantTemplates = $byTenant[$tenantId] ?? [];
        $unitTemplates = $byUnit[$unitId] ?? [];

        // Una plantilla del inquilino sustituye la plantilla de unidad equivalente.
        $tenantKeys = [];
        foreach ($tenantTemplates as $template) {
            $tenantKeys[strtoupper($template['tipo']).'|'.strtolower(trim($template['concepto']))] = true;
        }
        $effective = $tenantTemplates;
        foreach ($unitTemplates as $template) {
            $key = strtoupper($template['tipo']).'|'.strtolower(trim($template['concepto']));
            if (!isset($tenantKeys[$key])) $effective[] = $template;
        }

        // Compatibilidad: si no se ha configurado una plantilla de alquiler,
        // el campo de alquiler mensual del inquilino sigue generando el alquiler.
        $rentConfiguredQ->execute([$unitId, $tenantId]);
        $hasRentConfiguration = (int)$rentConfiguredQ->fetchColumn() > 0;
        if (!$hasRentConfiguration && (float)$tenant['alquiler_mensual'] > 0) {
            $effective[] = [
                'id' => 0, 'tipo' => 'Alquiler', 'concepto' => 'Alquiler mensual',
                'importe' => (float)$tenant['alquiler_mensual'], 'periodicidad' => 'MENSUAL',
                'fecha_inicio' => $tenant['fecha_entrada'], 'fecha_fin' => null,
                'pagado_por' => 'Inquilino'
            ];
        }

        $tenantStart = new DateTimeImmutable(substr($tenant['fecha_entrada'], 0, 7).'-01');
        $limit = $currentMonth;
        if (!empty($tenant['fecha_salida'])) {
            $exitMonth = new DateTimeImmutable(substr($tenant['fecha_salida'], 0, 7).'-01');
            if ($exitMonth < $limit) $limit = $exitMonth;
        }

        foreach ($effective as $template) {
            $templateStart = new DateTimeImmutable(substr($template['fecha_inicio'], 0, 7).'-01');
            $anchor = $templateStart > $tenantStart ? $templateStart : $tenantStart;
            $templateEnd = $limit;
            if (!empty($template['fecha_fin'])) {
                $endMonth = new DateTimeImmutable(substr($template['fecha_fin'], 0, 7).'-01');
                if ($endMonth < $templateEnd) $templateEnd = $endMonth;
            }
            if ($anchor > $templateEnd) continue;

            $step = match ($template['periodicidad']) {
                'TRIMESTRAL' => 3,
                'ANUAL' => 12,
                default => 1
            };
            $anchorIndex = ((int)$templateStart->format('Y')) * 12 + (int)$templateStart->format('n') - 1;
            $cursorIndex = ((int)$anchor->format('Y')) * 12 + (int)$anchor->format('n') - 1;
            $offset = max(0, $cursorIndex - $anchorIndex);
            $remainder = $offset % $step;
            if ($remainder !== 0) $anchor = $anchor->modify('+'.($step - $remainder).' months');

            for ($month = $anchor; $month <= $templateEnd; $month = $month->modify('+'.$step.' months')) {
                $monthKey = $month->format('Y-m');
                $excluded->execute([$tenantId, (int)$template['id'], $monthKey]);
                if ($excluded->fetchColumn()) continue;
                // Un alquiler ya registrado para ese mes prevalece sobre una nueva plantilla.
                if ($template['tipo'] === 'Alquiler') {
                    $existsRent->execute([$tenantId, $monthKey]);
                    if ($existsRent->fetchColumn()) continue;
                } else {
                    $exists->execute([$tenantId, $template['tipo'], $monthKey, $template['concepto'], (int)$template['id'], $monthKey]);
                    if ($exists->fetchColumn()) continue;
                }

                // El concepto identifica claramente el mes al que pertenece el cargo.
                $monthNames = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
                $monthLabel = $monthNames[(int)$month->format('n') - 1].' '.$month->format('Y');
                $baseConcept = $template['id'] === 0 ? 'Alquiler mensual' : trim((string)$template['concepto']);
                $concept = $baseConcept.' '.$monthLabel;
                $insert->execute([
                    $month->format('Y-m-01'), $template['tipo'], $concept, $tenantId,
                    (float)$template['importe'], $monthKey, $template['pagado_por'],
                    (int)$template['id'], $monthKey
                ]);
                $created += $insert->rowCount();
            }
        }
    }
    return $created;
}
