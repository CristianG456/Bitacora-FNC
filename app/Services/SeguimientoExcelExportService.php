<?php

namespace App\Services;

use App\Support\LocalDate;
use Illuminate\Support\Collection;
use ZipArchive;

class SeguimientoExcelExportService
{
    public function crear(array $datos, string $exportadoPor): string
    {
        $archivo = tempnam(sys_get_temp_dir(), 'seguimiento_');
        if ($archivo === false) {
            throw new \RuntimeException('No fue posible preparar el archivo Excel.');
        }

        $zip = new ZipArchive;
        if ($zip->open($archivo, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($archivo);
            throw new \RuntimeException('No fue posible generar el archivo Excel.');
        }

        try {
            $zip->addFromString('[Content_Types].xml', $this->contentTypes());
            $zip->addFromString('_rels/.rels', $this->rootRelationships());
            $zip->addFromString('xl/workbook.xml', $this->workbook());
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
            $zip->addFromString('xl/styles.xml', $this->styles());
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->resumen($datos, $exportadoPor));
            $zip->addFromString('xl/worksheets/sheet2.xml', $this->detalle($datos['casos']));
            $zip->addFromString('xl/worksheets/sheet3.xml', $this->responsables($datos['responsables']));
        } finally {
            $zip->close();
        }

        return $archivo;
    }

    private function resumen(array $datos, string $usuario): string
    {
        $filas = [
            ['Reporte de Seguimiento y Gestión'], [],
            ['Generado el', LocalDate::inBogota(now())?->format('d/m/Y H:i')],
            ['Exportado por', $usuario], [], ['Filtros aplicados'],
        ];
        foreach ($datos['filtrosAplicados'] as $nombre => $valor) {
            $filas[] = [$nombre, $valor];
        }
        $filas[] = [];
        $filas[] = ['Resumen ejecutivo'];
        $filas[] = ['Total de casos', 'Casos activos', 'Próximos a vencer / En riesgo', 'ANS vencidos', 'Tareas pendientes'];
        $filas[] = [[
            'n' => $datos['resumen']['total'],
        ], ['n' => $datos['resumen']['activos']], ['n' => $datos['resumen']['proximos']], ['n' => $datos['resumen']['vencidos']], ['n' => $datos['resumen']['tareas_pendientes']]];

        return $this->sheet($filas, [25, 34, 30, 20, 20], ['A1:E1', 'A6:E6', 'A16:E16'], [1 => 1, 3 => 4, 4 => 4, 6 => 2, 16 => 2, 17 => 3]);
    }

    private function detalle(Collection $casos): string
    {
        $filas = [
            ['Detalle de casos'], ['Todos los casos que cumplen los filtros aplicados.'], [],
            ['Radicado', 'Asunto', 'Tipo', 'Subtipo', 'Estado', 'Fecha solicitud', 'Fecha límite', 'Estado ANS', 'Restante / retraso', 'Responsables', 'Tareas pendientes', 'Tarea pendiente más antigua', 'Responsable tarea', 'Último movimiento'],
        ];
        foreach ($casos as $caso) {
            $dias = $caso->dias_restantes_calculados;
            $asunto = filled(trim((string) $caso->descripcion)) ? trim((string) $caso->descripcion) : 'Sin asunto registrado';
            $filas[] = [$caso->radicado, $asunto, $caso->tipo?->nombre, $caso->subtipo?->nombre, $caso->estado,
                $caso->fecha_solicitud?->format('d/m/Y'), $caso->ans_fecha_limite?->format('d/m/Y'), strtoupper((string) $caso->ans_estado),
                $dias === null ? 'Sin ANS' : ($dias >= 0 ? $dias.' restantes' : abs($dias).' de retraso'), $caso->usuarios->pluck('name')->join(', '),
                ['n' => $caso->tareas_pendientes_count], $caso->tareaPendienteMasAntigua?->descripcion,
                $caso->tareaPendienteMasAntigua?->usuario?->name, $caso->ultimo_movimiento?->format('d/m/Y H:i')];
        }
        return $this->sheet($filas, [21,38,24,23,16,16,16,16,20,34,18,36,27,20], ['A1:N1', 'A2:N2'], [1 => 1, 2 => 5, 4 => 3], 4, 'A4:N'.max(4, count($filas)));
    }

    private function responsables(Collection $responsables): string
    {
        $filas = [['Seguimiento por responsables'], [], ['Responsable', 'Rol', 'Casos asociados', 'Tareas pendientes', 'Tareas completadas', 'Próximos a vencer', 'ANS vencidos']];
        foreach ($responsables as $fila) {
            $filas[] = [$fila->usuario->name, $fila->usuario->role?->nombre, ['n' => $fila->casos_asociados], ['n' => $fila->tareas_pendientes], ['n' => $fila->tareas_completadas], ['n' => $fila->casos_proximos], ['n' => $fila->casos_vencidos]];
        }
        return $this->sheet($filas, [30,18,18,20,21,20,16], ['A1:G1'], [1 => 1, 3 => 3], 3, 'A3:G'.max(3, count($filas)));
    }

