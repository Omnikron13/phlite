<?php
namespace Phlite;

require_once 'PhliteException.php';

class UserException extends PhliteException {
    public const CODE_PREFIX = 100;
    public const CODE = [
        'USER_NOT_FOUND'       => self::CODE_PREFIX + 1,
        'USERNAME_INVALID'     => self::CODE_PREFIX + 2,
        'USERNAME_UNAVAILABLE' => self::CODE_PREFIX + 3,
        'EMAIL_INVALID'        => self::CODE_PREFIX + 4,
        'EMAIL_UNAVAILABLE'    => self::CODE_PREFIX + 5,
        'PASSWORD_INVALID'     => self::CODE_PREFIX + 6,
    ];
    protected const MESSAGE = [
        self::CODE['USER_NOT_FOUND']       => 'User not found',
        self::CODE['USERNAME_INVALID']     => 'Invalid username',
        self::CODE['USERNAME_UNAVAILABLE'] => 'Unavailable username',
        self::CODE['EMAIL_INVALID']        => 'Invalid email address',
        self::CODE['EMAIL_UNAVAILABLE']    => 'Unavailable email address',
        self::CODE['PASSWORD_INVALID']     => 'Invalid password',
    ];

    public function __construct(int $code) {
        parent::__construct("$code ".self::MESSAGE[$code], $code);
    }
}

?>
