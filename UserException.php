<?php
namespace Phlite;

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

    public function __construct(int $code) {
        parent::__construct(null, $code + self::CODE_PREFIX);
    }
}

?>
