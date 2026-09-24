<?php

namespace App\Services;

use App\Models\DiaNoHabil;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class CalendarioLaboralService
{
    private const TIMEZONE = 'America/Bogota';

    /** @var array<int, array<string, true>> */
    private array $fechasNoHabilesPorAnio = [];

    /** @var array<int, array<string, true>> */
    private array $festivosColombiaPorAnio = [];

    public function esFinDeSemana(CarbonInterface $fecha): bool
    {
        return $this->normalizar($fecha)->isWeekend();
    }

    public function esFestivoColombia(CarbonInterface $fecha): bool
    {
        $dia = $this->normalizar($fecha);

        return isset($this->festivosColombia($dia->year)[$dia->toDateString()]);
    }

    public function esDiaNoHabilConfigurado(CarbonInterface $fecha): bool
    {
        $dia = $this->normalizar($fecha);

        return isset($this->fechasNoHabiles($dia->year)[$dia->toDateString()]);
    }

    public function esDiaHabil(CarbonInterface $fecha): bool
    {
        return ! $this->esFinDeSemana($fecha)
            && ! $this->esFestivoColombia($fecha)
            && ! $this->esDiaNoHabilConfigurado($fecha);
    }

    public function sumarDiasHabiles(CarbonInterface $fecha, int $dias): CarbonImmutable
    {
        if ($dias < 0) {
            throw new InvalidArgumentException('Los días hábiles a sumar no pueden ser negativos.');
        }

        $resultado = $this->normalizar($fecha);
        $contados = 0;

        while ($contados < $dias) {
            $resultado = $resultado->addDay();

            if ($this->esDiaHabil($resultado)) {
                $contados++;
            }
        }

        return $resultado;
    }

    public function diferenciaDiasHabiles(
        CarbonInterface $desde,
        CarbonInterface $hasta,
    ): int {
        $inicio = $this->normalizar($desde);
        $fin = $this->normalizar($hasta);

        if ($inicio->equalTo($fin)) {
            return 0;
        }

        if ($inicio->greaterThan($fin)) {
            return -$this->diferenciaDiasHabiles($fin, $inicio);
        }

        $dias = 0;
        for ($cursor = $inicio->addDay(); $cursor->lessThanOrEqualTo($fin); $cursor = $cursor->addDay()) {
            if ($this->esDiaHabil($cursor)) {
                $dias++;
            }
        }

        return $dias;
    }

    private function normalizar(CarbonInterface $fecha): CarbonImmutable
    {
        return CarbonImmutable::instance($fecha)
            ->setTimezone(self::TIMEZONE)
            ->startOfDay();
    }

    /**
     * Festivos nacionales definidos por el artículo 1 de la Ley 51 de 1983.
     *
     * Fuente oficial: Departamento Administrativo de la Función Pública.
     * https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=4954
     *
     * Las fechas religiosas variables se derivan de Pascua mediante el
     * cómputo gregoriano y las celebraciones indicadas por la ley se trasladan
     * al lunes siguiente cuando no coinciden con lunes.
     *
     * @return array<string, true>
     */
    private function festivosColombia(int $anio): array
    {
        if (array_key_exists($anio, $this->festivosColombiaPorAnio)) {
            return $this->festivosColombiaPorAnio[$anio];
        }

        $festivos = [
            $this->fecha($anio, 1, 1),
            $this->fecha($anio, 5, 1),
            $this->fecha($anio, 7, 20),
            $this->fecha($anio, 8, 7),
            $this->fecha($anio, 12, 8),
            $this->fecha($anio, 12, 25),
        ];

        foreach ([[1, 6], [3, 19], [6, 29], [8, 15], [10, 12], [11, 1], [11, 11]] as [$mes, $dia]) {
            $festivos[] = $this->trasladarAlLunes($this->fecha($anio, $mes, $dia));
        }

        $pascua = $this->domingoPascua($anio);
        $festivos[] = $pascua->subDays(3); // Jueves Santo.
        $festivos[] = $pascua->subDays(2); // Viernes Santo.
        $festivos[] = $this->trasladarAlLunes($pascua->addDays(39)); // Ascensión.
        $festivos[] = $this->trasladarAlLunes($pascua->addDays(60)); // Corpus Christi.
        $festivos[] = $this->trasladarAlLunes($pascua->addDays(68)); // Sagrado Corazón.

        return $this->festivosColombiaPorAnio[$anio] = collect($festivos)
            ->mapWithKeys(fn (CarbonImmutable $festivo) => [$festivo->toDateString() => true])
            ->all();
    }

    private function trasladarAlLunes(CarbonImmutable $fecha): CarbonImmutable
    {
        return $fecha->isMonday()
            ? $fecha
            : $fecha->next(CarbonInterface::MONDAY)->startOfDay();
    }

    /**
     * Cómputo gregoriano de Meeus/Jones/Butcher para el Domingo de Pascua.
     */
    private function domingoPascua(int $anio): CarbonImmutable
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return $this->fecha($anio, $mes, $dia);
    }

    private function fecha(int $anio, int $mes, int $dia): CarbonImmutable
    {
        return CarbonImmutable::create($anio, $mes, $dia, 0, 0, 0, self::TIMEZONE);
    }

    /** @return array<string, true> */
    private function fechasNoHabiles(int $anio): array
    {
        if (! array_key_exists($anio, $this->fechasNoHabilesPorAnio)) {
            $this->fechasNoHabilesPorAnio[$anio] = DiaNoHabil::query()
                ->where('activo', true)
                ->whereBetween('fecha', ["{$anio}-01-01", "{$anio}-12-31"])
                ->pluck('fecha')
                ->mapWithKeys(fn ($fecha) => [
                    CarbonImmutable::parse($fecha, self::TIMEZONE)->toDateString() => true,
                ])
                ->all();
        }

        return $this->fechasNoHabilesPorAnio[$anio];
    }
}
