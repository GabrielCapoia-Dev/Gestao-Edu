<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class DominioEmail extends Model
{
    use HasFactory;
    use Notifiable;
    use HasRoles;


    protected $table = 'dominio_emails';

    protected $fillable = [
        'dominio_email',
        'setor',
        'status',
    ];
}
