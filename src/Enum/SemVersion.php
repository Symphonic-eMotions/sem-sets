<?php
declare(strict_types=1);

namespace App\Enum;

enum SemVersion: string
{
    case V_1_0_0 = '1.0.0';
    case V_2_9_0 = '2.9.0';
    case V_2_9_5 = '2.9.5';
    case V_2_9_6 = '2.9.6';
}
