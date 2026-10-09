<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class ReporteExcelService
{
    public function guardar(string $path, Builder $query, string $filtros): void
    {
        $options = new Options();
        $options->DEFAULT_ROW_STYLE = (new Style())->setFontName('Calibri')->setFontSize(11)->setFontColor('20344A')->setShouldWrapText();
        foreach ([10, 24, 16, 20, 20, 18, 30, 24] as $i => $width) {
            $options->setColumnWidth($width, $i + 1);
        }
        foreach ([1, 2, 3, 4] as $row) {
            $options->mergeCells(0, $row, 7, $row);
        }
        $writer = new Writer($options);
        $writer->openToFile($path);
        try {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Ocupación');
            $sheet->setSheetView((new SheetView())->setFreezeRow(7)->setShowGridLines(false));
            $writer->addRow($this->fila(['Parke’o | Reporte de ocupación'], (new Style())->setFontSize(20)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('10283F'), 38));
            $writer->addRow($this->fila(['Generado: '.now()->format('d/m/Y H:i').' · Zona horaria: '.config('app.timezone')], (new Style())->setFontColor('52677D'), 25));
            $writer->addRow($this->fila([$filtros], (new Style())->setBackgroundColor('EAF2FB')->setShouldWrapText(), 38));
            $writer->addRow($this->fila(['Historial de cambios de estado; no representa la ocupación actual.'], (new Style())->setFontColor('52677D'), 25));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow($this->fila(['ID', 'Fecha y hora', 'Espacio', 'Sensor', 'Estado detectado', 'Distancia (cm)', 'Origen', 'Fecha de registro'], (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('1768AC')->setShouldWrapText(), 30));
            $date = (new Style())->setFormat('dd/mm/yyyy hh:mm:ss');
            $number = (new Style())->setFormat('0.00');
            $rowNumber = 6;
            foreach ($query->lazy(500) as $registro) {
                ++$rowNumber;
                $state = (new Style())->setFontBold()->setFontColor($registro->estado_detectado === 'libre' ? '116149' : '9C3444')
                    ->setBackgroundColor($registro->estado_detectado === 'libre' ? 'DFF3EA' : 'FBE8EB');
                $values = [$registro->id, $registro->fecha_hora, $registro->espacio?->codigo ?? '—',
                    $registro->sensor?->codigo_sensor ?? '—', ucfirst($registro->estado_detectado),
                    $registro->distancia_cm === null ? null : (float) $registro->distancia_cm,
                    $registro->origen, $registro->created_at];
                $cells = [];
                foreach ($values as $i => $value) {
                    $style = match ($i) {1, 7 => $date, 4 => $state, 5 => $number, default => null};
                    // Text from records must never become an Excel formula.
                    $cells[] = is_string($value) ? new StringCell($value, $style) : Cell::fromValue($value, $style);
                }
                $writer->addRow((new Row($cells, (new Style())->setBackgroundColor($rowNumber % 2 ? 'F0F5FA' : 'FFFFFF')))->setHeight(30));
            }
            if ($rowNumber > 6) {
                $sheet->setAutoFilter(new AutoFilter(0, 6, 7, $rowNumber));
            } else {
                $options->mergeCells(0, 7, 7, 7);
                $writer->addRow($this->fila(['No hay registros para los filtros seleccionados.'], (new Style())->setFontColor('52677D'), 30));
            }
        } finally {
            $writer->close();
        }
    }

    private function fila(array $values, Style $style, float $height): Row
    {
        return (new Row(array_map(fn ($value) => new StringCell($value, null), array_pad($values, 8, '')), $style))->setHeight($height);
    }
}
