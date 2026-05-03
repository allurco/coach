<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Action extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category',
        'priority',
        'importance',
        'difficulty',
        'deadline',
        'status',
        'completed_at',
        'result_notes',
        'snooze_until',
        'attachments',
    ];

    protected $casts = [
        'deadline' => 'date',
        'snooze_until' => 'date',
        'completed_at' => 'datetime',
        'attachments' => 'array',
    ];

    public const CATEGORIES = [
        'financeiro' => 'Financeiro',
        'fiscal' => 'Fiscal',
        'operacional' => 'Operacional',
        'crescimento' => 'Crescimento',
    ];

    public const PRIORITIES = [
        'alta' => 'Alta',
        'media' => 'Média',
        'baixa' => 'Baixa',
    ];

    public const IMPORTANCES = [
        'critico' => 'Crítico',
        'importante' => 'Importante',
        'rotineiro' => 'Rotineiro',
    ];

    public const DIFFICULTIES = [
        'rapido' => 'Rápido',
        'medio' => 'Médio',
        'pesado' => 'Pesado',
    ];

    public const STATUSES = [
        'pendente' => 'Pendente',
        'em_andamento' => 'Em andamento',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
    ];

    public function isOverdue(): bool
    {
        return $this->deadline
            && $this->status === 'pendente'
            && $this->deadline->isPast();
    }

    public function isDueSoon(int $days = 3): bool
    {
        return $this->deadline
            && $this->status === 'pendente'
            && $this->deadline->isBetween(now(), now()->addDays($days));
    }
}
