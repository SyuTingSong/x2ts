<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/7/11
 * Time: 下午3:39
 */

namespace x2ts;

/**
 * Class Utils
 * @package x2ts
 */
class Utils extends Component {
    protected static $_conf = [
        'ldap' => [
            'host'     => 'localhost',
            'port'     => 389,
            'dn_base'  => 'ou=staffs,dc=example,dc=com',
            'auth_key' => 'uid',
        ],
    ];

    public function ldap_auth(string $username, string $password):bool {
        $c = ldap_connect($this->conf['ldap']['host'], $this->conf['ldap']['port']);
        if (!$c) {
            throw new LDAPException('Cannot connect to LDAP server');
        }
        ldap_set_option($c, LDAP_OPT_PROTOCOL_VERSION, 3);
        $dn = "{$this->conf['ldap']['auth_key']}={$username},{$this->conf['ldap']['dn_base']}";
        Toolkit::trace('LDAP DN:' . $dn);
        $r = @ldap_bind($c, $dn, $password);
        ldap_close($c);
        return (bool) $r;
    }
}