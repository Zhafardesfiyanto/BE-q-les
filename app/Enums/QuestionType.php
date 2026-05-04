<?php

namespace App\Enums;

enum QuestionType: string
{
    case PilihanGanda = 'pilihan_ganda';
    case PilihanGandaKompleks = 'pilihan_ganda_kompleks';
    case Uraian = 'uraian';
}