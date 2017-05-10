<?php
namespace Phlite;

require_once 'PhliteException.php';

class GroupException extends PhliteException {
    public const CODE_PREFIX = 200;
    public const CODE = [
        'GROUP_NOT_FOUND'     => self::CODE_PREFIX + 1,
        'NAME_INVALID'        => self::CODE_PREFIX + 2,
        'NAME_UNAVAILABLE'    => self::CODE_PREFIX + 3,
        'DESCRIPTION_INVALID' => self::CODE_PREFIX + 4,
    ];
    protected const MESSAGE = [
        self::CODE['GROUP_NOT_FOUND']     => 'Group not found',
        self::CODE['NAME_INVALID']        => 'Invalid group name',
        self::CODE['NAME_UNAVAILABLE']    => 'Unavailable group name',
        self::CODE['DESCRIPTION_INVALID'] => 'Invalid group description',
    ];

    public function __construct(int $code) {
        parent::__construct("$code ".self::MESSAGE[$code], $code);
    }
}

?>
