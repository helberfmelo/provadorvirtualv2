<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MeasurementTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasurementTablesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_manage_measurement_tables_with_rows(): void
    {
        $this->seed();
        $token = $this->loginToken();
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($headers)
            ->getJson('/api/v1/measurement-templates')
            ->assertOk()
            ->assertJsonPath('data.0.source', 'standard_catalog')
            ->assertJsonPath('meta.source', 'v1_standard_models');

        $tableId = $this->withHeaders($headers)
            ->postJson('/api/v1/measurement-tables', [
                'name' => 'Calca alfaiataria regular',
                'product_type' => 'pants',
                'gender' => 'female',
                'fit_profile' => 'regular',
                'measurement_target' => 'mixed',
                'size_system' => 'br_numeric',
                'range_mode' => 'min_max',
                'source' => 'manual',
                'rows' => [
                    [
                        'size_label' => 'P',
                        'waist_min' => 66,
                        'waist_max' => 72,
                        'hip_min' => 92,
                        'hip_max' => 98,
                        'length_min' => 98,
                        'length_max' => 102,
                        'composite_measurements' => [
                            'fit_balance' => [
                                'label' => 'Cintura + quadril',
                                'formula' => 'waist+hip',
                                'min' => 158,
                                'max' => 170,
                            ],
                        ],
                    ],
                    [
                        'size_label' => 'M',
                        'waist_min' => 72,
                        'waist_max' => 78,
                        'hip_min' => 98,
                        'hip_max' => 104,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Calca alfaiataria regular')
            ->assertJsonPath('data.measurement_target', 'mixed')
            ->assertJsonPath('data.size_system', 'br_numeric')
            ->assertJsonPath('data.range_mode', 'min_max')
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.measurements.length.min', 98)
            ->assertJsonPath('data.rows.0.composite_measurements.fit_balance.formula', 'waist+hip')
            ->json('data.id');

        $this->withHeaders($headers)
            ->patchJson("/api/v1/measurement-tables/{$tableId}", [
                'name' => 'Calca alfaiataria revisada',
                'rows' => [
                    [
                        'size_label' => 'P',
                        'waist_min' => 67,
                        'waist_max' => 73,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Calca alfaiataria revisada')
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.waist_min', 67);

        $this->withHeaders($headers)
            ->getJson('/api/v1/measurement-tables')
            ->assertOk()
            ->assertJsonPath('summary.total', 5);

        $this->withHeaders($headers)
            ->deleteJson("/api/v1/measurement-tables/{$tableId}")
            ->assertOk();
    }

    public function test_merchant_can_export_preview_and_import_measurement_table_spreadsheets(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $xlsxTemplate = $this->withHeaders($headers)
            ->get('/api/v1/measurement-tables/template?format=xlsx&target=garment')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->withHeaders($headers)
            ->postJson('/api/v1/measurement-tables/import/preview', [
                'format' => 'xlsx',
                'filename' => 'modelo.xlsx',
                'content' => base64_encode($xlsxTemplate->getContent()),
            ])
            ->assertOk()
            ->assertJsonPath('failed_rows', 0)
            ->assertJsonPath('valid_rows', 3);

        $invalidCsv = implode("\n", [
            'table_name;product_type;gender;fit_profile;measurement_target;size_system;range_mode;status;table_notes;size_label;size_note;bust_min;bust_max;bust_note',
            'S133 Camisas;shirt;male;regular;body;br_alpha;min_max;active;Tabela revisada;M;Base central;100;94;Conferir busto',
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/measurement-tables/import/preview', [
                'format' => 'csv',
                'filename' => 's133-invalid.csv',
                'content' => $invalidCsv,
            ])
            ->assertOk()
            ->assertJsonPath('failed_rows', 1)
            ->assertJsonPath('rows.0.errors.0.field', 'bust_max')
            ->assertJsonPath('rows.0.errors.0.suggestion', 'Ajuste o máximo para ser maior ou igual ao mínimo.');

        $duplicateCsv = implode("\n", [
            'table_name;product_type;measurement_target;size_label;bust_min;bust_max',
            'S133 Duplicada;shirt;body;P;88;94',
            'S133 Duplicada;shirt;body;P;94;100',
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/measurement-tables/import/preview', [
                'format' => 'csv',
                'filename' => 's133-duplicada.csv',
                'content' => $duplicateCsv,
            ])
            ->assertOk()
            ->assertJsonPath('failed_rows', 1)
            ->assertJsonPath('rows.1.errors.0.field', 'size_label')
            ->assertJsonPath('rows.1.errors.0.column', 4);

        $validCsv = implode("\n", [
            'table_name;product_type;gender;fit_profile;measurement_target;size_system;range_mode;status;table_notes;size_label;size_note;bust_min;bust_max;bust_note;waist_min;waist_max;waist_note',
            'S133 Camisas;shirt;male;regular;body;br_alpha;min_max;active;Tabela revisada pela equipe;P;Entrada;88;94;Busto corpo;76;82;Cintura natural',
            'S133 Camisas;shirt;male;regular;body;br_alpha;min_max;active;Tabela revisada pela equipe;M;Central;94;100;Busto corpo;82;88;Cintura natural',
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/measurement-tables/import/preview', [
                'format' => 'csv',
                'filename' => 's133-valid.csv',
                'content' => $validCsv,
            ])
            ->assertOk()
            ->assertJsonPath('failed_rows', 0)
            ->assertJsonPath('valid_rows', 2)
            ->assertJsonPath('summary.measurement_tables', 1)
            ->assertJsonPath('summary.creates', 2);

        $this->withHeaders($headers)
            ->postJson('/api/v1/measurement-tables/import', [
                'format' => 'csv',
                'filename' => 's133-valid.csv',
                'content' => $validCsv,
            ])
            ->assertOk()
            ->assertJsonPath('imported_rows', 2)
            ->assertJsonPath('failed_rows', 0);

        $table = MeasurementTable::query()->where('name', 'S133 Camisas')->with('rows')->firstOrFail();
        $this->assertSame('Tabela revisada pela equipe', $table->notes);
        $this->assertSame('Entrada', $table->rows[0]->metadata['note']);
        $this->assertSame('Busto corpo', $table->rows[0]->metadata['measurement_notes']['bust']);

        $this->withHeaders($headers)
            ->get('/api/v1/measurement-tables?search=S133&measurement_target=body&usage=without_products')
            ->assertOk()
            ->assertJsonPath('summary.filtered', 1)
            ->assertJsonPath('data.0.name', 'S133 Camisas');

        $export = $this->withHeaders($headers)
            ->get('/api/v1/measurement-tables/export?format=csv&search=S133')
            ->assertOk();
        $this->assertStringContainsString('S133 Camisas', $export->getContent());
        $this->assertStringContainsString('Busto corpo', $export->getContent());

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'measurement_table.imported',
        ]);
        $this->assertSame(1, AuditLog::query()->where('event', 'measurement_table.imported')->count());
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
