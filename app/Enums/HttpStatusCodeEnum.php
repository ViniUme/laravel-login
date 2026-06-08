<?php

namespace App\Enums;

enum HttpStatusCodeEnum: int
{
    case SUCCESS_OK = 200;
    case SUCCESS_CREATED = 201;
    case CLIENT_ERROR_UNAUTHORIZED = 401;
    case CLIENT_ERROR_NOT_FOUND = 404;
    case SERVER_ERROR_INTERNAL = 500;
}