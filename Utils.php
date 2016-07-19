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
 *
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

    public function is_lan_ip(string $ipv4):bool {
        $long = ip2long($ipv4);
        return $long & 0xff000000 === 0xa0000000 // 10.0.0.0/8
            || $long & 0xfff00000 === 0xac100000 // 172.16.0.0/12
            || $long & 0xffff0000 === 0xc0a80000;// 192.168.0.0/16
    }
}