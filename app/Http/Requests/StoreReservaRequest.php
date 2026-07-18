<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'user';
    }

    public function rules(): array
    {
        $hoy = now('America/Lima')->format('Y-m-d');
        $manana = now('America/Lima')->addDay()->format('Y-m-d');

        return [
            'vehiculo_tipo_id' => [
                'required',
                'integer',
                Rule::exists('vehiculo_tipos', 'id')->where('activo', true),
            ],
            'fecha_reserva' => ['required', 'date_format:Y-m-d', 'after_or_equal:' . $hoy, 'before_or_equal:' . $manana],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'duracion_minutos' => ['required', 'integer', 'in:60,120,180,240'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehiculo_tipo_id.required' => 'Seleccione un tipo de vehiculo.',
            'vehiculo_tipo_id.exists' => 'El tipo de vehiculo seleccionado no esta disponible.',
            'fecha_reserva.required' => 'Selecciona la fecha de reserva.',
            'fecha_reserva.date_format' => 'La fecha seleccionada no es valida.',
            'fecha_reserva.after_or_equal' => 'La fecha seleccionada no es valida.',
            'fecha_reserva.before_or_equal' => 'Solo puedes reservar para hoy o como maximo manana.',
            'hora_inicio.required' => 'Selecciona la hora de ingreso.',
            'hora_inicio.date_format' => 'La hora de ingreso debe tener un formato valido.',
            'duracion_minutos.required' => 'Selecciona la duracion de la reserva.',
            'duracion_minutos.integer' => 'La duracion seleccionada no es valida.',
            'duracion_minutos.in' => 'La duracion seleccionada no es valida.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $inicio = $this->fechaHoraInicio();
                $fin = $this->fechaHoraFin();
                $minimoPermitido = now('America/Lima')->addMinutes(15)->startOfMinute();

                if ($inicio->lt($minimoPermitido)) {
                    $validator->errors()->add(
                        'hora_inicio',
                        'La reserva debe realizarse con al menos 15 minutos de anticipacion.'
                    );
                }

                if ($fin->lessThanOrEqualTo($inicio)) {
                    $validator->errors()->add(
                        'duracion_minutos',
                        'La fecha final de la reserva no es valida.'
                    );
                }
            },
        ];
    }

    public function fechaHoraInicio(): Carbon
    {
        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $this->input('fecha_reserva') . ' ' . $this->input('hora_inicio'),
            'America/Lima'
        );
    }

    public function fechaHoraFin(): Carbon
    {
        return $this->fechaHoraInicio()->copy()->addMinutes($this->duracionMinutos());
    }

    public function duracionMinutos(): int
    {
        return (int) $this->input('duracion_minutos');
    }
}
