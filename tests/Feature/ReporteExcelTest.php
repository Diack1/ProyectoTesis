<?php

namespace Tests\Feature;

use App\Models\Espacio;
use App\Models\RegistroOcupacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteExcelTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_preserves_filters_types_and_safe_text_with_formatted_columns(): void
    {
        $space = Espacio::create(['codigo' => 'E01', 'activo' => true, 'estado_actual' => 'libre']);
        foreach (['libre', 'ocupado'] as $state) {
            RegistroOcupacion::create(['espacio_id' => $space->id, 'estado_detectado' => $state,
                'origen' => '=1+1', 'distancia_cm' => 0, 'fecha_hora' => '2026-10-09 12:50:11']);
        }
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $response = $this->get(route('admin.reportes.exportarExcel', ['estado' => 'libre', 'fecha_inicio' => '2026-10-09', 'fecha_fin' => '2026-10-09', 'espacio_id' => $space->id]))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'test-report-');
        try {
            file_put_contents($path, $response->streamedContent());
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $styles = $zip->getFromName('xl/styles.xml');
            $zip->close();
            $this->assertStringContainsString('autoFilter ref="A6:H7"', $xml);
            $this->assertStringContainsString('state="frozen"', $xml);
            $this->assertStringContainsString('E01', $xml);
            $this->assertStringContainsString('Libre', $xml);
            $this->assertStringNotContainsString('Ocupado', $xml);
            $this->assertStringContainsString('=1+1', $xml);
            $this->assertStringNotContainsString('<f>', $xml);
            $this->assertStringContainsString('dd/mm/yyyy hh:mm:ss', $styles);
            $this->assertStringContainsString('1768AC', $styles);
            $sheet = simplexml_load_string($xml);
            $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertCount(1, $sheet->xpath('//s:row[@r="7"]/s:c[@r="B7"]/s:v'));
            $this->assertSame('0', (string) $sheet->xpath('//s:c[@r="F7"]/s:v')[0]);
        } finally {
            @unlink($path);
        }
    }

    public function test_empty_export_is_valid_and_csv_declares_excel_separator(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $response = $this->get(route('admin.reportes.exportarExcel'))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'test-report-');
        try {
            file_put_contents($path, $response->streamedContent());
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($path));
            $this->assertStringContainsString('No hay registros', $zip->getFromName('xl/worksheets/sheet1.xml'));
            $zip->close();
        } finally {
            @unlink($path);
        }
        $csv = $this->get(route('admin.reportes.exportarCsv'))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFsep=;\r\n", $csv);
        $this->getJson(route('admin.reportes.exportarExcel', ['fecha_inicio' => 'invalid']))->assertUnprocessable();
    }

    public function test_report_download_remains_owner_only(): void
    {
        foreach (['user', 'admin', 'operador'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.reportes.exportarExcel'))->assertForbidden();
        }
    }
}