    private function sheet(array $filas, array $anchos, array $merges = [], array $estilos = [], int $congelar = 0, ?string $filtro = null): string
    {
        $q = chr(39);
        $xml = '<?xml version='.$q.'1.0'.$q.' encoding='.$q.'UTF-8'.$q.'?><worksheet xmlns='.$q.'http://schemas.openxmlformats.org/spreadsheetml/2006/main'.$q.'><sheetViews><sheetView workbookViewId='.$q.'0'.$q.'>';
        if ($congelar) $xml .= '<pane ySplit='.$q.$congelar.$q.' topLeftCell='.$q.'A'.($congelar + 1).$q.' activePane='.$q.'bottomLeft'.$q.' state='.$q.'frozen'.$q.'/>';
        $xml .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight='.$q.'18'.$q.'/><cols>';
        foreach ($anchos as $i => $ancho) $xml .= '<col min='.$q.($i + 1).$q.' max='.$q.($i + 1).$q.' width='.$q.$ancho.$q.' customWidth='.$q.'1'.$q.'/>';
        $xml .= '</cols><sheetData>';
        foreach ($filas as $numero => $fila) {
            $r = $numero + 1; $xml .= '<row r='.$q.$r.$q.'>';
            foreach ($fila as $i => $celda) {
                $ref = $this->columna($i + 1).$r; $s = $estilos[$r] ?? 0; $style = $s ? ' s='.$q.$s.$q : '';
                if (is_array($celda) && array_key_exists('n', $celda)) { $xml .= '<c r='.$q.$ref.$q.$style.'><v>'.(int)$celda['n'].'</v></c>'; continue; }
                $xml .= '<c r='.$q.$ref.$q.' t='.$q.'inlineStr'.$q.$style.'><is><t xml:space='.$q.'preserve'.$q.'>'.$this->escape($celda).'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        if ($filtro) $xml .= '<autoFilter ref='.$q.$filtro.$q.'/>';
        if ($merges) { $xml .= '<mergeCells count='.$q.count($merges).$q.'>'; foreach ($merges as $merge) $xml .= '<mergeCell ref='.$q.$merge.$q.'/>'; $xml .= '</mergeCells>'; }
        return $xml.'</worksheet>';
    }

    private function columna(int $n): string { $letra = ''; while ($n) { $r = ($n - 1) % 26; $letra = chr(65 + $r).$letra; $n = intdiv($n - 1, 26); } return $letra; }
    private function escape(mixed $valor): string
    {
        $texto = (string) ($valor ?? '');
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texto) ?? '';

        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function contentTypes(): string { return <<<'XML'
<?xml version='1.0' encoding='UTF-8'?><Types xmlns='http://schemas.openxmlformats.org/package/2006/content-types'><Default Extension='rels' ContentType='application/vnd.openxmlformats-package.relationships+xml'/><Default Extension='xml' ContentType='application/xml'/><Override PartName='/xl/workbook.xml' ContentType='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml'/><Override PartName='/xl/styles.xml' ContentType='application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml'/><Override PartName='/xl/worksheets/sheet1.xml' ContentType='application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml'/><Override PartName='/xl/worksheets/sheet2.xml' ContentType='application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml'/><Override PartName='/xl/worksheets/sheet3.xml' ContentType='application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml'/></Types>
XML; }
    private function rootRelationships(): string { return <<<'XML'
<?xml version='1.0' encoding='UTF-8'?><Relationships xmlns='http://schemas.openxmlformats.org/package/2006/relationships'><Relationship Id='rId1' Type='http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument' Target='xl/workbook.xml'/></Relationships>
XML; }
    private function workbook(): string { return <<<'XML'
<?xml version='1.0' encoding='UTF-8'?><workbook xmlns='http://schemas.openxmlformats.org/spreadsheetml/2006/main' xmlns:r='http://schemas.openxmlformats.org/officeDocument/2006/relationships'><sheets><sheet name='Resumen' sheetId='1' r:id='rId1'/><sheet name='Detalle de casos' sheetId='2' r:id='rId2'/><sheet name='Responsables' sheetId='3' r:id='rId3'/></sheets></workbook>
XML; }
    private function workbookRelationships(): string { return <<<'XML'
<?xml version='1.0' encoding='UTF-8'?><Relationships xmlns='http://schemas.openxmlformats.org/package/2006/relationships'><Relationship Id='rId1' Type='http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet' Target='worksheets/sheet1.xml'/><Relationship Id='rId2' Type='http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet' Target='worksheets/sheet2.xml'/><Relationship Id='rId3' Type='http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet' Target='worksheets/sheet3.xml'/><Relationship Id='rId4' Type='http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles' Target='styles.xml'/></Relationships>
XML; }
    private function styles(): string { return <<<'XML'
<?xml version='1.0' encoding='UTF-8'?><styleSheet xmlns='http://schemas.openxmlformats.org/spreadsheetml/2006/main'><fonts count='3'><font><sz val='11'/><name val='Calibri'/></font><font><b/><sz val='16'/><color rgb='FFFFFFFF'/><name val='Calibri'/></font><font><b/><sz val='11'/><color rgb='FFFFFFFF'/><name val='Calibri'/></font></fonts><fills count='4'><fill><patternFill patternType='none'/></fill><fill><patternFill patternType='gray125'/></fill><fill><patternFill patternType='solid'><fgColor rgb='FF9F1024'/><bgColor indexed='64'/></patternFill></fill><fill><patternFill patternType='solid'><fgColor rgb='FFF3F4F6'/><bgColor indexed='64'/></patternFill></fill></fills><borders count='1'><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count='1'><xf numFmtId='0' fontId='0' fillId='0' borderId='0'/></cellStyleXfs><cellXfs count='6'><xf numFmtId='0' fontId='0' fillId='0' borderId='0' xfId='0'/><xf numFmtId='0' fontId='1' fillId='2' borderId='0' xfId='0' applyFont='1' applyFill='1'/><xf numFmtId='0' fontId='2' fillId='2' borderId='0' xfId='0' applyFont='1' applyFill='1'/><xf numFmtId='0' fontId='2' fillId='2' borderId='0' xfId='0' applyFont='1' applyFill='1'/><xf numFmtId='0' fontId='0' fillId='3' borderId='0' xfId='0' applyFill='1'/><xf numFmtId='0' fontId='0' fillId='3' borderId='0' xfId='0' applyFill='1'/></cellXfs></styleSheet>
XML; }
}
