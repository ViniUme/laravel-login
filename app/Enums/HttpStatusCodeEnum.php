<?php

namespace App\Enums;

enum HttpStatusCodeEnum: int
{
    // * Success codes
    case SUCCESS_OK = 200;
    case SUCCESS_CREATED = 201;

    // * Client error codes
    case CLIENT_ERROR_BAD_REQUEST = 400;
    case CLIENT_ERROR_UNAUTHORIZED = 401;
    case CLIENT_ERROR_FORBIDDEN = 403;
    case CLIENT_ERROR_NOT_FOUND = 404;
    case CLIENT_ERROR_UNPROCESSABLE_ENTITY = 422;
    case CLIENT_ERROR_TOO_MANY_REQUESTS = 429;

    // * Server error code
    case SERVER_ERROR_INTERNAL = 500;
}
