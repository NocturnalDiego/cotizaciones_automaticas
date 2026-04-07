<?php

namespace App\Support;

class AppPermissions
{
    public const USERS_MANAGE = 'usuarios.gestionar';

    public const BRANDING_MANAGE = 'marca.gestionar';

    public const QUOTES_VIEW = 'cotizaciones.ver';

    public const QUOTES_CREATE = 'cotizaciones.crear';

    public const QUOTES_EDIT = 'cotizaciones.editar';

    public const QUOTES_DELETE = 'cotizaciones.eliminar';

    public const CONTACTS_VIEW = 'contactos.ver';

    public const CONTACTS_EDIT = 'contactos.editar';

    public const CONTACTS_DELETE = 'contactos.eliminar';

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
            self::QUOTES_DELETE,
            self::CONTACTS_VIEW,
            self::CONTACTS_EDIT,
            self::CONTACTS_DELETE,
        ];
    }
}
