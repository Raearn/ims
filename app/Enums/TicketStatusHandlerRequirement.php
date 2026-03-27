<?php

namespace App\Enums;

enum TicketStatusHandlerRequirement: string
{
    case None = 'none';
    case Optional = 'optional';
    case Required = 'required';
}
