<?php

namespace App\Support;

class AppPermissions
{
    public const USERS_MANAGE = 'usuarios.gestionar';

    public const BRANDING_MANAGE = 'marca.gestionar';

    public const QUOTES_VIEW = 'cotizaciones.ver';

    public const QUOTES_CREATE = 'cotizaciones.crear';

    public const QUOTES_EDIT = 'cotizaciones.editar';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::USERS_MANAGE,
            self::BRANDING_MANAGE,
            self::QUOTES_VIEW,
            self::QUOTES_CREATE,
            self::QUOTES_EDIT,
        ];
    }
}
