<?php
namespace Phlite;

require_once 'PhliteException.php';

class UserException extends PhliteException {
    public const CODE_PREFIX = 100;
    public const CODE = [
        'USER_NOT_FOUND'       => 1,
        'USERNAME_INVALID'     => 2,
        'USERNAME_UNAVAILABLE' => 3,
        'EMAIL_INVALID'        => 4,
        'EMAIL_UNAVAILABLE'    => 5,
        'PASSWORD_INVALID'     => 6,
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
        parent::__construct(null, $code + self::CODE_PREFIX);
    }
}

?>
