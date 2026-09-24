<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\DiaNoHabil;
use App\Models\Notificacion;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\SubtipoProceso;
use App\Models\TipoProceso;
use App\Models\User;
use App\Services\AnsService;
use App\Services\CalendarioLaboralService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AnsBusinessCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        foreach (['Administrador', 'Juridica', 'Consultor', 'Usuario'] as $nombre) {
            Rol::create(['nombre' => $nombre]);
        }
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_saturday_is_not_a_business_day(): void
    {
        $this->assertFalse($this->calendar()->esDiaHabil($this->date('2026-04-04')));
    }

    public function test_sunday_is_not_a_business_day(): void
    {
        $this->assertFalse($this->calendar()->esDiaHabil($this->date('2026-04-05')));
    }

    public function test_configured_holiday_is_not_a_business_day(): void
    {
        $this->nonBusinessDay('2026-04-08', 'Festivo de prueba', DiaNoHabil::TIPO_FESTIVO);

        $this->assertFalse($this->calendar()->esDiaHabil($this->date('2026-04-08')));
    }

    public function test_configured_institutional_closure_is_not_a_business_day(): void
    {
        $this->nonBusinessDay('2026-04-09', 'Cierre institucional', DiaNoHabil::TIPO_INSTITUCIONAL);

        $this->assertFalse($this->calendar()->esDiaHabil($this->date('2026-04-09')));
    }

    public function test_normal_weekday_is_a_business_day(): void
    {
        $this->assertTrue($this->calendar()->esDiaHabil($this->date('2026-04-06')));
    }

    public function test_fixed_colombian_holiday_is_detected_automatically(): void
    {
        $calendar = $this->calendar();

        $this->assertTrue($calendar->esFestivoColombia($this->date('2026-05-01')));
        $this->assertFalse($calendar->esDiaHabil($this->date('2026-05-01')));
    }

    public function test_emiliani_holiday_is_moved_to_the_following_monday(): void
    {
        $calendar = $this->calendar();

        $this->assertFalse($calendar->esFestivoColombia($this->date('2027-01-06')));
        $this->assertTrue($calendar->esFestivoColombia($this->date('2027-01-11')));
        $this->assertFalse($calendar->esDiaHabil($this->date('2027-01-11')));
    }

    public function test_holy_thursday_and_good_friday_are_detected_from_easter(): void
    {
        $calendar = $this->calendar();

        $this->assertTrue($calendar->esFestivoColombia($this->date('2026-04-02')));
        $this->assertTrue($calendar->esFestivoColombia($this->date('2026-04-03')));
        $this->assertFalse($calendar->esDiaHabil($this->date('2026-04-02')));
        $this->assertFalse($calendar->esDiaHabil($this->date('2026-04-03')));
    }

    public function test_all_national_holidays_for_2026_match_the_official_calendar(): void
    {
        $calendar = $this->calendar();
        $festivos = [
            '2026-01-01', '2026-01-12', '2026-03-23', '2026-04-02',
            '2026-04-03', '2026-05-01', '2026-05-18', '2026-06-08',
            '2026-06-15', '2026-06-29', '2026-07-20', '2026-08-07',
            '2026-08-17', '2026-10-12', '2026-11-02', '2026-11-16',
            '2026-12-08', '2026-12-25',
        ];

        foreach ($festivos as $festivo) {
            $this->assertTrue(
                $calendar->esFestivoColombia($this->date($festivo)),
                "No se reconoció el festivo oficial {$festivo}.",
            );
        }
    }

    public function test_calendar_days_include_holidays_and_weekends(): void
    {
        $this->nonBusinessDay('2026-04-03', 'Festivo entre semana', DiaNoHabil::TIPO_FESTIVO);
        $tipo = $this->type('Calendario', 'CAL', 3, 'calendario');

        $snapshot = app(AnsService::class)->snapshot($tipo, $this->date('2026-04-02'));

        $this->assertSame('2026-04-05', $snapshot['ans_fecha_limite']);
    }

    public function test_existing_case_keeps_snapshot_and_new_case_uses_labor_calendar(): void
    {
        $juridica = $this->user('Juridica', 'Juridica calendario');
        $tipo = $this->type('Hábiles', 'HAB', 2, 'habiles');
        $subtipo = $this->subtype($tipo);
        $snapshotAnterior = app(AnsService::class)->snapshot($tipo, $this->date('2026-04-06'));
        $casoHistorico = $this->case($juridica, $tipo, $subtipo, $snapshotAnterior);

        $this->nonBusinessDay('2026-04-07', 'Cierre posterior', DiaNoHabil::TIPO_INSTITUCIONAL);
        $snapshotNuevo = app(AnsService::class)->snapshot($tipo, $this->date('2026-04-06'));

        $this->assertSame('2026-04-08', $casoHistorico->refresh()->ans_fecha_limite->toDateString());
        $this->assertSame('2026-04-09', $snapshotNuevo['ans_fecha_limite']);
    }

    public function test_inactive_institutional_day_behaves_like_a_normal_weekday(): void
    {
        DiaNoHabil::create([
            'fecha' => '2026-04-09',
            'nombre' => 'Cierre desactivado',
            'tipo' => DiaNoHabil::TIPO_INSTITUCIONAL,
            'activo' => false,
        ]);

        $calendar = $this->calendar();
        $this->assertFalse($calendar->esDiaNoHabilConfigurado($this->date('2026-04-09')));
        $this->assertTrue($calendar->esDiaHabil($this->date('2026-04-09')));
    }

    public function test_three_business_days_skip_an_automatic_midweek_holiday(): void
    {
        $tipo = $this->type('Ejemplo oficial', 'EOF', 3, 'habiles');

        $snapshot = app(AnsService::class)->snapshot($tipo, $this->date('2027-12-05'));

        $this->assertTrue($this->calendar()->esFestivoColombia($this->date('2027-12-08')));
        $this->assertSame('2027-12-09', $snapshot['ans_fecha_limite']);
    }

    public function test_new_case_skips_weekend_and_configured_holiday(): void
    {
        CarbonImmutable::setTestNow($this->date('2026-04-03')->setTime(10, 0));
        $juridica = $this->user('Juridica', 'Juridica caso nuevo');
        $tipo = $this->type('Laboral', 'LAB', 3, 'habiles');
        $subtipo = $this->subtype($tipo);
        $this->nonBusinessDay('2026-04-08', 'Festivo intermedio', DiaNoHabil::TIPO_FESTIVO);

        $this->actingAs($juridica)->post(route('casos.guardar'), [
            'tipo_proceso_id' => $tipo->id,
            'subtipo_proceso_id' => $subtipo->id,
            'descripcion' => 'Caso con calendario laboral',
            'tipo_solicitante' => 'persona',
            'nombre_solicitante' => 'Solicitante laboral',
            'documento_solicitante' => null,
            'fecha_solicitud' => '2026-04-03',
        ])->assertRedirect();

        $caso = Caso::where('tipo_id', $tipo->id)->latest('id')->firstOrFail();
        $this->assertSame('2026-04-09', $caso->ans_fecha_limite->toDateString());
    }

    public function test_preventive_alert_uses_real_business_days_and_is_idempotent(): void
    {
        $juridica = $this->user('Juridica', 'Juridica alerta');
        $asignado = $this->user('Usuario', 'Asignado alerta');
        $tipo = $this->type('Alertas hábiles', 'ALH', 4, 'habiles');
        $subtipo = $this->subtype($tipo);
        $this->nonBusinessDay('2026-04-08', 'Festivo alerta', DiaNoHabil::TIPO_FESTIVO);
        $caso = $this->case($juridica, $tipo, $subtipo, [
            'ans_fecha_inicio' => '2026-04-03',
            'ans_dias' => 4,
            'ans_tipo_dias' => 'habiles',
            'ans_fecha_limite' => '2026-04-10',
            'ans_estado' => 'vigente',
        ], $asignado);

        $service = app(AnsService::class);
        $this->assertSame(4, $service->diasRestantes($caso, $this->date('2026-04-03')));
        $this->assertSame(1, $service->procesarAlertas($this->date('2026-04-03')));
        $this->assertSame('preventivo', $caso->refresh()->ans_estado);
        $this->assertSame(1, Notificacion::where('user_id', $asignado->id)->where('tipo', 'ans')->count());

        $bitacora = Bitacora::where('caso_id', $caso->id)
            ->where('accion', 'Alerta preventiva')
            ->firstOrFail();
        $this->assertSame(4, $bitacora->metadata['dias_restantes']);

        $notificaciones = Notificacion::count();
        $bitacoras = Bitacora::count();
        $this->assertSame(0, $service->procesarAlertas($this->date('2026-04-03')));
        $this->assertSame($notificaciones, Notificacion::count());
        $this->assertSame($bitacoras, Bitacora::count());
    }

    public function test_critical_alert_uses_the_colombian_business_calendar(): void
    {
        $juridica = $this->user('Juridica', 'Juridica crítica');
        $asignado = $this->user('Usuario', 'Asignado crítica');
        $tipo = $this->type('Crítica hábil', 'CRH', 1, 'habiles');
        $subtipo = $this->subtype($tipo);
        $caso = $this->case($juridica, $tipo, $subtipo, [
            'ans_fecha_inicio' => '2026-04-02',
            'ans_dias' => 1,
            'ans_tipo_dias' => 'habiles',
            'ans_fecha_limite' => '2026-04-06',
            'ans_estado' => 'vigente',
        ], $asignado);

        $service = app(AnsService::class);
        $this->assertSame(1, $service->diasRestantes($caso, $this->date('2026-04-02')));
        $this->assertSame(1, $service->procesarAlertas($this->date('2026-04-02')));
        $this->assertSame('critico', $caso->refresh()->ans_estado);
        $this->assertDatabaseHas('bitacoras', [
            'caso_id' => $caso->id,
            'accion' => 'Alerta crítica',
        ]);
    }

    public function test_delay_uses_the_same_business_calendar(): void
    {
        $juridica = $this->user('Juridica', 'Juridica retraso');
        $tipo = $this->type('Retraso hábil', 'RTH', 1, 'habiles');
        $subtipo = $this->subtype($tipo);
        $this->nonBusinessDay('2026-04-07', 'Cierre en retraso', DiaNoHabil::TIPO_INSTITUCIONAL);
        $caso = $this->case($juridica, $tipo, $subtipo, [
            'ans_fecha_inicio' => '2026-04-02',
            'ans_dias' => 1,
            'ans_tipo_dias' => 'habiles',
            'ans_fecha_limite' => '2026-04-03',
            'ans_estado' => 'vigente',
        ]);

        $service = app(AnsService::class);
        $this->assertSame(-2, $service->diasRestantes($caso, $this->date('2026-04-08')));
        $this->assertSame(2, $service->procesarAlertas($this->date('2026-04-08')));

        $incumplimiento = Bitacora::where('caso_id', $caso->id)
            ->where('accion', 'Incumplimiento')
            ->firstOrFail();
        $this->assertSame(-2, $incumplimiento->metadata['dias_restantes']);
        $this->assertSame('vencido', $caso->refresh()->ans_estado);
    }

    public function test_closing_uses_the_same_business_calendar_for_compliance(): void
    {
        $juridica = $this->user('Juridica', 'Juridica cierre');
        $tipo = $this->type('Cierre hábil', 'CIH', 1, 'habiles');
        $subtipo = $this->subtype($tipo);
        $snapshot = [
            'ans_fecha_inicio' => '2026-04-09',
            'ans_dias' => 1,
            'ans_tipo_dias' => 'habiles',
            'ans_fecha_limite' => '2026-04-10',
            'ans_estado' => 'vigente',
        ];
        $cumplido = $this->case($juridica, $tipo, $subtipo, $snapshot);
        $incumplido = $this->case($juridica, $tipo, $subtipo, $snapshot);

        $service = app(AnsService::class);
        $service->cerrarSeguimiento($cumplido, $this->date('2026-04-12'));
        $service->cerrarSeguimiento($incumplido, $this->date('2026-04-13'));

        $this->assertSame('cumplido', $cumplido->refresh()->ans_estado);
        $this->assertSame('incumplido', $incumplido->refresh()->ans_estado);
    }

    public function test_non_business_date_cannot_be_duplicated(): void
    {
        $this->nonBusinessDay('2026-12-25', 'Navidad', DiaNoHabil::TIPO_FESTIVO);

        $this->expectException(QueryException::class);
        $this->nonBusinessDay('2026-12-25', 'Cierre duplicado', DiaNoHabil::TIPO_INSTITUCIONAL);
    }

    private function calendar(): CalendarioLaboralService
    {
        return app(CalendarioLaboralService::class);
    }

    private function date(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, 'America/Bogota');
    }

    private function nonBusinessDay(string $fecha, string $nombre, string $tipo): DiaNoHabil
    {
        return DiaNoHabil::create(compact('fecha', 'nombre', 'tipo'));
    }

    private function user(string $rol, string $nombre): User
    {
        return User::factory()->create([
            'name' => $nombre,
            'rol_id' => Rol::where('nombre', $rol)->value('id'),
            'activo' => true,
            'force_password_change' => false,
        ]);
    }

    private function type(string $nombre, string $codigo, int $dias, string $modalidad): TipoProceso
    {
        return TipoProceso::create([
            'nombre' => $nombre,
            'codigo' => $codigo,
            'activo' => true,
            'ans_dias' => $dias,
            'ans_tipo_dias' => $modalidad,
        ]);
    }

    private function subtype(TipoProceso $tipo): SubtipoProceso
    {
        return SubtipoProceso::create([
            'tipo_id' => $tipo->id,
            'nombre' => 'General',
            'codigo' => 'GEN',
            'activo' => true,
        ]);
    }

    private function case(
        User $creador,
        TipoProceso $tipo,
        SubtipoProceso $subtipo,
        array $snapshot,
        ?User $asignado = null,
    ): Caso {
        $solicitante = Solicitante::create([
            'nombre' => 'Solicitante calendario',
            'documento' => null,
            'tipo_solicitante' => 'persona',
        ]);
        $caso = Caso::create(array_merge([
            'radicado' => $tipo->codigo.'-GEN-'.uniqid(),
            'tipo_id' => $tipo->id,
            'subtipo_id' => $subtipo->id,
            'descripcion' => 'Caso calendario laboral',
            'solicitante_id' => $solicitante->id,
            'fecha_solicitud' => $snapshot['ans_fecha_inicio'],
            'estado' => 'Pendiente',
            'fecha_inicio' => $snapshot['ans_fecha_inicio'],
            'created_by' => $creador->id,
        ], $snapshot));

        if ($asignado) {
            $caso->usuarios()->attach($asignado->id, [
                'estado' => 'Pendiente',
                'activo' => true,
                'fecha_asignacion' => now(),
            ]);
        }

        return $caso;
    }
}
