<?php
namespace Folksaurus;

/**
 * Class containing constants for common status codes.
 */
class StatusCodes
{
    const OK                 = 200;
    const CREATED            = 201;
    const NOT_MODIFIED       = 304;
    const BAD_REQUEST        = 400;
    const FORBIDDEN          = 403;
    const NOT_FOUND          = 404;
    const METHOD_NOT_ALLOWED = 405;
    const CONFLICT           = 409;
    const GONE               = 410;
    const SERVER_ERROR       = 500;
}