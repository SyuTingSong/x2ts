<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/9/21
 * Time: 下午6:11
 */

namespace x2ts;


use x2ts\cache\ICache;

/**
 * Class Session
 *
 * @package x2ts
 *
 * @property-read string $session_id
 */
class Session extends Token {
    protected static $_conf = [
        'saveComponentId' => 'cache',
        'saveKeyPrefix'   => 'session_',
        'tokenLength'     => 16,
        'autoSave'        => true,
        'expireIn'        => 604800,
        'cookie'          => [
            'name'     => 'X_SESSION_ID',
            'expireIn' => null,
            'path'     => null,
            'domain'   => null,
            'secure'   => null,
            'httpOnly' => true,
        ],
    ];

    public static function getInstance(string $token = '') {
        $action = ComponentFactory::action();
        $sessionId = ComponentFactory::action()->cookie(static::$_conf['cookie']['name'], '');
        Toolkit::trace('SessionId from cookie: ' . $sessionId);

        $session = parent::getInstance($sessionId);
        if (!$sessionId) {
            $action->setCookie(
                static::$_conf['cookie']['name'],
                (string) $session,
                static::$_conf['cookie']['expireIn'] ?
                    time() + static::$_conf['cookie']['expireIn'] : null,
                static::$_conf['cookie']['path'],
                static::$_conf['cookie']['domain'],
                static::$_conf['cookie']['secure'],
                static::$_conf['cookie']['httpOnly']
            );
        }
        return $session;
    }

    public function destroy() {
        ComponentFactory::action()->setCookie(
            static::$_conf['cookie'],
            '',
            strtotime('1997-07-01 00:00:00 GMT+0800'),
            static::$_conf['cookie']['path'],
            static::$_conf['cookie']['domain'],
            static::$_conf['cookie']['secure'],
            static::$_conf['cookie']['httpOnly']
        );
        parent::destroy();
    }
}