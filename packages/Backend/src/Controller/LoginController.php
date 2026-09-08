<?php

declare(strict_types=1);

namespace Solidarity\Backend\Controller;

/**
 * The framework login controller, in Serbian.
 *
 * Constants only — no behaviour, no overridden method. Every rule about who may log in, how
 * links are issued and spent, and what a session is now lives in the framework and is the
 * same for users and delegates; this class exists solely so the dashboard speaks the
 * language its users do.
 *
 * It replaces DelegateLoginController, which was a copy of the framework controller that had
 * drifted: it held an account-status check the framework did not have, so an unverified
 * delegate was refused a login link at one entry point and issued one at another. That check
 * is now in MagicLinkService, where every entry point goes through it.
 *
 * Note the class name has to end in "LoginController": Controller::respond() derives the
 * template folder from it, and both this and the framework class resolve to themes/admin/login.
 */
class LoginController extends \Skeletor\Login\Controller\LoginController
{
    const LOGGED_OUT = 'Uspešno ste se odjavili.';

    const LOGIN_ERROR_INVALID = 'Pogrešni podaci za prijavu.';
    const LOGIN_ERROR_NO_EMAIL = 'Email nije pronađen u sistemu.';
    const LOGIN_ERROR_INACTIVE = 'Vaš nalog nije aktivan. Kontaktirajte administratora.';
    const LOGIN_ERROR_UNKNOWN_TYPE = 'Nepoznat tip prijave.';
    const LOGIN_SUCCESS = 'Uspešno ste se prijavili.';
    const LOGIN_ERROR_TOKEN = 'Forma je istekla. Osvežite stranicu i pokušajte ponovo.';

    const MAGIC_LINK_SENT = 'Link za login je poslat. Proverite mail.';
    const MAGIC_LINK_INVALID = 'Link za prijavu je neispravan ili je istekao.';
    const MAGIC_LINK_THROTTLED = 'Link za prijavu je upravo poslat. Proverite mail.';
    const INVALID_EMAIL = 'Unesite validnu email adresu';

    const GENERIC_ERROR = 'Došlo je do greške. Pokušajte ponovo.';
}
