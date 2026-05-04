<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Guru = 'guru';
    case Murid = 'murid';
}