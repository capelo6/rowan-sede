<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Festivo extends Model
{
    protected $fillable = ['fecha', 'descripcion', 'ambito'];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public const AMBITOS = ['nacional' => 'Nacional', 'autonomico' => 'Autonómico', 'local' => 'Local'];
}
