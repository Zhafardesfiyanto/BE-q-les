<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Dikumpulkan = 'dikumpulkan';
    case Terlambat = 'terlambat';
}